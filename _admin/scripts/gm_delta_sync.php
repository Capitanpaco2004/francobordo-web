<?php
/**
 * gm_delta_sync.php — Punto 3: precio/stock casi-real-time hacia Google Merchant.
 *
 * Replica EXACTAMENTE la lógica del feed francobordo.txt (feedmachine _wa):
 *   set    = products_status=1 AND exclude_feedmachine=0 AND (EAN padre o EAN variante),
 *            una oferta por variante (offer_id = pid o "pid-products_attributes_id")
 *   precio = special activa del padre (pisa también variantes) o base±delta variante,
 *            × IVA (tax_rates zona 31 por tax_class), round 2
 *   stock  = products_quantity del PADRE > -900 → 'in stock', si no 'out of stock'
 *
 * Empuja deltas a una fuente SUPLEMENTARIA de Merchant API (products/v1
 * productInputs:insert) que tiene prioridad sobre la primaria SOLO en los
 * atributos que enviamos (price, availability). Estado en tabla gm_push_state.
 *
 * Modos:
 *   --setup            crea la fuente suplementaria + enlaza defaultRule (backup previo)
 *   --seed             primera carga del estado SIN empujar nada (el feed ya está al día)
 *   --test-push=OFFER  empuja una única oferta con sus valores actuales (humo)
 *   --status           contadores
 *   (sin args)         run normal: diff + push deltas (cap por ejecución)
 *
 * Cron: cada 15 min con flock. Log: /home/francobordo/gm_delta.log
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require '/home/francobordo/public_html/includes/classes/google_merchant.php';

const DS_DISPLAY   = 'API deltas precio-stock';
const DS_CACHE     = '/home/francobordo/gm_datasource.json';
const PUSH_CAP     = 600;    // inserts máx por ejecución
const DELETE_CAP   = 100;    // deletes máx por ejecución
const PRIMARY_DS   = 'accounts/7605527/dataSources/10609135583';   // francobordo.txt

$gm = new google_merchant();
if (!$gm->configured()) { echo date('c') . ' CONFIG: ' . $gm->error() . "\n"; exit(1); }
$ACC = $gm->accountId();

include '/home/francobordo/public_html/includes/configure.php';
$db = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($db->connect_errno) { echo date('c') . " BD: {$db->connect_error}\n"; exit(1); }
$db->set_charset('utf8mb4');

$db->query("CREATE TABLE IF NOT EXISTS gm_push_state (
    offer_id     VARCHAR(32) NOT NULL PRIMARY KEY,
    price        DECIMAL(15,2) NOT NULL,
    availability CHAR(1) NOT NULL,
    pushed       TINYINT NOT NULL DEFAULT 0,
    updated_at   DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=ascii");

/* ---------------- fuente suplementaria ---------------- */

function ds_name($gm, $ACC, $create = true) {
    $c = json_decode((string)@file_get_contents(DS_CACHE), true);
    if (!empty($c['name'])) return $c['name'];
    $r = $gm->request('GET', "https://merchantapi.googleapis.com/datasources/v1/accounts/$ACC/dataSources");
    foreach ((array)($r['data']['dataSources'] ?? array()) as $ds) {
        if (($ds['displayName'] ?? '') === DS_DISPLAY) {
            @file_put_contents(DS_CACHE, json_encode(array('name' => $ds['name'])));
            return $ds['name'];
        }
    }
    if (!$create) return null;
    // v1: suplementaria "genérica" (sin feedLabel/contentLanguage — la beta los admitía,
    // v1 los rechaza con Unexpected field; cada productInput lleva los suyos)
    $r = $gm->request('POST', "https://merchantapi.googleapis.com/datasources/v1/accounts/$ACC/dataSources", array(
        'displayName' => DS_DISPLAY,
        'supplementalProductDataSource' => (object)array(),
    ));
    if ($r['code'] !== 200 || empty($r['data']['name'])) {
        echo date('c') . " crear datasource FALLO: {$r['error']} " . substr($r['raw'], 0, 300) . "\n";
        exit(1);
    }
    @file_put_contents(DS_CACHE, json_encode(array('name' => $r['data']['name'])));
    echo date('c') . " datasource suplementaria creada: {$r['data']['name']}\n";
    return $r['data']['name'];
}

/* ---------------- valores actuales (réplica del feed) ---------------- */

function tax_map($db) {
    $map = array(0 => 0.0);
    $q = $db->query("SELECT tax_class_id, tax_priority, SUM(tax_rate) r FROM tax_rates WHERE tax_zone_id = 31 GROUP BY tax_class_id, tax_priority");
    $tmp = array();
    while ($x = $q->fetch_assoc()) $tmp[(int)$x['tax_class_id']][] = (float)$x['r'];
    foreach ($tmp as $c => $rs) { $f = 1.0; foreach ($rs as $r) $f *= (1 + $r / 100); $map[$c] = ($f - 1) * 100; }
    return $map;
}

function current_offers($db) {
    $iva = tax_map($db);
    $out = array();
    $q = $db->query("
        SELECT IF(pa.products_attributes_id IS NOT NULL, CONCAT(p.products_id,'-',pa.products_attributes_id), p.products_id) AS offer_id,
               p.products_quantity, p.products_tax_class_id,
               (CASE WHEN pa.price_prefix = '+' THEN (p.products_price + pa.options_values_price)
                     WHEN pa.price_prefix = '-' THEN (p.products_price - pa.options_values_price)
                     ELSE p.products_price END) AS base_price,
               s.specials_new_products_price AS sp, s.status AS sp_st
        FROM products p
        LEFT JOIN products_attributes pa ON p.products_id = pa.products_id
        LEFT JOIN specials s ON p.products_id = s.products_id
        WHERE p.products_status = 1 AND p.exclude_feedmachine = 0
          AND (p.product_ean <> '' OR (pa.products_attributes_ean IS NOT NULL AND pa.products_attributes_ean <> '' AND pa.products_attributes_ean <> '0'))", MYSQLI_USE_RESULT);
    while ($x = $q->fetch_assoc()) {
        $oid  = (string)$x['offer_id'];
        $base = ((int)$x['sp_st'] === 1 && (float)$x['sp'] > 0) ? (float)$x['sp'] : (float)$x['base_price'];
        $rate = $iva[(int)$x['products_tax_class_id']] ?? 0.0;
        $out[$oid] = array(
            'p' => round($base * (1 + $rate / 100), 2),
            'a' => ((float)$x['products_quantity'] > -900) ? 'i' : 'o',
        );
    }
    $q->free();
    return $out;
}

function push_offer($gm, $ACC, $dsName, $oid, $v) {
    $body = array(
        'offerId'         => $oid,
        'contentLanguage' => 'es',
        'feedLabel'       => 'ES',
        'productAttributes' => array(   // v1 GA renombró attributes → productAttributes
            'price'        => array('amountMicros' => (string)(int)round($v['p'] * 1000000), 'currencyCode' => 'EUR'),
            'availability' => ($v['a'] === 'i') ? 'IN_STOCK' : 'OUT_OF_STOCK',   // enum v1 (el feed usa "in stock", la API no)
        ),
    );
    return $gm->request('POST', "https://merchantapi.googleapis.com/products/v1/accounts/$ACC/productInputs:insert?dataSource=" . rawurlencode($dsName), $body);
}

/* ---------------- modos ---------------- */

$argvStr = implode(' ', array_slice((array)$argv, 1));

if (strpos($argvStr, '--setup') !== false) {
    $dsName = ds_name($gm, $ACC, true);
    // backup de la datasource primaria antes de tocar su defaultRule
    $g = $gm->request('GET', 'https://merchantapi.googleapis.com/datasources/v1/' . PRIMARY_DS);
    if ($g['code'] !== 200) { echo "GET primaria FALLO: {$g['error']}\n"; exit(1); }
    @file_put_contents('/home/francobordo/gm_backups/datasource-primaria-' . date('Ymd-His') . '.json', $g['raw']);
    $rule = $g['data']['primaryProductDataSource']['defaultRule']['takeFromDataSources'] ?? array();
    foreach ($rule as $e) {
        if (($e['supplementalDataSourceName'] ?? '') === $dsName) { echo "defaultRule ya enlazada, nada que hacer\n"; exit(0); }
    }
    // la PRIMERA de la lista tiene prioridad → suplementaria delante, self detrás
    $r = $gm->request('PATCH', 'https://merchantapi.googleapis.com/datasources/v1/' . PRIMARY_DS . '?updateMask=primaryProductDataSource.defaultRule', array(
        'primaryProductDataSource' => array('defaultRule' => array('takeFromDataSources' => array(
            array('supplementalDataSourceName' => $dsName),
            array('self' => true),
        ))),
    ));
    echo ($r['code'] === 200)
        ? "defaultRule enlazada: [suplementaria, self] OK\n"
        : "PATCH defaultRule FALLO ({$r['error']}): " . substr($r['raw'], 0, 400) . "\n";
    exit($r['code'] === 200 ? 0 : 1);
}

if (strpos($argvStr, '--status') !== false) {
    $x = $db->query("SELECT COUNT(*) n, SUM(pushed) p FROM gm_push_state")->fetch_assoc();
    echo "estado: {$x['n']} ofertas, {$x['p']} empujadas alguna vez\n";
    exit(0);
}

if (preg_match('/--test-push=(\S+)/', $argvStr, $mm)) {
    $dsName = ds_name($gm, $ACC, false);
    if (!$dsName) { echo "no hay datasource (corre --setup)\n"; exit(1); }
    $cur = current_offers($db);
    $oid = $mm[1];
    if (!isset($cur[$oid])) { echo "la oferta $oid no está en el set del feed\n"; exit(1); }
    $r = push_offer($gm, $ACC, $dsName, $oid, $cur[$oid]);
    echo "test-push $oid (precio " . number_format($cur[$oid]['p'], 2, ',', '') . " €, " . ($cur[$oid]['a'] === 'i' ? 'in stock' : 'out of stock') . "): HTTP {$r['code']}\n";
    if ($r['code'] !== 200) echo substr($r['raw'], 0, 500) . "\n";
    else $db->query("REPLACE INTO gm_push_state VALUES ('" . $db->real_escape_string($oid) . "', {$cur[$oid]['p']}, '{$cur[$oid]['a']}', 1, NOW())");
    exit($r['code'] === 200 ? 0 : 1);
}

/* ---------------- seed / run normal ---------------- */

$seed = (strpos($argvStr, '--seed') !== false);
$cur  = current_offers($db);

$state = array();
$q = $db->query("SELECT offer_id, price, availability, pushed FROM gm_push_state", MYSQLI_USE_RESULT);
while ($x = $q->fetch_assoc()) $state[$x['offer_id']] = $x;
$q->free();

if ($seed || !count($state)) {
    $db->query("TRUNCATE gm_push_state");
    $stmt = $db->prepare("INSERT INTO gm_push_state VALUES (?, ?, ?, 0, NOW())");
    $n = 0;
    $db->query("START TRANSACTION");
    foreach ($cur as $oid => $v) { $stmt->bind_param('sds', $oid, $v['p'], $v['a']); $stmt->execute(); $n++; }
    $db->query("COMMIT");
    echo date('c') . " SEED: $n ofertas registradas (sin empujar nada)\n";
    exit(0);
}

$dsName = ds_name($gm, $ACC, false);
if (!$dsName) { echo date('c') . " no hay datasource suplementaria (corre --setup)\n"; exit(1); }

$pushed = $errors = $deleted = 0;
$stmt = $db->prepare("REPLACE INTO gm_push_state VALUES (?, ?, ?, 1, NOW())");

foreach ($cur as $oid => $v) {
    if ($pushed + $errors >= PUSH_CAP) break;
    $st = $state[$oid] ?? null;
    if ($st !== null && abs((float)$st['price'] - $v['p']) < 0.005 && $st['availability'] === $v['a']) continue;
    if ($st === null) {
        // oferta nueva en el set: el feed primario la traerá; solo registrar estado
        $db->query("INSERT IGNORE INTO gm_push_state VALUES ('" . $db->real_escape_string($oid) . "', {$v['p']}, '{$v['a']}', 0, NOW())");
        continue;
    }
    $r = push_offer($gm, $ACC, $dsName, $oid, $v);
    if ($r['code'] === 200) { $stmt->bind_param('sds', $oid, $v['p'], $v['a']); $stmt->execute(); $pushed++; }
    else {
        $errors++;
        echo date('c') . " ERROR push $oid HTTP {$r['code']}: " . substr((string)$r['raw'], 0, 200) . "\n";
        if ($r['code'] === 401 || $r['code'] === 403 || $r['code'] === 429) break;   // no insistir si es global
    }
    if (($pushed % 20) === 0) usleep(50000);
}

// ofertas que salieron del set: borrar su input suplementario (solo si lo empujamos alguna vez)
foreach ($state as $oid => $st) {
    if (isset($cur[$oid])) continue;
    if ($deleted >= DELETE_CAP) break;
    if ((int)$st['pushed'] === 1) {
        $gm->request('DELETE', "https://merchantapi.googleapis.com/products/v1/accounts/$ACC/productInputs/" . rawurlencode('es~ES~' . $oid) . '?dataSource=' . rawurlencode($dsName));
        $deleted++;
    }
    $db->query("DELETE FROM gm_push_state WHERE offer_id = '" . $db->real_escape_string($oid) . "'");
}

echo date('c') . " run: " . count($cur) . " ofertas, $pushed empujadas, $errors errores, $deleted bajas\n";
