<?php
require 'includes/application_top.php';
require_once dirname(dirname(__FILE__)) . '/includes/vendor/autoload.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

/* ──────────────────────────────────────────────────────────────────────────
 * Actualizador de precios Oceansouth
 *
 * Igual de fuentes que el importador:
 *   - COSTE ← xlsx /import/OceanSouth/PRICE LIST 2025.xlsx (A=SKU, C=EUR NET, D=Barcode)
 *   - PVP   ← Shopify oceansouth.com/en-eu products.json (variant.price, IVA incl) → roundToNickel(/1,21)
 * Enlace por SKU. Recalcula products_price/products_cost/G1 y, en familias, los deltas
 * de cada variante (la más barata define el precio del padre). Stock NO se toca.
 * ────────────────────────────────────────────────────────────────────────── */

const OS_DIR          = '/home/francobordo/public_html/import/OceanSouth/';
const OS_CACHE_DIR    = OS_DIR . 'cache/';
const OS_CACHE        = OS_CACHE_DIR . 'catalog.json';
const OS_SKUMAP_CACHE = OS_CACHE_DIR . 'skumap.json';   // compartido con el importador
const OS_XLSX_SHEET   = 'PRICE LIST 2025';
const SHOP_BASE       = 'https://oceansouth.com/en-eu';
const G1_GROUP_ID     = 1;
const G1_FLOOR_FACTOR = 1.10;
const PRICE_THRESHOLD = 0.005;          // 0.5%
const MAX_CHANGE_PCT_DEF = 30;          // tope superior default (configurable)
const IVA_ES          = 1.21;
const MFG_ID          = 595;
const SCOPE_SQL       = "(p.products_import_origin LIKE 'oceansouth%' OR p.manufacturers_id = " . MFG_ID . ")";

function roundToNickel($net) { $wi = ((float) $net) * IVA_ES; $r = round($wi * 20) / 20; return round($r / IVA_ES, 4); }
function priceNetFromWebGross($gross) { return roundToNickel(((float) $gross) / IVA_ES); }
function ean13Checksum($p) { if (strlen($p) !== 12 || !ctype_digit($p)) return -1; $s = 0; for ($i = 0; $i < 12; $i++) { $d = (int) $p[$i]; $s += ($i % 2 === 0) ? $d : $d * 3; } return (10 - ($s % 10)) % 10; }
function isValidEan13($e) { $e = trim((string) $e); return strlen($e) === 13 && ctype_digit($e) && ean13Checksum(substr($e, 0, 12)) === (int) $e[12]; }

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$confirmExec = isset($_POST['confirm_execute']) || isset($_GET['confirm_execute']);
$dryRun = !($action === 'execute' && $confirmExec);
$applyExtremes = isset($_POST['apply_extremes']) || isset($_GET['apply_extremes']);
$onlyExtremes  = isset($_POST['only_extremes']) || isset($_GET['only_extremes']);
$noStock       = (($_POST['scope'] ?? $_GET['scope'] ?? 'all') === 'no_stock');
$maxChangePct  = isset($_POST['max_change_pct']) ? (float) $_POST['max_change_pct'] : (isset($_GET['max_change_pct']) ? (float) $_GET['max_change_pct'] : MAX_CHANGE_PCT_DEF);
if ($maxChangePct < 0) $maxChangePct = 0;
$maxChangeRatio = $maxChangePct / 100.0;
$isAction = ($action === 'plan' || $action === 'execute');

function logMsg($msg) {
    $line = '[' . date('H:i:s') . '] ' . $msg . "\n";
    echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars($line) . '</pre>';
    @flush();
}
function fmt4($n) { return number_format((float) $n, 4, '.', ''); }
function priceDeltaPct($oldP, $newP) { $ref = max(abs((float) $oldP), 0.01); return abs((float) $newP - (float) $oldP) / $ref; }
function osParseNum($v) { $v = trim((string) $v); if ($v === '') return null; $v = str_replace(',', '.', $v); return is_numeric($v) ? (float) $v : null; }

function calcG1Price($price, $cost) {
    $price = (float) $price; $cost = (float) $cost;
    if ($price <= 0) return 0.0;
    $mult = 0.90;
    if ($cost > 0) {
        $margin = ($price - $cost) / $price;
        if      ($margin >= 0.45) $mult = 0.75;
        elseif  ($margin >= 0.40) $mult = 0.80;
        elseif  ($margin >= 0.35) $mult = 0.82;
        elseif  ($margin >= 0.30) $mult = 0.85;
    }
    return round(max($price * $mult, $cost * G1_FLOOR_FACTOR), 4);
}

function osBrowserUA() { return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'; }

function findNewestXlsx($dir) {
    if (!is_dir($dir)) return null; $best = null; $bestT = 0;
    foreach (scandir($dir) as $f) { if (substr($f, -5) !== '.xlsx' || substr($f, 0, 2) === '~$') continue; $t = filemtime($dir . $f); if ($t > $bestT) { $bestT = $t; $best = $dir . $f; } }
    return $best;
}

/** xlsx → [costBySku, costByEan]. cost = col C (EUR NET). Salta "TBD"/vacío.
 *  Reutiliza la cache skumap.json del importador (sku=>{cost,ean}); solo parsea el
 *  xlsx (~60s) cuando el fichero cambia (clave = nombre:mtime:size). */
function osLoadXlsxCost($path) {
    $sig = basename($path) . ':' . filemtime($path) . ':' . filesize($path);
    $skuMap = null;
    if (file_exists(OS_SKUMAP_CACHE)) {
        $c = json_decode((string) file_get_contents(OS_SKUMAP_CACHE), true);
        if (is_array($c) && ($c['sig'] ?? '') === $sig && isset($c['map']) && is_array($c['map'])) $skuMap = $c['map'];
    }
    if ($skuMap === null) {
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly([OS_XLSX_SHEET]);
            $ss = $reader->load($path); $sheet = $ss->getSheet(0);
        } catch (Throwable $e) {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true); $ss = $reader->load($path);
            $sheet = $ss->getSheetByName(OS_XLSX_SHEET) ?: $ss->getSheet(0);
        }
        $skuMap = [];
        foreach ($sheet->getRowIterator(2) as $row) {
            $ri = $row->getRowIndex();
            $sku = trim((string) $sheet->getCell('A' . $ri)->getValue());
            if ($sku === '' || strtoupper($sku) === 'ITEM NO.') continue;
            $cost = $sheet->getCell('C' . $ri)->getValue();
            $ean  = trim((string) $sheet->getCell('D' . $ri)->getValue());
            $skuMap[$sku] = ['cost' => ($cost === null || $cost === '') ? null : (float) $cost, 'ean' => isValidEan13($ean) ? $ean : ''];
        }
        if (!empty($skuMap)) { if (!is_dir(OS_CACHE_DIR)) @mkdir(OS_CACHE_DIR, 0775, true); @file_put_contents(OS_SKUMAP_CACHE, json_encode(['sig' => $sig, 'map' => $skuMap], JSON_UNESCAPED_UNICODE)); }
    }
    $bySku = []; $byEan = [];
    foreach ($skuMap as $sku => $e) {
        $cost = osParseNum($e['cost']);
        if ($cost === null || $cost <= 0) continue;
        $bySku[strtolower($sku)] = round($cost, 4);
        $ean = trim((string) ($e['ean'] ?? ''));
        if ($ean !== '') $byEan[$ean] = round($cost, 4);
    }
    return [$bySku, $byEan];
}

/** Web Shopify → [skuLower => products_price NETO]. Fetch fresco; fallback a cache. */
function osLoadWebPrices(&$srcMsg) {
    $srcMsg = '';
    $catalog = [];
    $page = 1;
    while ($page <= 40) {
        $ch = curl_init(SHOP_BASE . '/products.json?limit=250&page=' . $page);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_USERAGENT => osBrowserUA(),
            CURLOPT_TIMEOUT => 40, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); unset($ch);
        if ($resp === false || $code !== 200) break;
        $j = json_decode($resp, true);
        if (empty($j['products'])) break;
        foreach ($j['products'] as $p) $catalog[] = $p;
        if (count($j['products']) < 250) break;
        $page++;
    }
    if (!empty($catalog)) {
        if (!is_dir(OS_CACHE_DIR)) @mkdir(OS_CACHE_DIR, 0775, true);
        @file_put_contents(OS_CACHE, json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $srcMsg = 'web (' . count($catalog) . ' productos)';
    } elseif (file_exists(OS_CACHE)) {
        $catalog = json_decode((string) file_get_contents(OS_CACHE), true) ?: [];
        $srcMsg = 'cache (fallo web, ' . date('Y-m-d H:i', filemtime(OS_CACHE)) . ')';
    }
    $priceBySku = [];
    foreach ($catalog as $p) {
        foreach (($p['variants'] ?? []) as $v) {
            $sku = strtolower(trim((string) ($v['sku'] ?? '')));
            $gross = (float) ($v['price'] ?? 0);
            if ($sku === '' || $gross <= 0) continue;
            $priceBySku[$sku] = priceNetFromWebGross($gross);
        }
    }
    return $priceBySku;
}

/** Match: precio por SKU (web), coste por SKU o EAN (xlsx). Requiere AMBOS. */
function osMatch($costBySku, $costByEan, $priceBySku, array $eanCands, array $skuCands) {
    $price = null; $cost = null;
    foreach ($skuCands as $s) {
        $s = strtolower(trim((string) $s)); if ($s === '') continue;
        if ($price === null && isset($priceBySku[$s])) $price = $priceBySku[$s];
        if ($cost  === null && isset($costBySku[$s]))  $cost  = $costBySku[$s];
    }
    if ($cost === null) foreach ($eanCands as $e) { $e = trim((string) $e); if ($e !== '' && isset($costByEan[$e])) { $cost = $costByEan[$e]; break; } }
    if ($price === null || $cost === null) return null;
    return ['cost' => $cost, 'price' => $price];
}

if ($isAction) {
    @header('X-Accel-Buffering: no');
    @header('Content-Type: text/html; charset=utf-8');
    while (ob_get_level() > 0) @ob_end_flush();
    @ob_implicit_flush(true);
    if (session_status() === PHP_SESSION_ACTIVE) @session_write_close();
}
?>
<?php require THEME . 'html/header.php'; ?>
<table style="width:100%;"><tr><td><div style="padding:20px;">
<?php if ($isAction): ?>
    <h2>Actualizador precios Oceansouth — <?php echo $dryRun ? 'PLAN (dry-run, sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
    <p><a href="<?php echo tep_href_link('Actualizador_precios_oceansouth.php'); ?>" class="xbutton small hv9">← Volver</a></p>
    <div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

logMsg("Modo: " . ($dryRun ? "PLAN (dry-run)" : "EXECUTE")
    . " | scope=" . ($noStock ? "SIN STOCK (qty<=0)" : "TODOS")
    . " | tope cambio=" . ($applyExtremes ? "OFF (aplicando también extremos)" : ("|Δ| ≤ " . rtrim(rtrim(number_format($maxChangePct, 1, '.', ''), '0'), '.') . "%"))
    . ($max > 0 ? " | max=$max cambios" : ""));

$xlsx = findNewestXlsx(OS_DIR);
if (!$xlsx) { logMsg("ERROR: no hay xlsx en " . OS_DIR); goto end_action; }
logMsg("xlsx: " . basename($xlsx) . " (" . round(filesize($xlsx)/1024) . " KB, mtime " . date('Y-m-d H:i', filemtime($xlsx)) . ")");
logMsg("Cargando costes del xlsx (C=EUR NET, salta 'TBD')…");
list($costBySku, $costByEan) = osLoadXlsxCost($xlsx);
logMsg("Costes: " . count($costBySku) . " por SKU / " . count($costByEan) . " por EAN");

$srcMsg = '';
$priceBySku = osLoadWebPrices($srcMsg);
logMsg("PVP web: " . count($priceBySku) . " SKUs con precio [$srcMsg]");
if (empty($priceBySku)) { logMsg("ERROR: no se pudo obtener PVP de la web ni cache."); goto end_action; }

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');

logMsg("Leyendo productos Oceansouth en BD…");
$prods = [];
$stockCond = $noStock ? " AND p.products_quantity <= 0" : "";
$r = $mysqli->query("SELECT p.products_id, p.products_model, p.reference_prov, p.product_ean, p.products_price, p.products_cost FROM products p WHERE " . SCOPE_SQL . $stockCond);
while ($row = $r->fetch_assoc()) $prods[(int) $row['products_id']] = $row;
logMsg("Productos Oceansouth en BD: " . count($prods));
if (empty($prods)) { logMsg("Nada que hacer."); goto end_action; }

$ids = implode(',', array_keys($prods));
$names = [];
$r = $mysqli->query("SELECT products_id, products_name FROM products_description WHERE language_id=3 AND products_id IN ($ids)");
while ($row = $r->fetch_assoc()) $names[(int) $row['products_id']] = $row['products_name'];
$nm = function ($pid) use (&$names) { return mb_substr((string)($names[$pid] ?? ''), 0, 45, 'UTF-8'); };
$g1Cur = [];
$r = $mysqli->query("SELECT products_id, customers_group_price FROM products_groups WHERE customers_group_id=" . G1_GROUP_ID . " AND products_id IN ($ids)");
while ($row = $r->fetch_assoc()) $g1Cur[(int) $row['products_id']] = (float) $row['customers_group_price'];

$attrsByProd = [];
$r = $mysqli->query("SELECT products_attributes_id, products_id, options_values_id, reference, reference_prov, products_attributes_ean, options_values_price, price_prefix FROM products_attributes WHERE products_id IN ($ids)");
while ($row = $r->fetch_assoc()) $attrsByProd[(int) $row['products_id']][] = $row;

$paIds = [];
foreach ($attrsByProd as $arr) foreach ($arr as $a) $paIds[] = (int) $a['products_attributes_id'];
$g1AttrCur = [];
if (!empty($paIds)) {
    $paIn = implode(',', $paIds);
    $r = $mysqli->query("SELECT products_attributes_id, options_values_price, price_prefix FROM products_attributes_groups WHERE customers_group_id=" . G1_GROUP_ID . " AND products_attributes_id IN ($paIn)");
    while ($row = $r->fetch_assoc()) $g1AttrCur[(int) $row['products_attributes_id']] = $row;
}
logMsg("  → con variantes: " . count(array_filter($attrsByProd, fn($a) => !empty($a))) . " productos / " . count($paIds) . " atributos");

$updPriceMain = []; $updCostMain = []; $updG1Main = []; $insG1Main = [];
$updAttrPrice = []; $updAttrG1 = []; $insAttrG1 = [];
$extremesProds = []; $noMatch = []; $processed = 0;

foreach ($prods as $pid => $p) {
    $variants = $attrsByProd[$pid] ?? [];

    if (empty($variants)) {
        // ── Producto SUELTO ──
        $entry = osMatch($costBySku, $costByEan, $priceBySku, [$p['product_ean']], [$p['products_model'], $p['reference_prov']]);
        if ($entry === null) { $noMatch[] = ['pid'=>$pid, 'ref'=>$p['products_model']]; continue; }
        $processed++;
        $newCost = $entry['cost']; $newPrice = $entry['price'];
        $newG1   = roundToNickel(calcG1Price($newPrice, $newCost));
        $curPrice = (float) $p['products_price']; $curCost = (float) $p['products_cost'];
        $dP = priceDeltaPct($curPrice, $newPrice); $dC = priceDeltaPct($curCost, $newCost);
        if (!$applyExtremes && $maxChangeRatio > 0 && (max($dP, $dC) > $maxChangeRatio)) {
            $extremesProds[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'why'=>'suelto', 'oldP'=>$curPrice, 'newP'=>$newPrice, 'oldC'=>$curCost, 'newC'=>$newCost]; continue;
        }
        if ($dP > PRICE_THRESHOLD) $updPriceMain[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'old'=>$curPrice, 'new'=>$newPrice];
        if ($dC > PRICE_THRESHOLD) $updCostMain[]  = ['pid'=>$pid, 'ref'=>$p['products_model'], 'old'=>$curCost,  'new'=>$newCost];
        if (isset($g1Cur[$pid])) { if (priceDeltaPct($g1Cur[$pid], $newG1) > PRICE_THRESHOLD) $updG1Main[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'old'=>$g1Cur[$pid], 'new'=>$newG1]; }
        else $insG1Main[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'new'=>$newG1];
        continue;
    }

    // ── Producto CON VARIANTES ──
    $variantPrices = []; $missing = [];
    foreach ($variants as $a) {
        $entry = osMatch($costBySku, $costByEan, $priceBySku, [$a['products_attributes_ean']], [$a['reference'], $a['reference_prov']]);
        if ($entry === null) $missing[] = ($a['reference'] ?: $a['products_attributes_ean']);
        else $variantPrices[(int) $a['products_attributes_id']] = ['cost'=>$entry['cost'], 'price'=>$entry['price'], 'g1'=>roundToNickel(calcG1Price($entry['price'], $entry['cost']))];
    }
    if (!empty($missing)) { $noMatch[] = ['pid'=>$pid, 'ref'=>$p['products_model'] . ' (variante ' . implode(',', array_slice($missing,0,3)) . ' sin match)']; continue; }
    $processed++;

    $cheapestPa = null; $cheapestPrice = PHP_FLOAT_MAX;
    foreach ($variantPrices as $paId => $vp) { if ($vp['price'] < $cheapestPrice) { $cheapestPrice = $vp['price']; $cheapestPa = $paId; } }
    $mainNew = $variantPrices[$cheapestPa];
    $newCost = $mainNew['cost']; $newPrice = $mainNew['price']; $newG1Main = $mainNew['g1'];
    $curPrice = (float) $p['products_price']; $curCost = (float) $p['products_cost'];
    $dP = priceDeltaPct($curPrice, $newPrice); $dC = priceDeltaPct($curCost, $newCost);
    if (!$applyExtremes && $maxChangeRatio > 0 && (max($dP, $dC) > $maxChangeRatio)) {
        $extremesProds[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'why'=>'con variantes ('.count($variants).')', 'oldP'=>$curPrice, 'newP'=>$newPrice, 'oldC'=>$curCost, 'newC'=>$newCost]; continue;
    }
    if ($dP > PRICE_THRESHOLD) $updPriceMain[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'old'=>$curPrice, 'new'=>$newPrice];
    if ($dC > PRICE_THRESHOLD) $updCostMain[]  = ['pid'=>$pid, 'ref'=>$p['products_model'], 'old'=>$curCost,  'new'=>$newCost];
    if (isset($g1Cur[$pid])) { if (priceDeltaPct($g1Cur[$pid], $newG1Main) > PRICE_THRESHOLD) $updG1Main[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'old'=>$g1Cur[$pid], 'new'=>$newG1Main]; }
    else $insG1Main[] = ['pid'=>$pid, 'ref'=>$p['products_model'], 'new'=>$newG1Main];

    foreach ($variants as $a) {
        $paId = (int) $a['products_attributes_id'];
        if (!isset($variantPrices[$paId])) continue;
        $vp = $variantPrices[$paId];
        $delta = round($vp['price'] - $newPrice, 4); $prefix = $delta < 0 ? '-' : '+'; $absDelta = abs($delta);
        $curAbs = (float) $a['options_values_price']; $curPref = $a['price_prefix'] ?: '+';
        $signedNew = ($prefix === '-' ? -$absDelta : $absDelta); $signedCur = ($curPref === '-' ? -$curAbs : $curAbs);
        if (priceDeltaPct($signedCur, $signedNew) > PRICE_THRESHOLD || ($absDelta > 0 && $curAbs == 0) || ($curPref !== $prefix && $absDelta > 0.0001))
            $updAttrPrice[] = ['paid'=>$paId, 'pid'=>$pid, 'ref'=>$a['reference'], 'absOld'=>$curAbs, 'prefOld'=>$curPref, 'absNew'=>$absDelta, 'prefNew'=>$prefix];
        $g1Delta = round($vp['g1'] - $newG1Main, 4); $g1Prefix = $g1Delta < 0 ? '-' : '+'; $g1Abs = abs($g1Delta);
        if (isset($g1AttrCur[$paId])) {
            $curG1Abs = (float) $g1AttrCur[$paId]['options_values_price']; $curG1Pref = $g1AttrCur[$paId]['price_prefix'] ?: '+';
            $signedNewG1 = ($g1Prefix === '-' ? -$g1Abs : $g1Abs); $signedCurG1 = ($curG1Pref === '-' ? -$curG1Abs : $curG1Abs);
            if (priceDeltaPct($signedCurG1, $signedNewG1) > PRICE_THRESHOLD || ($g1Abs > 0 && $curG1Abs == 0) || ($curG1Pref !== $g1Prefix && $g1Abs > 0.0001))
                $updAttrG1[] = ['paid'=>$paId, 'pid'=>$pid, 'absNew'=>$g1Abs, 'prefNew'=>$g1Prefix];
        } else $insAttrG1[] = ['paid'=>$paId, 'pid'=>$pid, 'absNew'=>$g1Abs, 'prefNew'=>$g1Prefix];
    }
}

// ───────────────────── Specials a borrar (V3: solo repreciados en este run cuando !applyExtremes) ─────────────────────
// Política V3 (2026-07-17): solo ofertas de productos cuyo PVP cambia en ESTE run.
// (La V2 borraba todas las del scope — incidente Osculati 2026-07-16, 383 ofertas
// purgadas de 15+ marcas y restauradas desde backup.)
$badSpecials = [];
if (!$applyExtremes && !empty($updPriceMain)) {
    $effPrice = [];
    foreach ($updPriceMain as $u) $effPrice[(int)$u['pid']] = (float) $u['new'];
    $idsRepriced = implode(',', array_map('intval', array_keys($effPrice)));

    $rs = $mysqli->query("SELECT specials_id, products_id, specials_new_products_price, specials_date_added, expires_date, expires_repeat FROM specials WHERE status=1 AND products_id IN ($idsRepriced)");
    if (!$rs) { logMsg("ERROR SELECT specials: " . $mysqli->error); goto end_action; }
    while ($s = $rs->fetch_assoc()) {
        $pid = (int) $s['products_id'];
        $eff = (float) ($effPrice[$pid] ?? 0);
        $sp  = (float) $s['specials_new_products_price'];
        $dtoPct = $eff > 0 ? (($eff - $sp) / $eff) * 100 : 0.0;
        $badSpecials[] = [
            'specials_id' => (int) $s['specials_id'],
            'pid' => $pid,
            'ref' => $prods[$pid]['products_model'] ?? '?',
            'eff_price' => $eff,
            'sp_price'  => $sp,
            'dto_pct'   => $dtoPct,
            'reason'    => ($sp > $eff) ? 'NEGATIVO (special > PVP nuevo)' : (sprintf('dto %.1f%%', $dtoPct) . ' sobre PVP nuevo — PVP repreciado en este run'),
            'created'   => substr((string)$s['specials_date_added'], 0, 10),
            'expires'   => substr((string)$s['expires_date'], 0, 10),
        ];
    }
}

logMsg("==================== PLAN ====================");
logMsg("Procesados: $processed");
logMsg("UPDATE products.products_price : " . count($updPriceMain));
logMsg("UPDATE products.products_cost  : " . count($updCostMain));
logMsg("UPDATE products_groups (G1)    : " . count($updG1Main) . " | INSERT G1: " . count($insG1Main));
logMsg("UPDATE variantes price : " . count($updAttrPrice) . " | UPDATE G1 var: " . count($updAttrG1) . " | INSERT G1 var: " . count($insAttrG1));
if (!$applyExtremes && $maxChangeRatio > 0) logMsg("⚠️  Productos extremos > {$maxChangePct}% EXCLUIDOS: " . count($extremesProds) . " (padre + variantes no se tocan)");
if (!$applyExtremes) logMsg("🗑️  Specials a BORRAR (solo de productos repreciados en este run) : " . count($badSpecials) . (empty($badSpecials)?" (ninguno)":""));
logMsg("Sin match (web+xlsx) : " . count($noMatch));

$showLimit = 25;
if ($onlyExtremes) logMsg("** Modo SOLO EXTREMOS: se omiten las listas de cambios y de sin-match **");
if (!$onlyExtremes) {
    foreach ([['UPDATE price principal', $updPriceMain], ['UPDATE cost principal', $updCostMain], ['INSERT G1 principal', $insG1Main], ['UPDATE G1 principal', $updG1Main]] as [$title, $arr]) {
        if (empty($arr)) continue;
        logMsg("--- $title (top $showLimit) ---");
        foreach (array_slice($arr, 0, $showLimit) as $u) {
            if (isset($u['old'])) { $pct = priceDeltaPct($u['old'], $u['new']) * 100; logMsg(sprintf("  pid=%d ref=%s [%s] : %.4f → %.4f (%s%.1f%%)", $u['pid'], $u['ref'], $nm($u['pid']), $u['old'], $u['new'], $u['new']>=$u['old']?'+':'-', $pct)); }
            else logMsg(sprintf("  pid=%d ref=%s [%s] : (sin G1) → %.4f", $u['pid'], $u['ref'], $nm($u['pid']), $u['new']));
        }
        if (count($arr) > $showLimit) logMsg("  …y " . (count($arr) - $showLimit) . " más");
    }
}
if (!empty($extremesProds)) {
    logMsg("--- ⚠️ EXTREMOS excluidos (TODOS: " . count($extremesProds) . ", >{$maxChangePct}%, NO se tocan) — posible pack-vs-unidad o error ---");
    foreach ($extremesProds as $u) { $pctP = priceDeltaPct($u['oldP'], $u['newP']) * 100; $pctC = priceDeltaPct($u['oldC'], $u['newC']) * 100; logMsg(sprintf("  pid=%d ref=%s [%s] (%s): price %.4f→%.4f (%.1f%%) cost %.4f→%.4f (%.1f%%)", $u['pid'], $u['ref'], $nm($u['pid']), $u['why'], $u['oldP'], $u['newP'], $pctP, $u['oldC'], $u['newC'], $pctC)); }
}
if (!$onlyExtremes && !empty($noMatch)) {
    logMsg("--- Sin match en web+xlsx (TODOS: " . count($noMatch) . ", no se tocan) ---");
    foreach ($noMatch as $u) logMsg(sprintf("  pid=%d ref=%s [%s]", $u['pid'], $u['ref'], $nm($u['pid'])));
}
if (!empty($badSpecials)) {
    logMsg(sprintf("--- 🗑️ Specials a BORRAR (repreciados en este run: %d) ---", count($badSpecials)));
    foreach ($badSpecials as $b) {
        logMsg(sprintf("  specials_id=%d pid=%d ref=%-14s PVP=%7.2f sp=%7.2f dto=%5.1f%% creado=%s expira=%s — %s",
            $b['specials_id'], $b['pid'], $b['ref'], $b['eff_price']*1.21, $b['sp_price']*1.21, $b['dto_pct'], $b['created'], $b['expires'], $b['reason']));
    }
}

if ($dryRun) { logMsg("=== Dry-run finalizado. No se ha tocado nada. ==="); goto end_action; }

// max: trunca proporcionalmente el total de cambios (price + cost + g1 + variantes)
if ($max > 0) {
    $budget = $max;
    foreach (['updPriceMain','updCostMain','updG1Main','insG1Main','updAttrPrice','updAttrG1','insAttrG1'] as $bucket) {
        if ($budget <= 0) { $$bucket = []; continue; }
        if (count($$bucket) > $budget) $$bucket = array_slice($$bucket, 0, $budget);
        $budget -= count($$bucket);
    }
    logMsg("Truncado a max=$max cambios.");
}

logMsg("Aplicando cambios en transacción única…");
$mysqli->begin_transaction();
try {
    foreach ($updPriceMain as $u) { if (!$mysqli->query("UPDATE products SET products_price=" . fmt4($u['new']) . ", products_last_modified=NOW() WHERE products_id=" . (int) $u['pid'])) throw new Exception("price pid=" . $u['pid'] . ": " . $mysqli->error); }
    foreach ($updCostMain as $u) { if (!$mysqli->query("UPDATE products SET products_cost=" . fmt4($u['new']) . ", products_last_modified=NOW() WHERE products_id=" . (int) $u['pid'])) throw new Exception("cost pid=" . $u['pid'] . ": " . $mysqli->error); }
    foreach ($updG1Main as $u) { if (!$mysqli->query("UPDATE products_groups SET customers_group_price=" . fmt4($u['new']) . " WHERE products_id=" . (int) $u['pid'] . " AND customers_group_id=" . G1_GROUP_ID)) throw new Exception("g1 pid=" . $u['pid'] . ": " . $mysqli->error); }
    foreach ($insG1Main as $u) { if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", " . (int) $u['pid'] . ", " . fmt4($u['new']) . ", 1, 1)")) throw new Exception("ins g1 pid=" . $u['pid'] . ": " . $mysqli->error); }
    foreach ($updAttrPrice as $u) { if (!$mysqli->query("UPDATE products_attributes SET options_values_price=" . fmt4($u['absNew']) . ", price_prefix='" . $u['prefNew'] . "' WHERE products_attributes_id=" . (int) $u['paid'])) throw new Exception("attr paid=" . $u['paid'] . ": " . $mysqli->error); }
    foreach ($updAttrG1 as $u) { if (!$mysqli->query("UPDATE products_attributes_groups SET options_values_price=" . fmt4($u['absNew']) . ", price_prefix='" . $u['prefNew'] . "' WHERE products_attributes_id=" . (int) $u['paid'] . " AND customers_group_id=" . G1_GROUP_ID)) throw new Exception("attr g1 paid=" . $u['paid'] . ": " . $mysqli->error); }
    foreach ($insAttrG1 as $u) { if (!$mysqli->query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix) VALUES (" . (int) $u['paid'] . ", " . G1_GROUP_ID . ", " . fmt4($u['absNew']) . ", '" . $u['prefNew'] . "', " . (int) $u['pid'] . ", 0, '+')")) throw new Exception("ins attr g1 paid=" . $u['paid'] . ": " . $mysqli->error); }
    if (!empty($badSpecials)) {
        $bakDir = '/home/francobordo/backups';
        @mkdir($bakDir, 0755, true);
        $bakPath = $bakDir . '/oceansouth_specials_purge_' . date('Ymd_His') . '.sql';
        $freeBytes = @disk_free_space($bakDir);
        if ($freeBytes !== false && $freeBytes < 100 * 1024 * 1024) {
            logMsg("WARN: poco espacio en $bakDir (" . round(($freeBytes ?: 0) / 1024 / 1024) . "MB libres, mínimo 100MB) — abortando DELETE de specials.");
            throw new Exception("disco insuficiente para backup specials");
        }
        $fh = @fopen($bakPath, 'w');
        if ($fh) {
            fwrite($fh, "-- Backup specials borrados por Actualizador_precios_oceansouth.php " . date('Y-m-d H:i:s') . "\n");
            fwrite($fh, "-- Política V3: !apply_extremes ⇒ borrar ofertas de productos repreciados en este run. Total: " . count($badSpecials) . " filas.\n\n");
            $idList = implode(',', array_map(fn($b) => (int) $b['specials_id'], $badSpecials));
            $rb = $mysqli->query("SELECT * FROM specials WHERE specials_id IN ($idList)");
            if ($rb) while ($srow = $rb->fetch_assoc()) {
                $cols = array_keys($srow);
                $vals = array_map(function ($v) use ($mysqli) {
                    if ($v === null) return 'NULL';
                    return "'" . $mysqli->real_escape_string((string) $v) . "'";
                }, array_values($srow));
                fwrite($fh, "INSERT INTO specials (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n");
            }
            fclose($fh);
            $bakSize = @filesize($bakPath);
            if ($bakSize === false || $bakSize < 100) {
                @unlink($bakPath);
                logMsg("WARN: backup escrito vacío/truncado ($bakSize bytes) — abortando DELETE de specials.");
                throw new Exception("backup specials truncado o vacío");
            }
            logMsg("Backup specials borrados: $bakPath ($bakSize bytes)");
        } else {
            logMsg("WARN: no pude crear backup en $bakDir — abortando DELETE de specials por seguridad.");
            throw new Exception("backup specials no escribible");
        }
        $idList = implode(',', array_map(fn($b) => (int) $b['specials_id'], $badSpecials));
        if (!$mysqli->query("DELETE FROM specials WHERE specials_id IN ($idList)"))
            throw new Exception("delete specials: " . $mysqli->error);
        logMsg("Specials borrados: " . $mysqli->affected_rows);
        $pidsBumpList = implode(',', array_unique(array_map(fn($b)=>(int)$b['pid'], $badSpecials)));
        $mysqli->query("UPDATE products SET products_last_modified=NOW() WHERE products_id IN ($pidsBumpList)");
    }
    $mysqli->commit();
    logMsg("=== COMMIT OK ===");
} catch (Exception $e) { $mysqli->rollback(); logMsg("=== ROLLBACK por error: " . $e->getMessage() . " ==="); }

end_action:
?>
    </div>
    <p style="margin-top:15px;"><a href="<?php echo tep_href_link('Actualizador_precios_oceansouth.php'); ?>" class="xbutton small hv9">← Volver</a></p>
<?php else: ?>
    <h2>Actualizador de precios — Oceansouth</h2>
    <?php $xlsx = findNewestXlsx(OS_DIR); if (!$xlsx) echo '<p style="color:red;">No hay xlsx en ' . OS_DIR . '</p>'; else echo '<p style="color:#666;font-size:13px;">xlsx: <code>' . htmlspecialchars(basename($xlsx)) . '</code> (' . round(filesize($xlsx)/1024) . ' KB, mtime ' . date('Y-m-d H:i', filemtime($xlsx)) . ')</p>'; ?>
    <p>
        Recalcula precios de los productos Oceansouth (<code>origin oceansouth%</code> o manufacturer <?php echo MFG_ID; ?>):
        <strong>coste</strong> ← col C del xlsx (EUR NET), <strong>PVP</strong> ← Shopify <code>oceansouth.com/en-eu</code>
        (se descarga fresco cada vez), enlazado por <strong>SKU</strong> (coste también por EAN).
        Maneja productos con variantes (la más barata define el precio del padre y el resto el delta).
        Aplica solo cuando la diferencia &gt; <strong><?php echo PRICE_THRESHOLD * 100; ?>%</strong>. Stock NO se toca.
    </p>
    <form method="post" style="background:#f5f5f5;padding:15px;border-radius:5px;">
        <p><strong>Ámbito</strong>:
            <label><input type="radio" name="scope" value="all" checked> Todos los Oceansouth</label> &nbsp;
            <label><input type="radio" name="scope" value="no_stock"> Solo sin stock (qty ≤ 0)</label>
        </p>
        <p><strong>Tope de variación</strong>: <label>excluir cambios &gt; <input type="number" name="max_change_pct" value="<?php echo (int) MAX_CHANGE_PCT_DEF; ?>" min="0" max="500" step="1" style="width:60px;"> %</label>
            <small style="color:#888;display:block;margin-top:4px;">Si el price o cost del producto (padre, si hay variantes) cambia más de este %, se excluye el producto entero. 0 = sin tope. Protege contra pack-vs-unidad o errores.</small></p>
        <p><label><input type="checkbox" name="apply_extremes" value="1"> Aplicar también los extremos (desactiva el tope)</label></p>
        <p><label><input type="checkbox" name="only_extremes" value="1"> <strong>Ver SOLO los productos saltados por extremos</strong> (oculta el resto del plan)</label></p>
        <p><label>Cambios máximos por ejecución (0 = sin límite): <input type="number" name="max" value="0" min="0" style="width:80px;"></label></p>
        <p><label><input type="checkbox" name="confirm_execute" value="1"> Aplicar cambios (sin marcar = solo PLAN/dry-run)</label></p>
        <input type="hidden" name="action" value="plan">
        <button type="submit" name="action" value="plan" class="xbutton small hv9">Generar plan (dry-run)</button>
        <button type="submit" name="action" value="execute" class="xbutton small hv9 verde" onclick="return this.form.confirm_execute.checked || (alert('Marca la casilla Aplicar cambios antes de ejecutar.'), false);">Ejecutar</button>
    </form>
    <p style="margin-top:20px;color:#888;font-size:12px;">
        <strong>Reglas</strong> (idénticas al importador Oceansouth):<br>
        - <code>products_cost</code> ← col C del xlsx (EUR NET). Celdas <code>TBD</code>/vacías se ignoran.<br>
        - <code>products_price</code> ← <code>roundToNickel(PVP_web / 1,21)</code> (PVP web IVA incluido).<br>
        - <strong>Grupo 1</strong>: tiers según margen (≥45% ×0.75, 40 ×0.80, 35 ×0.82, 30 ×0.85, &lt;30% ×0.90) + piso cost×<?php echo G1_FLOOR_FACTOR; ?>.<br>
        - Match por <strong>SKU</strong> (modelo/ref ↔ Item No. del xlsx y variant.sku de la web); coste también por EAN. Requiere precio web Y coste xlsx.<br>
        - Variantes: padre = más barata, resto delta. Sin match (p.ej. descatalogado en web) → se lista, no se toca. Stock NO se toca.
    </p>
<?php endif; ?>
</div></td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
