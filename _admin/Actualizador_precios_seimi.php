<?php
require 'includes/application_top.php';
require_once DIR_FS_DOCUMENT_ROOT . 'includes/vendor/autoload.php';
use phpseclib3\Net\SFTP;

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

/* ──────────────────────────────────────────────────────────────────────────
 * Actualizador de precios SEIMI (Alliance Marine)
 *
 * Fuente ÚNICA = CSV del SFTP de Alliance Marine (el mismo que el importador):
 *   /import/feed/SEIMI/SEIMI_products_francobordo.csv — pipe |, UTF-8, con cabecera.
 *   Se re-descarga FRESCO en cada run (checkbox para usar la copia local).
 *   - products_price = roundToNickel(Public_price_ex_VAT || cost×2) · products_cost = Net_price_ex_VAT · G1 = tiers+piso.
 *
 * Recalcula products_price / products_cost / G1. MANEJA VARIANTES (padre = la más
 * barata, resto deltas + deltas G1). Stock NO se toca. Specials: solo AVISO (no borra).
 * Scope: SOLO products_import_origin 'seimi%' (multi-marca: las marcas de SEIMI se comparten
 * con otros proveedores — NUNCA filtrar por manufacturers_id).
 * ────────────────────────────────────────────────────────────────────────── */

const SEIMI_CSV          = '/home/francobordo/public_html/import/feed/SEIMI/SEIMI_products_francobordo.csv';
const SEIMI_SFTP_HOST    = 'stftpamgprd01.blob.core.windows.net';
const SEIMI_SFTP_PORT    = 22;
const SEIMI_SFTP_USER    = 'stftpamgprd01.amghubfrancobordo';
const SEIMI_SFTP_PASS    = 'lkYgzugus0nD0bQi/smYJLzSMY2RyFBS';
const SEIMI_SFTP_REMOTE  = 'products/SEIMI_products_francobordo.csv';
const G1_GROUP_ID      = 1;
const G1_FLOOR_FACTOR  = 1.10;
const PRICE_THRESHOLD  = 0.005;          // 0.5%
const MAX_CHANGE_PCT_DEF = 30;           // tope superior default (configurable)
const IVA_ES           = 1.21;
const ORIGIN_FLAG      = 'seimi';

function roundToNickel($net) { $wi = ((float) $net) * IVA_ES; $r = round($wi * 20) / 20; return round($r / IVA_ES, 4); }
function ean13Checksum($p) { if (strlen($p) !== 12 || !ctype_digit($p)) return -1; $s = 0; for ($i = 0; $i < 12; $i++) { $d = (int) $p[$i]; $s += ($i % 2 === 0) ? $d : $d * 3; } return (10 - ($s % 10)) % 10; }
function isValidEan13($e) { $e = trim((string) $e); return strlen($e) === 13 && ctype_digit($e) && ean13Checksum(substr($e, 0, 12)) === (int) $e[12]; }
function seimiNormalizeEan($raw) { $e = preg_replace('/\D/', '', trim((string) $raw)); if (strlen($e) === 12) $e = '0' . $e; elseif (strlen($e) === 14 && $e[0] === '0') $e = substr($e, 1); return isValidEan13($e) ? $e : ''; }
function seimiParseNum($v) { $v = trim((string) $v); if ($v === '') return null; if (strpos($v, ',') !== false && strpos($v, '.') === false) $v = str_replace(',', '.', $v); $v = str_replace(' ', '', $v); return is_numeric($v) ? (float) $v : null; }

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$confirmExec = isset($_POST['confirm_execute']) || isset($_GET['confirm_execute']);
$dryRun = !($action === 'execute' && $confirmExec);
$applyExtremes = isset($_POST['apply_extremes']) || isset($_GET['apply_extremes']);
$onlyExtremes  = isset($_POST['only_extremes']) || isset($_GET['only_extremes']);
$noStock       = (($_POST['scope'] ?? $_GET['scope'] ?? 'all') === 'no_stock');
$skipDownload  = isset($_POST['skip_download']) || isset($_GET['skip_download']);
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

function downloadFeedFromSftp($localPath) {
    try {
        $dir = dirname($localPath);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $sftp = new SFTP(SEIMI_SFTP_HOST, SEIMI_SFTP_PORT, 30);
        if (!$sftp->login(SEIMI_SFTP_USER, SEIMI_SFTP_PASS)) return ['ok' => false, 'err' => 'login fallido'];
        $tmp = $localPath . '.tmp.' . uniqid();
        if (!$sftp->get(SEIMI_SFTP_REMOTE, $tmp)) return ['ok' => false, 'err' => 'get fallido'];
        if (!file_exists($tmp) || filesize($tmp) < 1000000) { @unlink($tmp); return ['ok' => false, 'err' => 'fichero descargado vacío/inválido']; }
        if (!@rename($tmp, $localPath)) { @unlink($tmp); return ['ok' => false, 'err' => 'rename fallido']; }
        return ['ok' => true, 'size' => filesize($localPath)];
    } catch (Exception $e) {
        return ['ok' => false, 'err' => $e->getMessage()];
    }
}

/** CSV → [bySku (SKU y supplier_item_code), byEan] con ['price','cost'] ya calculados.
 *  Incluye Active y End of life (mientras SEIMI publique precio, se actualiza). */
function seimiBuildMaps($path, &$rowsTotal = 0, &$rowsPriced = 0) {
    $f = fopen($path, 'r');
    if (!$f) return [[], []];
    $header = fgetcsv($f, 0, '|', chr(34), '');
    if (!$header) { fclose($f); return [[], []]; }
    if (isset($header[0])) $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    $idx = array_flip(array_map('trim', $header));
    $ncols = count($header);
    $bySku = []; $byEan = [];
    while (($r = fgetcsv($f, 0, '|', chr(34), '')) !== false) {
        if (count($r) !== $ncols) continue;
        $rowsTotal++;
        $sku = trim((string) ($r[$idx['SKU']] ?? ''));
        if ($sku === '') continue;
        $net = seimiParseNum($r[$idx['Net_price_ex_VAT']] ?? '');
        if ($net === null || $net <= 0) continue;
        $pub = seimiParseNum($r[$idx['Public_price_ex_VAT']] ?? '');
        $price = roundToNickel(($pub !== null && $pub > $net) ? $pub : $net * 2.0);
        $rec = ['price' => $price, 'cost' => round($net, 4)];
        $rowsPriced++;
        $bySku[strtolower($sku)] = $rec;
        $sup = trim((string) ($r[$idx['supplier_item_code']] ?? ''));
        if ($sup !== '' && strcasecmp($sup, $sku) !== 0 && !isset($bySku[strtolower($sup)])) $bySku[strtolower($sup)] = $rec;
        $ean = seimiNormalizeEan($r[$idx['Barcode']] ?? '');
        if ($ean !== '' && !isset($byEan[$ean])) $byEan[$ean] = $rec;
    }
    fclose($f);
    return [$bySku, $byEan];
}

/** Match: price+cost por SKU/ref o, si no, por EAN. Ambos vienen de la misma fila. */
function seimiMatch($bySku, $byEan, array $skuCands, array $eanCands) {
    foreach ($skuCands as $s) { $s = strtolower(trim((string) $s)); if ($s !== '' && isset($bySku[$s])) return $bySku[$s]; }
    foreach ($eanCands as $e) { $e = trim((string) $e); if ($e !== '' && isset($byEan[$e])) return $byEan[$e]; }
    return null;
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
    <h2>Actualizador precios SEIMI — <?php echo $dryRun ? 'PLAN (dry-run, sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
    <p><a href="<?php echo tep_href_link('Actualizador_precios_seimi.php'); ?>" class="xbutton small hv9">← Volver</a></p>
    <div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

logMsg("Modo: " . ($dryRun ? "PLAN (dry-run)" : "EXECUTE")
    . " | scope=" . ($noStock ? "SIN STOCK (qty<=0)" : "TODOS")
    . " | tope cambio=" . ($applyExtremes ? "OFF (aplicando también extremos)" : ("|Δ| ≤ " . rtrim(rtrim(number_format($maxChangePct, 1, '.', ''), '0'), '.') . "%"))
    . ($skipDownload ? " | sin descargar feed" : "")
    . ($max > 0 ? " | max=$max cambios" : ""));

if (!$skipDownload) {
    $mtime = file_exists(SEIMI_CSV) ? filemtime(SEIMI_CSV) : 0;
    logMsg("Descargando " . SEIMI_SFTP_REMOTE . " del SFTP…" . ($mtime ? " (local actual: " . date('Y-m-d H:i', $mtime) . ")" : ""));
    $dl = downloadFeedFromSftp(SEIMI_CSV);
    if ($dl['ok']) logMsg("  ✓ descargado: " . round($dl['size']/1048576, 1) . " MB");
    else { logMsg("  ✗ descarga fallida: " . $dl['err'] . " — uso copia local si la hay"); if (!file_exists(SEIMI_CSV)) { logMsg("ERROR: no hay copia local"); goto end_action; } }
}
if (!file_exists(SEIMI_CSV)) { logMsg("ERROR: CSV no encontrado: " . SEIMI_CSV); goto end_action; }
logMsg("CSV: " . basename(SEIMI_CSV) . " (" . round(filesize(SEIMI_CSV)/1048576, 1) . " MB, mtime " . date('Y-m-d H:i', filemtime(SEIMI_CSV)) . ")");

$rowsTotal = 0; $rowsPriced = 0;
list($bySku, $byEan) = seimiBuildMaps(SEIMI_CSV, $rowsTotal, $rowsPriced);
if (empty($bySku)) { logMsg("ERROR: CSV vacío o ilegible"); goto end_action; }
logMsg("Precios del CSV: $rowsPriced filas con precio de $rowsTotal | claves: " . count($bySku) . " por SKU/ref / " . count($byEan) . " por EAN");

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');

// Scope SOLO por origin (multi-marca — jamás por manufacturers_id)
$scopeSql = "p.products_import_origin LIKE '" . ORIGIN_FLAG . "%'";

logMsg("Leyendo productos en BD…");
$prods = [];
$stockCond = $noStock ? " AND p.products_quantity <= 0" : "";
$r = $mysqli->query("SELECT p.products_id, p.products_model, p.reference_prov, p.product_ean, p.products_price, p.products_cost FROM products p WHERE $scopeSql$stockCond");
while ($r && $row = $r->fetch_assoc()) $prods[(int) $row['products_id']] = $row;
logMsg("Productos en scope (origin '" . ORIGIN_FLAG . "%'): " . count($prods));
if (empty($prods)) { logMsg("Nada que hacer."); goto end_action; }

$ids = implode(',', array_keys($prods));
$names = [];
$r = $mysqli->query("SELECT products_id, products_name FROM products_description WHERE language_id=3 AND products_id IN ($ids)");
while ($r && $row = $r->fetch_assoc()) $names[(int) $row['products_id']] = $row['products_name'];
$nm = function ($pid) use (&$names) { return mb_substr((string)($names[$pid] ?? ''), 0, 45, 'UTF-8'); };
$g1Cur = [];
$r = $mysqli->query("SELECT products_id, customers_group_price FROM products_groups WHERE customers_group_id=" . G1_GROUP_ID . " AND products_id IN ($ids)");
while ($r && $row = $r->fetch_assoc()) $g1Cur[(int) $row['products_id']] = (float) $row['customers_group_price'];

$attrsByProd = [];
$r = $mysqli->query("SELECT products_attributes_id, products_id, options_values_id, reference, reference_prov, products_attributes_ean, options_values_price, price_prefix FROM products_attributes WHERE products_id IN ($ids)");
while ($r && $row = $r->fetch_assoc()) $attrsByProd[(int) $row['products_id']][] = $row;

$paIds = [];
foreach ($attrsByProd as $arr) foreach ($arr as $a) $paIds[] = (int) $a['products_attributes_id'];
$g1AttrCur = [];
if (!empty($paIds)) {
    $paIn = implode(',', $paIds);
    $r = $mysqli->query("SELECT products_attributes_id, options_values_price, price_prefix FROM products_attributes_groups WHERE customers_group_id=" . G1_GROUP_ID . " AND products_attributes_id IN ($paIn)");
    while ($r && $row = $r->fetch_assoc()) $g1AttrCur[(int) $row['products_attributes_id']] = $row;
}
logMsg("  → con variantes: " . count(array_filter($attrsByProd, fn($a) => !empty($a))) . " productos / " . count($paIds) . " atributos");

$updPriceMain = []; $updCostMain = []; $updG1Main = []; $insG1Main = [];
$updAttrPrice = []; $updAttrG1 = []; $insAttrG1 = [];
$extremesProds = []; $noMatch = []; $processed = 0;

foreach ($prods as $pid => $p) {
    $variants = $attrsByProd[$pid] ?? [];

    if (empty($variants)) {
        // ── Producto SUELTO ──
        $entry = seimiMatch($bySku, $byEan, [$p['reference_prov'], $p['products_model']], [$p['product_ean']]);
        if ($entry === null) { $noMatch[] = ['pid'=>$pid, 'ref'=>$p['reference_prov'] ?: $p['products_model']]; continue; }
        $processed++;
        $newCost = $entry['cost']; $newPrice = $entry['price'];
        $newG1   = roundToNickel(calcG1Price($newPrice, $newCost));
        $curPrice = (float) $p['products_price']; $curCost = (float) $p['products_cost'];
        $dP = priceDeltaPct($curPrice, $newPrice); $dC = priceDeltaPct($curCost, $newCost);
        if (!$applyExtremes && $maxChangeRatio > 0 && (max($dP, $dC) > $maxChangeRatio)) {
            $extremesProds[] = ['pid'=>$pid, 'ref'=>$p['reference_prov'], 'why'=>'suelto', 'oldP'=>$curPrice, 'newP'=>$newPrice, 'oldC'=>$curCost, 'newC'=>$newCost]; continue;
        }
        if ($dP > PRICE_THRESHOLD) $updPriceMain[] = ['pid'=>$pid, 'ref'=>$p['reference_prov'], 'old'=>$curPrice, 'new'=>$newPrice];
        if ($dC > PRICE_THRESHOLD) $updCostMain[]  = ['pid'=>$pid, 'ref'=>$p['reference_prov'], 'old'=>$curCost,  'new'=>$newCost];
        if (isset($g1Cur[$pid])) { if (priceDeltaPct($g1Cur[$pid], $newG1) > PRICE_THRESHOLD) $updG1Main[] = ['pid'=>$pid, 'ref'=>$p['reference_prov'], 'old'=>$g1Cur[$pid], 'new'=>$newG1]; }
        else $insG1Main[] = ['pid'=>$pid, 'ref'=>$p['reference_prov'], 'new'=>$newG1];
        continue;
    }

    // ── Producto CON VARIANTES ──
    $variantPrices = []; $missing = [];
    foreach ($variants as $a) {
        $entry = seimiMatch($bySku, $byEan, [$a['reference_prov'], $a['reference']], [$a['products_attributes_ean']]);
        if ($entry === null) $missing[] = ($a['reference_prov'] ?: $a['reference']);
        else $variantPrices[(int) $a['products_attributes_id']] = ['cost'=>$entry['cost'], 'price'=>$entry['price'], 'g1'=>roundToNickel(calcG1Price($entry['price'], $entry['cost']))];
    }
    if (!empty($missing)) { $noMatch[] = ['pid'=>$pid, 'ref'=>($p['reference_prov'] ?: $p['products_model']) . ' (variante ' . implode(',', array_slice($missing,0,3)) . ' sin match)']; continue; }
    $processed++;

    $cheapestPa = null; $cheapestPrice = PHP_FLOAT_MAX;
    foreach ($variantPrices as $paId => $vp) { if ($vp['price'] < $cheapestPrice) { $cheapestPrice = $vp['price']; $cheapestPa = $paId; } }
    $mainNew = $variantPrices[$cheapestPa];
    $newCost = $mainNew['cost']; $newPrice = $mainNew['price']; $newG1Main = $mainNew['g1'];
    $curPrice = (float) $p['products_price']; $curCost = (float) $p['products_cost'];
    $dP = priceDeltaPct($curPrice, $newPrice); $dC = priceDeltaPct($curCost, $newCost);
    if (!$applyExtremes && $maxChangeRatio > 0 && (max($dP, $dC) > $maxChangeRatio)) {
        $extremesProds[] = ['pid'=>$pid, 'ref'=>$p['reference_prov'], 'why'=>'con variantes ('.count($variants).')', 'oldP'=>$curPrice, 'newP'=>$newPrice, 'oldC'=>$curCost, 'newC'=>$newCost]; continue;
    }
    if ($dP > PRICE_THRESHOLD) $updPriceMain[] = ['pid'=>$pid, 'ref'=>$p['reference_prov'], 'old'=>$curPrice, 'new'=>$newPrice];
    if ($dC > PRICE_THRESHOLD) $updCostMain[]  = ['pid'=>$pid, 'ref'=>$p['reference_prov'], 'old'=>$curCost,  'new'=>$newCost];
    if (isset($g1Cur[$pid])) { if (priceDeltaPct($g1Cur[$pid], $newG1Main) > PRICE_THRESHOLD) $updG1Main[] = ['pid'=>$pid, 'ref'=>$p['reference_prov'], 'old'=>$g1Cur[$pid], 'new'=>$newG1Main]; }
    else $insG1Main[] = ['pid'=>$pid, 'ref'=>$p['reference_prov'], 'new'=>$newG1Main];

    foreach ($variants as $a) {
        $paId = (int) $a['products_attributes_id'];
        if (!isset($variantPrices[$paId])) continue;
        $vp = $variantPrices[$paId];
        $delta = round($vp['price'] - $newPrice, 4); $prefix = $delta < 0 ? '-' : '+'; $absDelta = abs($delta);
        $curAbs = (float) $a['options_values_price']; $curPref = $a['price_prefix'] ?: '+';
        $signedNew = ($prefix === '-' ? -$absDelta : $absDelta); $signedCur = ($curPref === '-' ? -$curAbs : $curAbs);
        if (priceDeltaPct($signedCur, $signedNew) > PRICE_THRESHOLD || ($absDelta > 0 && $curAbs == 0) || ($curPref !== $prefix && $absDelta > 0.0001))
            $updAttrPrice[] = ['paid'=>$paId, 'pid'=>$pid, 'ref'=>$a['reference_prov'], 'absOld'=>$curAbs, 'prefOld'=>$curPref, 'absNew'=>$absDelta, 'prefNew'=>$prefix];
        $g1Delta = round($vp['g1'] - $newG1Main, 4); $g1Prefix = $g1Delta < 0 ? '-' : '+'; $g1Abs = abs($g1Delta);
        if (isset($g1AttrCur[$paId])) {
            $curG1Abs = (float) $g1AttrCur[$paId]['options_values_price']; $curG1Pref = $g1AttrCur[$paId]['price_prefix'] ?: '+';
            $signedNewG1 = ($g1Prefix === '-' ? -$g1Abs : $g1Abs); $signedCurG1 = ($curG1Pref === '-' ? -$curG1Abs : $curG1Abs);
            if (priceDeltaPct($signedCurG1, $signedNewG1) > PRICE_THRESHOLD || ($g1Abs > 0 && $curG1Abs == 0) || ($curG1Pref !== $g1Prefix && $g1Abs > 0.0001))
                $updAttrG1[] = ['paid'=>$paId, 'pid'=>$pid, 'absNew'=>$g1Abs, 'prefNew'=>$g1Prefix];
        } else $insAttrG1[] = ['paid'=>$paId, 'pid'=>$pid, 'absNew'=>$g1Abs, 'prefNew'=>$g1Prefix];
    }
}

// ── Specials activos que quedarían mal respecto al nuevo PVP → SOLO AVISO (no se borra nada) ──
$warnSpecials = [];
$effPrice = [];
foreach ($prods as $pid => $p) $effPrice[$pid] = (float) $p['products_price'];
foreach ($updPriceMain as $u) $effPrice[(int)$u['pid']] = (float) $u['new'];
$rs = $mysqli->query("SELECT specials_id, products_id, specials_new_products_price FROM specials WHERE status=1 AND products_id IN ($ids)");
while ($rs && $s = $rs->fetch_assoc()) {
    $pid = (int) $s['products_id'];
    $eff = (float) ($effPrice[$pid] ?? 0);
    $sp  = (float) $s['specials_new_products_price'];
    if ($sp >= $eff * (1 - PRICE_THRESHOLD)) {
        $warnSpecials[] = ['specials_id'=>(int)$s['specials_id'], 'pid'=>$pid, 'ref'=>$prods[$pid]['reference_prov'] ?? '?', 'eff'=>$eff, 'sp'=>$sp];
    }
}

logMsg("==================== PLAN ====================");
logMsg("Procesados: $processed");
logMsg("UPDATE products.products_price : " . count($updPriceMain));
logMsg("UPDATE products.products_cost  : " . count($updCostMain));
logMsg("UPDATE products_groups (G1)    : " . count($updG1Main) . " | INSERT G1: " . count($insG1Main));
logMsg("UPDATE variantes price : " . count($updAttrPrice) . " | UPDATE G1 var: " . count($updAttrG1) . " | INSERT G1 var: " . count($insAttrG1));
if (!$applyExtremes && $maxChangeRatio > 0) logMsg("⚠️  Productos extremos > {$maxChangePct}% EXCLUIDOS: " . count($extremesProds) . " (padre + variantes no se tocan)");
logMsg("Sin match en el CSV : " . count($noMatch) . " (no se tocan — el cron de stock los descataloga si desaparecieron del feed)");
if (!empty($warnSpecials)) logMsg("⚠️  Specials activos que quedan POR ENCIMA (o casi) del nuevo PVP: " . count($warnSpecials) . " — revisar a mano, este script NO los toca");

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
    logMsg("--- Sin match en el CSV (TODOS: " . count($noMatch) . ", no se tocan) ---");
    foreach (array_slice($noMatch, 0, 50) as $u) logMsg(sprintf("  pid=%d ref=%s [%s]", $u['pid'], $u['ref'], $nm($u['pid'])));
    if (count($noMatch) > 50) logMsg("  …y " . (count($noMatch) - 50) . " más");
}
if (!empty($warnSpecials)) {
    logMsg("--- ⚠️ Specials a revisar (NO se tocan) ---");
    foreach ($warnSpecials as $b) logMsg(sprintf("  specials_id=%d pid=%d ref=%-14s PVP nuevo=%7.2f€ (c/IVA) vs oferta=%7.2f€", $b['specials_id'], $b['pid'], $b['ref'], $b['eff']*IVA_ES, $b['sp']*IVA_ES));
}

if ($dryRun) { logMsg("=== Dry-run finalizado. No se ha tocado nada. ==="); goto end_action; }

// max: trunca proporcionalmente el total de cambios
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
    $mysqli->commit();
    logMsg("=== COMMIT OK ===");
} catch (Exception $e) { $mysqli->rollback(); logMsg("=== ROLLBACK por error: " . $e->getMessage() . " ==="); }

end_action:
?>
    </div>
    <p style="margin-top:15px;"><a href="<?php echo tep_href_link('Actualizador_precios_seimi.php'); ?>" class="xbutton small hv9">← Volver</a></p>
<?php else: ?>
    <h2>Actualizador de precios — SEIMI (Alliance Marine)</h2>
    <?php if (file_exists(SEIMI_CSV)) echo '<p style="color:#666;font-size:13px;">CSV local: <code>' . htmlspecialchars(basename(SEIMI_CSV)) . '</code> (' . round(filesize(SEIMI_CSV)/1048576, 1) . ' MB, mtime ' . date('Y-m-d H:i', filemtime(SEIMI_CSV)) . ') — se re-descarga del SFTP en cada run salvo que marques lo contrario.</p>'; ?>
    <p>
        Recalcula precios de los productos con <code>origin seimi%</code> desde el CSV de Alliance Marine
        (precios negociados, se regeneran cada medianoche): <strong>PVP</strong> ← <code>roundToNickel(Public_price_ex_VAT)</code>
        (fallback coste×2), <strong>coste</strong> ← <code>Net_price_ex_VAT</code>, enlazado por <strong>SKU SEIMI</strong>
        (reference_prov), referencia de fabricante (products_model) y EAN. <strong>Maneja variantes</strong>
        (la más barata define el precio del padre y el resto el delta). Aplica solo cuando la diferencia
        &gt; <strong><?php echo PRICE_THRESHOLD * 100; ?>%</strong>. Stock NO se toca. Specials NO se tocan (solo aviso).
    </p>
    <form method="post" style="background:#f5f5f5;padding:15px;border-radius:5px;">
        <p><strong>Ámbito</strong>:
            <label><input type="radio" name="scope" value="all" checked> Todos</label> &nbsp;
            <label><input type="radio" name="scope" value="no_stock"> Solo sin stock (qty ≤ 0)</label>
        </p>
        <p><strong>Tope de variación</strong>: <label>excluir cambios &gt; <input type="number" name="max_change_pct" value="<?php echo (int) MAX_CHANGE_PCT_DEF; ?>" min="0" max="500" step="1" style="width:60px;"> %</label>
            <small style="color:#888;display:block;margin-top:4px;">Si el price o cost del producto (padre, si hay variantes) cambia más de este %, se excluye el producto entero. 0 = sin tope. Protege contra pack-vs-unidad o errores.</small></p>
        <p><label><input type="checkbox" name="apply_extremes" value="1"> Aplicar también los extremos (desactiva el tope)</label></p>
        <p><label><input type="checkbox" name="only_extremes" value="1"> <strong>Ver SOLO los productos saltados por extremos</strong> (oculta el resto del plan)</label></p>
        <p><label><input type="checkbox" name="skip_download" value="1"> No descargar el feed del SFTP (usar copia local)</label></p>
        <p><label>Cambios máximos por ejecución (0 = sin límite): <input type="number" name="max" value="0" min="0" style="width:80px;"></label></p>
        <p><label><input type="checkbox" name="confirm_execute" value="1"> Aplicar cambios (sin marcar = solo PLAN/dry-run)</label></p>
        <input type="hidden" name="action" value="plan">
        <button type="submit" name="action" value="plan" class="xbutton small hv9">Generar plan (dry-run)</button>
        <button type="submit" name="action" value="execute" class="xbutton small hv9 verde" onclick="return this.form.confirm_execute.checked || (alert('Marca la casilla Aplicar cambios antes de ejecutar.'), false);">Ejecutar</button>
    </form>
    <p style="margin-top:20px;color:#888;font-size:12px;">
        <strong>Reglas</strong> (idénticas al importador SEIMI):<br>
        - <code>products_price</code> ← <code>roundToNickel(Public_price_ex_VAT)</code>; si público ≤ neto → coste×2.<br>
        - <code>products_cost</code> ← <code>Net_price_ex_VAT</code> (neto negociado sin IVA).<br>
        - <strong>Grupo 1</strong>: tiers según margen (≥45% ×0.75, 40 ×0.80, 35 ×0.82, 30 ×0.85, &lt;30% ×0.90) + piso cost×<?php echo G1_FLOOR_FACTOR; ?>.<br>
        - Match por SKU SEIMI (reference_prov), ref. fabricante (products_model / reference) y EAN. Incluye filas Active y End of life del feed.<br>
        - Variantes: padre = más barata, resto delta + delta G1. Producto/variante sin match → se lista, no se toca.<br>
        - Scope SOLO <code>origin seimi%</code> (las marcas de SEIMI se comparten con otros proveedores). Stock y specials NO se tocan.
    </p>
<?php endif; ?>
</div></td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
