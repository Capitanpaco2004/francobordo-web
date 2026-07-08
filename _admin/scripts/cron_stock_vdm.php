<?php
/**
 * cron_stock_vdm.php — actualizador de stock + descatalogados VDM / Alliance Marine (CLI, para cron)
 *
 * Fuente: SFTP Alliance Marine products/VDM_products_francobordo.csv (se regenera cada ~30 min;
 * el propio script lo descarga en cada pasada). Feed FULL REPLACEMENT: ausencia = ya no disponible.
 *
 * Reglas (usuario 2026-07-07, sentinels tipo RAM + variantes tipo Touron):
 *   - Nuestro stock > 0 (producto o variante) → NO se toca (stock real propio).
 *   - En feed con stock VDM > 0 (Active o End of life) → -100 (en proveedor).
 *   - En feed Active sin stock VDM               → -800 (bajo pedido).
 *   - En feed End of life/Dead sin stock         → -900 (no se repone: descatalogándose).
 *   - SIN fila en el feed                        → -900 (fuera de catálogo, full replacement).
 *   - Variantes: mismo criterio por variante en products_stock (clave options_id-values_id,
 *     UPSERT con guard SQL que nunca pisa stock propio > 0). Padre = agregado (algún -100 → -100;
 *     si no, algún -800 → -800; si no → -900).
 *   - Descatalogar: TODO no disponible (-900) + sin stock propio + status=1 → status=2 y se anota
 *     en vdm_csv_descatalogados. Si REAPARECE disponible → status 2→1 y se desanota (solo los que
 *     desactivó ESTE cron; las altas status=2 pendientes de revisar no se tocan).
 *   - status=0 (legacy) NUNCA se toca.
 * Toca products_quantity, products_stock y products_status. Precios NO se tocan.
 *
 * Nota: la tarea QFac de las 21:00 puede pisar sentinels de productos que hayan entrado en
 * QFac; al correr cada hora, este cron los re-aplica solo.
 *
 * Uso:
 *   php cron_stock_vdm.php                     # DRY: reporta sin tocar
 *   php cron_stock_vdm.php --apply             # ejecuta (lo usa el cron)
 *   php cron_stock_vdm.php --skip-download     # usa la copia local del CSV
 */

$apply        = in_array('--apply', $argv ?? [], true);
$skipDownload = in_array('--skip-download', $argv ?? [], true);

const CSV_PATH    = '/home/francobordo/public_html/import/feed/VDM/VDM_products_francobordo.csv';
const SFTP_HOST   = 'stftpamgprd01.blob.core.windows.net';
const SFTP_PORT   = 22;
const SFTP_USER   = 'stftpamgprd01.amghubfrancobordo';
const SFTP_PASS   = 'lkYgzugus0nD0bQi/smYJLzSMY2RyFBS';
const SFTP_REMOTE = 'products/VDM_products_francobordo.csv';
const STOCK_PROVEEDOR   = -100; // VDM tiene stock
const STOCK_BAJOPEDIDO  = -800; // en catálogo sin stock
const STOCK_DESCATALOG  = -900; // fuera de catálogo / EOL agotado
const MARKER_TABLE = 'vdm_csv_descatalogados';

function out($msg) { echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n"; }

require '/home/francobordo/public_html/includes/vendor/autoload.php';
use phpseclib3\Net\SFTP;

// ── conexión BD (configure.php por regex — application_top rompe en CLI) ──
$conf = file_get_contents('/home/francobordo/public_html/includes/configure.php');
preg_match("/'DB_SERVER',\s*'([^']+)'/", $conf, $m);          $DB_HOST = $m[1] ?? 'localhost';
preg_match("/'DB_SERVER_USERNAME',\s*'([^']+)'/", $conf, $m); $DB_USER = $m[1] ?? '';
preg_match("/'DB_SERVER_PASSWORD',\s*'([^']+)'/", $conf, $m); $DB_PASS = $m[1] ?? '';
preg_match("/'DB_DATABASE',\s*'([^']+)'/", $conf, $m);        $DB_NAME = $m[1] ?? '';
$db = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($db->connect_error) { out('ERROR BD: ' . $db->connect_error); exit(1); }
$db->set_charset('utf8');

// ── descarga del feed ──
if (!$skipDownload) {
    try {
        $dir = dirname(CSV_PATH);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $sftp = new SFTP(SFTP_HOST, SFTP_PORT, 30);
        if (!$sftp->login(SFTP_USER, SFTP_PASS)) { out('AVISO: login SFTP fallido — uso copia local'); }
        else {
            $tmp = CSV_PATH . '.tmp.' . uniqid();
            if ($sftp->get(SFTP_REMOTE, $tmp) && file_exists($tmp) && filesize($tmp) >= 1000000) {
                @rename($tmp, CSV_PATH);
                out('Feed descargado: ' . round(filesize(CSV_PATH) / 1048576, 1) . ' MB');
            } else { @unlink($tmp); out('AVISO: descarga SFTP fallida/incompleta — uso copia local'); }
        }
    } catch (Throwable $e) { out('AVISO: SFTP: ' . $e->getMessage() . ' — uso copia local'); }
}
if (!file_exists(CSV_PATH)) { out('ERROR: no existe ' . CSV_PATH); exit(1); }
$age = time() - filemtime(CSV_PATH);
if ($age > 3600 * 6) out('AVISO: el CSV tiene ' . round($age / 3600, 1) . 'h (el feed se regenera cada 30 min — ¿SFTP caído?)');

// ── parseo (pipe |, UTF-8, con cabecera) ──
$fh = fopen(CSV_PATH, 'r');
if (!$fh) { out('ERROR: no se pudo abrir ' . CSV_PATH); exit(1); }
$header = fgetcsv($fh, 0, '|', chr(34), '');
if (!$header) { out('ERROR: CSV sin cabecera'); exit(1); }
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
$idx = array_flip(array_map('trim', $header));
foreach (['SKU', 'Available_stock', 'Product_status'] as $need) {
    if (!isset($idx[$need])) { out("ERROR: falta columna $need en el CSV"); exit(1); }
}
$ncols = count($header);
function ean13Checksum($p) { if (strlen($p) !== 12 || !ctype_digit($p)) return -1; $s = 0; for ($i = 0; $i < 12; $i++) { $d = (int) $p[$i]; $s += ($i % 2 === 0) ? $d : $d * 3; } return (10 - ($s % 10)) % 10; }
function isValidEan13($e) { $e = trim((string) $e); return strlen($e) === 13 && ctype_digit($e) && ean13Checksum(substr($e, 0, 12)) === (int) $e[12]; }
function vdmNormalizeEan($raw) { $e = preg_replace('/\D/', '', trim((string) $raw)); if (strlen($e) === 12) $e = '0' . $e; elseif (strlen($e) === 14 && $e[0] === '0') $e = substr($e, 1); return isValidEan13($e) ? $e : ''; }

$bySku = []; $byEan = []; $rows = 0; $bad = 0;
while (($r = fgetcsv($fh, 0, '|', chr(34), '')) !== false) {
    if (count($r) !== $ncols) { $bad++; continue; }
    $sku = trim((string) ($r[$idx['SKU']] ?? ''));
    if ($sku === '') continue;
    $rows++;
    $stock = (int) floor((float) str_replace(',', '.', trim((string) ($r[$idx['Available_stock']] ?? '0'))));
    $status = trim((string) ($r[$idx['Product_status']] ?? ''));
    $active = (strcasecmp($status, 'Active') === 0);
    $rec = ['stock' => $stock, 'active' => $active];
    $bySku[strtolower($sku)] = $rec;
    $sup = strtolower(trim((string) ($r[$idx['supplier_item_code']] ?? '')));
    if ($sup !== '' && !isset($bySku[$sup])) $bySku[$sup] = $rec;
    $ean = vdmNormalizeEan($r[$idx['Barcode']] ?? '');
    if ($ean !== '' && !isset($byEan[$ean])) $byEan[$ean] = $rec;
}
fclose($fh);
if (empty($bySku)) { out('ERROR: CSV vacío o ilegible'); exit(1); }
out("Feed: $rows filas (mal formadas: $bad) | claves SKU/ref: " . count($bySku) . " | EAN: " . count($byEan));

/** Sentinel objetivo para un registro del feed (o null si no está). */
function targetFor($rec) {
    if ($rec === null) return STOCK_DESCATALOG;              // fuera del feed (full replacement)
    if ($rec['stock'] > 0) return STOCK_PROVEEDOR;           // VDM tiene stock (Active o EOL)
    return $rec['active'] ? STOCK_BAJOPEDIDO : STOCK_DESCATALOG; // sin stock: Active=bajo pedido, EOL/Dead=fin
}
function feedLookup($bySku, $byEan, array $skuCands, array $eanCands) {
    foreach ($skuCands as $s) { $s = strtolower(trim((string) $s)); if ($s !== '' && isset($bySku[$s])) return $bySku[$s]; }
    foreach ($eanCands as $e) { $e = trim((string) $e); if ($e !== '' && isset($byEan[$e])) return $byEan[$e]; }
    return null;
}

// ── tabla marcador de descatalogados por este cron ──
$db->query("CREATE TABLE IF NOT EXISTS " . MARKER_TABLE . " (
    products_id INT NOT NULL PRIMARY KEY,
    products_model VARCHAR(96) NOT NULL DEFAULT '',
    date_added DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8");
$marker = [];
$r = $db->query("SELECT products_id FROM " . MARKER_TABLE);
if ($r) while ($row = $r->fetch_assoc()) $marker[(int) $row['products_id']] = true;

// ── productos VDM + variantes + stock por variante ──
$prods = [];
$res = $db->query("SELECT products_id, products_model, reference_prov, product_ean, products_quantity, products_status FROM products WHERE products_import_origin LIKE 'vdm%'");
if (!$res) { out('ERROR query productos: ' . $db->error); exit(1); }
while ($p = $res->fetch_assoc()) $prods[(int) $p['products_id']] = $p;
if (empty($prods)) { out('No hay productos origin=vdm en BD. Nada que hacer.'); exit(0); }
$ids = implode(',', array_keys($prods));

$attrsByProd = [];
$res = $db->query("SELECT products_attributes_id, products_id, options_id, options_values_id, reference, reference_prov, products_attributes_ean FROM products_attributes WHERE products_id IN ($ids)");
while ($res && $a = $res->fetch_assoc()) $attrsByProd[(int) $a['products_id']][] = $a;

$stockCur = [];
$res = $db->query("SELECT products_id, products_stock_attributes, products_stock_quantity FROM products_stock WHERE products_id IN ($ids)");
while ($res && $s = $res->fetch_assoc()) $stockCur[(int) $s['products_id']][$s['products_stock_attributes']] = (int) $s['products_stock_quantity'];

// ── decisión ──
$updQty = [STOCK_PROVEEDOR => [], STOCK_BAJOPEDIDO => [], STOCK_DESCATALOG => []];
$stockUpserts = [];   // [pid, key, qty]
$toDeact = []; $toResur = []; $markerStale = [];
$nProds = 0; $ownStock = 0; $qtyUnchanged = 0; $varOwn = 0; $varUnchanged = 0; $varChanges = 0;

foreach ($prods as $pid => $p) {
    $nProds++;
    $qty = (int) $p['products_quantity'];
    $status = (int) $p['products_status'];
    $variants = $attrsByProd[$pid] ?? [];

    if (empty($variants)) {
        $rec = feedLookup($bySku, $byEan, [$p['reference_prov'], $p['products_model']], [$p['product_ean']]);
        $target = targetFor($rec);
        $available = ($target !== STOCK_DESCATALOG);
    } else {
        $targets = [];
        foreach ($variants as $a) {
            $rec = feedLookup($bySku, $byEan, [$a['reference_prov'], $a['reference']], [$a['products_attributes_ean']]);
            $t = targetFor($rec);
            $targets[] = $t;
            $key = $a['options_id'] . '-' . $a['options_values_id'];
            $cur = $stockCur[$pid][$key] ?? null;
            if ($cur !== null && $cur > 0) { $varOwn++; continue; }   // stock propio de variante: no tocar
            if ($cur === $t) { $varUnchanged++; continue; }
            $stockUpserts[] = [$pid, $key, $t];
            $varChanges++;
        }
        // agregado para el padre
        if (in_array(STOCK_PROVEEDOR, $targets, true)) $target = STOCK_PROVEEDOR;
        elseif (in_array(STOCK_BAJOPEDIDO, $targets, true)) $target = STOCK_BAJOPEDIDO;
        else $target = STOCK_DESCATALOG;
        $available = ($target !== STOCK_DESCATALOG);
    }

    // marcador: resurrección / limpieza
    if (isset($marker[$pid])) {
        if ($status !== 2) $markerStale[] = $pid;                       // tocado a mano → desanotar
        elseif ($available) $toResur[] = ['pid' => $pid, 'model' => $p['reference_prov'] ?: $p['products_model']];
    }
    // descatalogar: nada disponible + sin stock propio + activo (solo 1→2; el 0 legacy NUNCA)
    if (!$available && $qty <= 0 && $status === 1 && !isset($marker[$pid])) {
        $toDeact[] = ['pid' => $pid, 'model' => $p['reference_prov'] ?: $p['products_model']];
    }

    // quantity del producto
    if ($qty > 0) { $ownStock++; continue; }
    if ($qty === $target) { $qtyUnchanged++; continue; }
    $updQty[$target][] = $pid;
}

$mode = $apply ? 'APPLY' : 'DRY';
out("[$mode] BD VDM: $nProds productos | marcador: " . count($marker));
out("[$mode] Quantity padre: " . count($updQty[STOCK_PROVEEDOR]) . " → -100 (en proveedor), " . count($updQty[STOCK_BAJOPEDIDO]) . " → -800 (bajo pedido), " . count($updQty[STOCK_DESCATALOG]) . " → -900 (fuera de catálogo)");
out("[$mode] Intocables stock propio>0: $ownStock | ya correctos: $qtyUnchanged");
out("[$mode] Variantes (products_stock): $varChanges upserts | propias>0 intocables: $varOwn | ya correctas: $varUnchanged");
out("[$mode] Status: descatalogar (1→2): " . count($toDeact) . " | resucitar (2→1): " . count($toResur) . " | marcador obsoleto: " . count($markerStale));
foreach (array_slice($toDeact, 0, 20) as $d) out("  DESCATALOGAR pid={$d['pid']} {$d['model']}");
foreach (array_slice($toResur, 0, 20) as $d) out("  RESUCITAR   pid={$d['pid']} {$d['model']}");

$total = count($updQty[STOCK_PROVEEDOR]) + count($updQty[STOCK_BAJOPEDIDO]) + count($updQty[STOCK_DESCATALOG])
       + count($stockUpserts) + count($toDeact) + count($toResur) + count($markerStale);
if ($apply && $total > 0) {
    $db->begin_transaction();
    try {
        // quantity padre — guard: nunca pisar stock real aparecido entre el plan y el update
        foreach ($updQty as $target => $pids) {
            foreach (array_chunk($pids, 500) as $chunk) {
                if (!$db->query("UPDATE products SET products_quantity = " . (int) $target . ", products_last_modified = NOW() WHERE products_id IN (" . implode(',', $chunk) . ") AND products_quantity <= 0"))
                    throw new Exception($db->error);
            }
        }
        // stock por variante — UPSERT con guard SQL (jamás pisa stock propio > 0)
        foreach (array_chunk($stockUpserts, 500) as $chunk) {
            $values = [];
            foreach ($chunk as [$pid, $key, $q]) $values[] = "($pid, '" . $db->real_escape_string($key) . "', " . (int) $q . ")";
            if (!$db->query("INSERT INTO products_stock (products_id, products_stock_attributes, products_stock_quantity) VALUES " . implode(',', $values) .
                " ON DUPLICATE KEY UPDATE products_stock_quantity = IF(products_stock_quantity > 0, products_stock_quantity, VALUES(products_stock_quantity))"))
                throw new Exception('upsert products_stock: ' . $db->error);
        }
        // descatalogar 1→2 + anotar (guards re-verifican estado)
        foreach ($toDeact as $d) {
            if (!$db->query("UPDATE products SET products_status = 2, products_last_modified = NOW() WHERE products_id = {$d['pid']} AND products_status = 1 AND products_quantity <= 0"))
                throw new Exception($db->error);
            if ($db->affected_rows > 0) {
                if (!$db->query("INSERT IGNORE INTO " . MARKER_TABLE . " (products_id, products_model, date_added) VALUES ({$d['pid']}, '" . $db->real_escape_string($d['model']) . "', NOW())"))
                    throw new Exception($db->error);
            }
        }
        // resucitar 2→1 + desanotar
        foreach ($toResur as $d) {
            if (!$db->query("UPDATE products SET products_status = 1, products_last_modified = NOW() WHERE products_id = {$d['pid']} AND products_status = 2"))
                throw new Exception($db->error);
            if (!$db->query("DELETE FROM " . MARKER_TABLE . " WHERE products_id = {$d['pid']}"))
                throw new Exception($db->error);
        }
        if (!empty($markerStale)) {
            if (!$db->query("DELETE FROM " . MARKER_TABLE . " WHERE products_id IN (" . implode(',', array_map('intval', $markerStale)) . ")"))
                throw new Exception($db->error);
        }
        $db->commit();
        out("[APPLY] OK: quantity " . (count($updQty[STOCK_PROVEEDOR]) + count($updQty[STOCK_BAJOPEDIDO]) + count($updQty[STOCK_DESCATALOG]))
            . " | variantes $varChanges | descatalogados " . count($toDeact) . " | resucitados " . count($toResur)
            . " | marcadores limpiados " . count($markerStale) . ".");
    } catch (Throwable $e) {
        $db->rollback();
        out("[APPLY] ERROR: " . $e->getMessage() . " — ROLLBACK");
        exit(1);
    }
} elseif (!$apply) {
    out("[DRY] No se ha tocado nada (usa --apply para ejecutar).");
}
exit(0);
