<?php
require 'includes/application_top.php';
require_once DIR_FS_DOCUMENT_ROOT . 'includes/vendor/autoload.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

// ─── Configuración ──────────────────────────────────────────────────────────
const MB_DIR              = '/home/francobordo/public_html/import/MB/';
const MB_MFG_ID           = 47;            // Marine Business
const ORIGIN_FLAG         = 'marinebusiness';
const TAX_RATE            = 1.21;
const G1_GROUP_ID         = 1;
const PRICE_MULT_NET      = 0.95;          // price/Retail
const COST_MULT           = 0.50;          // cost/Retail
const G1_MULT             = 0.70;          // G1/Retail
const PRICE_THRESHOLD     = 0.005;         // 0.5% — mínimo para considerar el cambio
const MAX_CHANGE_PCT_DEF  = 30;            // default del tope (configurable en form)

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$confirmExec = isset($_POST['confirm_execute']) || isset($_GET['confirm_execute']); $dryRun = !($action === 'execute' && $confirmExec); // fix footgun: el botón plan nunca ejecuta
$scope  = $_POST['scope'] ?? $_GET['scope'] ?? 'all'; // 'all' | 'no_stock'
$applyExtremes = isset($_POST['apply_extremes']) || isset($_GET['apply_extremes']);
$onlyExtremes = isset($_POST['only_extremes']) || isset($_GET['only_extremes']); // PLAN: mostrar SOLO los excluidos por extremos
$maxChangePct  = isset($_POST['max_change_pct']) ? (float) $_POST['max_change_pct'] : (isset($_GET['max_change_pct']) ? (float) $_GET['max_change_pct'] : MAX_CHANGE_PCT_DEF);
if ($maxChangePct < 0) $maxChangePct = 0;
$maxChangeRatio = $maxChangePct / 100.0; // 0.30 para 30%
$disableOrphans = isset($_POST['disable_orphans']) || isset($_GET['disable_orphans']);

function logMsg($msg) {
	if (!empty($GLOBALS['onlyExtremes'])) { static $lmSeen = false, $lmShow = true; if (strpos($msg, '====') !== false) { /* banner */ } elseif (strpos($msg, '--- ') !== false) { $lmSeen = true; $lmShow = (stripos($msg, 'EXTREMO') !== false); if (!$lmShow) return; } elseif ($lmSeen && !$lmShow) return; }
    $line = '[' . date('H:i:s') . '] ' . $msg . "\n";
    echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars($line) . '</pre>';
    @flush();
}
function fmt4($n) { return number_format((float) $n, 4, '.', ''); }
function priceDeltaPct($oldP, $newP) {
    $ref = max(abs((float) $oldP), 0.01);
    return abs((float) $newP - (float) $oldP) / $ref;
}

function mbParseNum($v) {
    $v = trim((string) $v);
    if ($v === '') return null;
    $v = str_replace(',', '.', $v);
    return is_numeric($v) ? (float) $v : null;
}

/** Redondea un PVP NETO de modo que el precio CON IVA quede en múltiplo de 0.05. */
function roundToNickel($net) {
    $wi = ((float) $net) * TAX_RATE;
    $r  = round($wi * 20) / 20;
    return round($r / TAX_RATE, 4);
}

function findNewestXlsx($dir) {
    if (!is_dir($dir)) return null;
    $best = null; $bestT = 0;
    foreach (scandir($dir) as $f) {
        if (substr($f, -5) !== '.xlsx') continue;
        if (substr($f, 0, 1) === '~') continue;
        $abs = $dir . $f;
        $t = filemtime($abs);
        if ($t > $bestT) { $bestT = $t; $best = $abs; }
    }
    return $best;
}

/** Carga los precios del xlsx indexado por SKU lowercase.
 *  Sheet "esp" como fuente del Retail (col C); SKU en col A. */
function loadMbXlsxPrices($file) {
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
    $reader->setReadDataOnly(true);
    $ss = $reader->load($file);
    $sheet = $ss->getSheetByName('esp') ?: $ss->getActiveSheet();
    $hi = $sheet->getHighestRow();
    $prices = [];
    for ($r = 2; $r <= $hi; $r++) {
        $sku = trim((string) $sheet->getCell('A' . $r)->getValue());
        if ($sku === '') continue;
        $retail = mbParseNum($sheet->getCell('C' . $r)->getValue());
        if ($retail === null || $retail <= 0) continue;
        $cost  = round($retail * COST_MULT, 4);
        $price = roundToNickel($retail * PRICE_MULT_NET);
        $g1    = roundToNickel($retail * G1_MULT);
        $prices[strtolower($sku)] = [
            'retail' => round($retail, 4),
            'cost'   => $cost,
            'price'  => $price,
            'g1'     => $g1,
        ];
    }
    return $prices;
}

$isAction = ($action === 'plan' || $action === 'execute');

if ($isAction) {
    @header('X-Accel-Buffering: no');
    @header('Content-Type: text/html; charset=utf-8');
    while (ob_get_level() > 0) @ob_end_flush();
    @ob_implicit_flush(true);
    if (session_status() === PHP_SESSION_ACTIVE) @session_write_close();
}
?>
<?php require THEME . 'html/header.php'; ?>
<table style="width:100%;"><tr><td>
<div style="padding:20px;">
<?php if ($isAction): ?>
    <h2>Actualizador precios Marine Business — <?php echo $dryRun ? 'PLAN (dry-run, sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
    <p>
        <a href="<?php echo tep_href_link('Actualizador_precios_marinebusiness.php'); ?>" class="xbutton small hv9">← Volver</a>
    </p>
    <div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

logMsg("Modo: " . ($dryRun ? "PLAN (dry-run)" : "EXECUTE")
    . " | scope=" . ($scope === 'no_stock' ? 'solo sin stock (qty<=0)' : 'todos los productos MB')
    . " | tope cambio="
    . ($applyExtremes ? "OFF (aplicando también extremos)" : ("|Δ| ≤ " . rtrim(rtrim(number_format($maxChangePct, 1, '.', ''), '0'), '.') . "%"))
    . ($disableOrphans ? " | desactivar huérfanos sin stock=ON" : "")
    . ($max > 0 ? " | max=$max cambios" : ""));

$xlsx = findNewestXlsx(MB_DIR);
if (!$xlsx) { logMsg("ERROR: no hay xlsx en " . MB_DIR); goto end_action; }
logMsg("xlsx: " . basename($xlsx) . " (" . round(filesize($xlsx)/1024) . " KB, mtime " . date('Y-m-d H:i', filemtime($xlsx)) . ")");

logMsg("Leyendo precios del xlsx (sheet esp)…");
$xlsxPrices = loadMbXlsxPrices($xlsx);
logMsg("SKUs en xlsx con precio válido: " . count($xlsxPrices));

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');

// ─── Cargar productos en scope ────────────────────────────────────────────────
$securityFilter = "(p.products_import_origin LIKE 'marinebusiness%' OR p.manufacturers_id = " . MB_MFG_ID . ")";
$scopeFilter = $scope === 'no_stock' ? " AND p.products_quantity <= 0" : "";

logMsg("Leyendo productos MB (filtro de seguridad" . ($scope === 'no_stock' ? ' + scope sin stock' : '') . ")…");
$prods = [];
$r = $mysqli->query("SELECT p.products_id, p.products_model, p.reference_prov, p.products_price, p.products_cost, p.products_quantity, p.products_status FROM products p WHERE $securityFilter $scopeFilter AND p.products_model<>''");
while ($row = $r->fetch_assoc()) $prods[(int) $row['products_id']] = $row;
logMsg("Productos en scope: " . count($prods));
if (empty($prods)) { logMsg("Nada que hacer."); goto end_action; }

// G1 actual en bulk
$ids = implode(',', array_keys($prods));
$g1Cur = [];
$r = $mysqli->query("SELECT products_id, customers_group_price FROM products_groups WHERE customers_group_id=" . G1_GROUP_ID . " AND products_id IN ($ids)");
while ($row = $r->fetch_assoc()) $g1Cur[(int) $row['products_id']] = (float) $row['customers_group_price'];

$updPrice = []; $updCost = []; $updG1 = []; $insG1 = [];
$extremesPrice = []; $extremesCost = []; // se LISTAN pero no se aplican (a menos que applyExtremes)
$extremesPids  = []; // pids con price o cost extremo → su G1 tampoco se toca (queda en sync con el price actual)
$noMatch = []; $noChange = 0;

foreach ($prods as $pid => $p) {
    $model = strtolower(trim((string) $p['products_model']));
    $ref   = strtolower(trim((string) $p['reference_prov']));
    $candidates = array_unique(array_filter([$model, $ref]));
    $entry = null;
    foreach ($candidates as $c) {
        if (isset($xlsxPrices[$c])) { $entry = $xlsxPrices[$c]; break; }
    }
    if ($entry === null) { $noMatch[] = $p; continue; }

    $newCost  = $entry['cost'];
    $newPrice = $entry['price'];
    $newG1    = $entry['g1'];

    $curPrice = (float) $p['products_price'];
    $curCost  = (float) $p['products_cost'];

    $deltaPrice = priceDeltaPct($curPrice, $newPrice);
    $deltaCost  = priceDeltaPct($curCost,  $newCost);
    $priceChanged = $deltaPrice > PRICE_THRESHOLD;
    $costChanged  = $deltaCost  > PRICE_THRESHOLD;
    $priceExtreme = $maxChangeRatio > 0 && $deltaPrice > $maxChangeRatio;
    $costExtreme  = $maxChangeRatio > 0 && $deltaCost  > $maxChangeRatio;

    if ($priceChanged) {
        $row = ['pid' => $pid, 'model' => $p['products_model'], 'old' => $curPrice, 'new' => $newPrice];
        if ($priceExtreme && !$applyExtremes) { $extremesPrice[] = $row; $extremesPids[$pid] = true; }
        else $updPrice[] = $row;
    }
    if ($costChanged) {
        $row = ['pid' => $pid, 'model' => $p['products_model'], 'old' => $curCost, 'new' => $newCost];
        if ($costExtreme && !$applyExtremes) { $extremesCost[] = $row; $extremesPids[$pid] = true; }
        else $updCost[] = $row;
    }

    // G1: si el pid está en extremos, no tocamos G1 tampoco (sino G1 quedaría desalineado del price actual)
    if (!isset($extremesPids[$pid])) {
        if (isset($g1Cur[$pid])) {
            if (priceDeltaPct($g1Cur[$pid], $newG1) > PRICE_THRESHOLD) {
                $updG1[] = ['pid' => $pid, 'model' => $p['products_model'], 'old' => $g1Cur[$pid], 'new' => $newG1];
            }
        } else {
            $insG1[] = ['pid' => $pid, 'model' => $p['products_model'], 'new' => $newG1];
        }
    }

    if (!$priceChanged && !$costChanged && isset($g1Cur[$pid]) && priceDeltaPct($g1Cur[$pid], $newG1) <= PRICE_THRESHOLD) $noChange++;
}

// ───────────────────── Specials a borrar (política V3 2026-07-17) ─────────────────────
// Sin marcar "Aplicar extremos" → borrar solo las ofertas de productos repreciados en este run.
// Razonamiento: las ofertas se pusieron sobre el PVP antiguo; cuando el PVP cambia dejan de tener sentido.
// Política V3 (2026-07-17): solo ofertas de productos cuyo PVP cambia en ESTE run.
// (La V2 borraba todas las del scope — incidente Osculati 2026-07-16, 383 ofertas
// purgadas de 15+ marcas y restauradas desde backup.)
$badSpecials = [];
if (!$applyExtremes && !empty($updPrice)) {
    $effPrice = [];
    foreach ($updPrice as $u) $effPrice[(int)$u['pid']] = (float) $u['new'];
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

$totalChanges = count($updPrice) + count($updCost) + count($updG1) + count($insG1);
if ($max > 0 && $totalChanges > $max) {
    logMsg("Plan supera max=$max cambios totales. Truncando proporcionalmente…");
    $keep = $max;
    $updPrice = array_slice($updPrice, 0, min($keep, count($updPrice))); $keep -= count($updPrice);
    $updCost  = array_slice($updCost,  0, min($keep, count($updCost)));  $keep -= count($updCost);
    $updG1    = array_slice($updG1,    0, min($keep, count($updG1)));    $keep -= count($updG1);
    $insG1    = array_slice($insG1,    0, max(0, $keep));
}

// ─── Huérfanos: productos MB en BD sin match en xlsx ──────────────────────────
// Si el usuario marca "disable_orphans", los desactiva (status=2) SOLO si no tienen
// stock ni en products_quantity ni en products_stock (variantes históricas).
$orphansToDisable  = [];   // candidatos a status=2 (status=1 actualmente + sin stock en ningún sitio)
$orphansHaveStock  = [];   // con stock principal o de variantes — no se tocan
$orphansAlreadyOff = 0;    // ya status=2
$orphansLegacy0    = 0;    // status=0 legacy — NO se tocan por decisión usuario

if (!empty($noMatch)) {
    $orphanPids = array_map(fn($p) => (int) $p['products_id'], $noMatch);
    // 1) Marcar variantes con stock positivo en products_stock
    $pidsWithVarStock = [];
    if (!empty($orphanPids)) {
        $in = implode(',', $orphanPids);
        $r = $mysqli->query("SELECT DISTINCT products_id FROM products_stock WHERE products_id IN ($in) AND products_stock_quantity > 0");
        while ($row = $r->fetch_assoc()) $pidsWithVarStock[(int) $row['products_id']] = true;
    }
    // 2) Clasificar
    foreach ($noMatch as $p) {
        $pid    = (int) $p['products_id'];
        $qty    = (float) $p['products_quantity'];
        $status = (int) $p['products_status'];
        if ($status === 2) { $orphansAlreadyOff++; continue; }
        if ($status === 0) { $orphansLegacy0++; continue; }  // legacy — NO tocar
        $hasMainStock = $qty > 0;
        $hasVarStock  = isset($pidsWithVarStock[$pid]);
        if ($hasMainStock || $hasVarStock) {
            $orphansHaveStock[] = ['pid'=>$pid, 'sku'=>$p['products_model'], 'qty'=>$qty, 'var_stock'=>$hasVarStock ? 'sí' : 'no', 'status'=>$status];
        } else {
            $orphansToDisable[] = ['pid'=>$pid, 'sku'=>$p['products_model'], 'status'=>$status];
        }
    }
}

logMsg("==================== PLAN ====================");
logMsg("UPDATE products.products_price : " . count($updPrice));
logMsg("UPDATE products.products_cost  : " . count($updCost));
logMsg("UPDATE products_groups (G1)    : " . count($updG1));
logMsg("INSERT products_groups (G1)    : " . count($insG1));
if (!$applyExtremes && $maxChangeRatio > 0) {
    logMsg("⚠️  Extremos > {$maxChangePct}% EXCLUIDOS (revisar): price=" . count($extremesPrice) . " cost=" . count($extremesCost) . " (afecta a " . count($extremesPids) . " pids; sus G1 tampoco se tocan)");
}
logMsg("Sin cambios significativos     : $noChange");
logMsg("Sin match en xlsx              : " . count($noMatch) . " (huérfanos: " . count($orphansToDisable) . " desactivables + " . count($orphansHaveStock) . " con stock + " . $orphansAlreadyOff . " ya status=2 + " . $orphansLegacy0 . " legacy status=0 intactos)");
if (!$applyExtremes) logMsg("🗑️  Specials a BORRAR (solo de productos repreciados en este run) : " . count($badSpecials) . (empty($badSpecials)?" (ninguno)":""));
if ($disableOrphans) {
    logMsg("Huérfanos → status=2 (a aplicar): " . count($orphansToDisable) . " (solo los actualmente status=1; los status=0 NO se tocan)");
}

$showLimit = 30; if (!empty($onlyExtremes)) $showLimit = 1000000;
if (!empty($updPrice)) {
    logMsg("--- UPDATE products_price (top $showLimit) ---");
    foreach (array_slice($updPrice, 0, $showLimit) as $u) {
        $pct = priceDeltaPct($u['old'], $u['new']) * 100;
        logMsg(sprintf("  pid=%d sku=%s : %.4f → %.4f (%s%.1f%%)", $u['pid'], $u['model'], $u['old'], $u['new'], $u['new']>=$u['old']?'+':'-', $pct));
    }
    if (count($updPrice) > $showLimit) logMsg("  …y " . (count($updPrice) - $showLimit) . " más");
}
if (!empty($updCost)) {
    logMsg("--- UPDATE products_cost (top $showLimit) ---");
    foreach (array_slice($updCost, 0, $showLimit) as $u) {
        $pct = priceDeltaPct($u['old'], $u['new']) * 100;
        logMsg(sprintf("  pid=%d sku=%s : cost %.4f → %.4f (%s%.1f%%)", $u['pid'], $u['model'], $u['old'], $u['new'], $u['new']>=$u['old']?'+':'-', $pct));
    }
    if (count($updCost) > $showLimit) logMsg("  …y " . (count($updCost) - $showLimit) . " más");
}
if (!empty($insG1)) {
    logMsg("--- INSERT G1 (top $showLimit) ---");
    foreach (array_slice($insG1, 0, $showLimit) as $u) logMsg(sprintf("  pid=%d sku=%s : (sin G1) → %.4f", $u['pid'], $u['model'], $u['new']));
    if (count($insG1) > $showLimit) logMsg("  …y " . (count($insG1) - $showLimit) . " más");
}
if (!empty($updG1)) {
    logMsg("--- UPDATE G1 (top $showLimit) ---");
    foreach (array_slice($updG1, 0, $showLimit) as $u) {
        $pct = priceDeltaPct($u['old'], $u['new']) * 100;
        logMsg(sprintf("  pid=%d sku=%s : G1 %.4f → %.4f (%s%.1f%%)", $u['pid'], $u['model'], $u['old'], $u['new'], $u['new']>=$u['old']?'+':'-', $pct));
    }
    if (count($updG1) > $showLimit) logMsg("  …y " . (count($updG1) - $showLimit) . " más");
}
if (!empty($extremesPrice)) {
    logMsg("--- ⚠️ EXTREMOS price (excluidos, top $showLimit) — probablemente pack-vs-unidad ---");
    foreach (array_slice($extremesPrice, 0, $showLimit) as $u) {
        $pct = priceDeltaPct($u['old'], $u['new']) * 100;
        logMsg(sprintf("  pid=%d sku=%s : %.4f → %.4f (%s%.1f%%)", $u['pid'], $u['model'], $u['old'], $u['new'], $u['new']>=$u['old']?'+':'-', $pct));
    }
    if (count($extremesPrice) > $showLimit) logMsg("  …y " . (count($extremesPrice) - $showLimit) . " más");
}
if (!empty($extremesCost)) {
    logMsg("--- ⚠️ EXTREMOS cost (excluidos, top $showLimit) ---");
    foreach (array_slice($extremesCost, 0, $showLimit) as $u) {
        $pct = priceDeltaPct($u['old'], $u['new']) * 100;
        logMsg(sprintf("  pid=%d sku=%s : cost %.4f → %.4f (%s%.1f%%)", $u['pid'], $u['model'], $u['old'], $u['new'], $u['new']>=$u['old']?'+':'-', $pct));
    }
    if (count($extremesCost) > $showLimit) logMsg("  …y " . (count($extremesCost) - $showLimit) . " más");
}
if (!empty($orphansToDisable)) {
    $action_label = $disableOrphans ? ($dryRun ? "se desactivarían" : "se desactivarán") : "se podrían desactivar (marca 'desactivar huérfanos')";
    logMsg("--- Huérfanos SIN stock → status=2 ($action_label, top $showLimit) ---");
    foreach (array_slice($orphansToDisable, 0, $showLimit) as $o) {
        logMsg(sprintf("  pid=%d sku=%s (status actual=%d)", $o['pid'], $o['sku'], $o['status']));
    }
    if (count($orphansToDisable) > $showLimit) logMsg("  …y " . (count($orphansToDisable) - $showLimit) . " más");
}
if (!empty($orphansHaveStock)) {
    logMsg("--- Huérfanos CON stock (NO se desactivan, top $showLimit) — revisar manualmente ---");
    foreach (array_slice($orphansHaveStock, 0, $showLimit) as $o) {
        logMsg(sprintf("  pid=%d sku=%s qty=%s stock_variantes=%s status=%d", $o['pid'], $o['sku'], rtrim(rtrim(number_format($o['qty'], 2, '.', ''), '0'), '.'), $o['var_stock'], $o['status']));
    }
    if (count($orphansHaveStock) > $showLimit) logMsg("  …y " . (count($orphansHaveStock) - $showLimit) . " más");
}

if (!empty($badSpecials)) {
    logMsg(sprintf("--- 🗑️ Specials a BORRAR (repreciados en este run: %d) ---", count($badSpecials)));
    foreach ($badSpecials as $b) {
        logMsg(sprintf("  specials_id=%d pid=%d ref=%-14s PVP=%7.2f sp=%7.2f dto=%5.1f%% creado=%s expira=%s — %s",
            $b['specials_id'], $b['pid'], $b['ref'], $b['eff_price']*1.21, $b['sp_price']*1.21, $b['dto_pct'], $b['created'], $b['expires'], $b['reason']));
    }
}

if ($dryRun) {
    logMsg("=== Dry-run finalizado. No se ha tocado nada. ===");
    goto end_action;
}

logMsg("Aplicando cambios en transacción única…");
$mysqli->begin_transaction();
try {
    foreach ($updPrice as $u) {
        if (!$mysqli->query("UPDATE products SET products_price=" . fmt4($u['new']) . ", products_last_modified=NOW() WHERE products_id=" . (int)$u['pid'])) throw new Exception("UPDATE price pid=" . $u['pid'] . ": " . $mysqli->error);
    }
    foreach ($updCost as $u) {
        if (!$mysqli->query("UPDATE products SET products_cost=" . fmt4($u['new']) . ", products_last_modified=NOW() WHERE products_id=" . (int)$u['pid'])) throw new Exception("UPDATE cost pid=" . $u['pid'] . ": " . $mysqli->error);
    }
    foreach ($updG1 as $u) {
        if (!$mysqli->query("UPDATE products_groups SET customers_group_price=" . fmt4($u['new']) . " WHERE products_id=" . (int)$u['pid'] . " AND customers_group_id=" . G1_GROUP_ID)) throw new Exception("UPDATE g1 pid=" . $u['pid'] . ": " . $mysqli->error);
    }
    foreach ($insG1 as $u) {
        if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", " . (int)$u['pid'] . ", " . fmt4($u['new']) . ", 1, 1)")) throw new Exception("INSERT g1 pid=" . $u['pid'] . ": " . $mysqli->error);
    }
    $orphansApplied = 0;
    if ($disableOrphans && !empty($orphansToDisable)) {
        foreach ($orphansToDisable as $o) {
            // Guard belt-and-suspenders: solo si products_status<>2 AND products_quantity<=0 AND sin variantes con stock
            $pid = (int) $o['pid'];
            $ok = $mysqli->query("UPDATE products p
                SET products_status=2, products_last_modified=NOW()
                WHERE p.products_id=$pid
                  AND p.products_status=1
                  AND p.products_quantity<=0
                  AND NOT EXISTS (SELECT 1 FROM products_stock ps WHERE ps.products_id=p.products_id AND ps.products_stock_quantity>0)");
            if (!$ok) throw new Exception("UPDATE status=2 pid=$pid: " . $mysqli->error);
            $orphansApplied += $mysqli->affected_rows;
        }
    }
    // ───────────────────── Borrado de specials descolgados (backup + DELETE + bump) ─────────────────────
    if (!empty($badSpecials)) {
        $bakDir = '/home/francobordo/backups';
        @mkdir($bakDir, 0755, true);
        $bakPath = $bakDir . '/marinebusiness_specials_purge_' . date('Ymd_His') . '.sql';
        $freeBytes = @disk_free_space($bakDir);
        if ($freeBytes !== false && $freeBytes < 100 * 1024 * 1024) {
            logMsg("WARN: poco espacio en $bakDir (" . round(($freeBytes ?: 0) / 1024 / 1024) . "MB libres, mínimo 100MB) — abortando DELETE de specials.");
            throw new Exception("disco insuficiente para backup specials");
        }
        $fh = @fopen($bakPath, 'w');
        if ($fh) {
            fwrite($fh, "-- Backup specials borrados por Actualizador_precios_marinebusiness.php " . date('Y-m-d H:i:s') . "
");
            fwrite($fh, "-- Política V3: !apply_extremes ⇒ borrar ofertas de productos repreciados en este run. Total: " . count($badSpecials) . " filas.

");
            $idList = implode(',', array_map(fn($b) => (int) $b['specials_id'], $badSpecials));
            $rb = $mysqli->query("SELECT * FROM specials WHERE specials_id IN ($idList)");
            if ($rb) while ($srow = $rb->fetch_assoc()) {
                $cols = array_keys($srow);
                $vals = array_map(function ($v) use ($mysqli) {
                    if ($v === null) return 'NULL';
                    return "'" . $mysqli->real_escape_string((string) $v) . "'";
                }, array_values($srow));
                fwrite($fh, "INSERT INTO specials (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");
");
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
    logMsg("UPDATE products_price aplicados : " . count($updPrice));
    logMsg("UPDATE products_cost  aplicados : " . count($updCost));
    logMsg("UPDATE G1 aplicados             : " . count($updG1));
    logMsg("INSERT G1 aplicados             : " . count($insG1));
    if ($disableOrphans) {
        logMsg("Huérfanos desactivados (status=2) : $orphansApplied / " . count($orphansToDisable) . " planeados (diferencia = guard de seguridad o stock entrante entre PLAN y EXECUTE)");
    }
} catch (Exception $e) {
    $mysqli->rollback();
    logMsg("=== ROLLBACK por error: " . $e->getMessage() . " ===");
}

end_action:
?>
    </div>
    <p style="margin-top:15px;">
        <a href="<?php echo tep_href_link('Actualizador_precios_marinebusiness.php'); ?>" class="xbutton small hv9">← Volver</a>
    </p>
<?php else: ?>
    <h2>Actualizador de precios — Marine Business</h2>
    <?php
        $xlsx = findNewestXlsx(MB_DIR);
        if (!$xlsx) {
            echo '<p style="color:red;">No hay xlsx en ' . MB_DIR . '</p>';
        } else {
            echo '<p style="color:#666;font-size:13px;">xlsx detectado: <code>' . htmlspecialchars(basename($xlsx)) . '</code> (' . round(filesize($xlsx)/1024) . ' KB, mtime ' . date('Y-m-d H:i', filemtime($xlsx)) . ')</p>';
        }
    ?>
    <p>
        Lee el xlsx más reciente de <code><?php echo MB_DIR; ?></code> (sheet <code>esp</code>, col A=Código y col C=Precio Retail sin IVA)
        y compara con los <code>products_price</code> / <code>products_cost</code> / <code>products_groups</code> (G1)
        de los productos Marine Business en BD. Aplica cambios solo cuando la diferencia supera el threshold.
    </p>
    <form method="post" style="background:#f5f5f5;padding:15px;border-radius:5px;">
        <p>
            <strong>Scope (qué productos actualizar)</strong>:<br>
            <label><input type="radio" name="scope" value="all" checked> Todos los productos MB (origin marinebusiness% o manufacturer id=<?php echo MB_MFG_ID; ?>)</label><br>
            <label><input type="radio" name="scope" value="no_stock"> Solo productos sin stock real (<code>products_quantity ≤ 0</code>)</label>
        </p>
        <p>
            <strong>Tope de variación</strong>:
            <label>excluir cambios &gt;
                <input type="number" name="max_change_pct" value="<?php echo (int) MAX_CHANGE_PCT_DEF; ?>" min="0" max="500" step="1" style="width:60px;">
                %
            </label>
            <small style="color:#888;display:block;margin-top:4px;">Los cambios cuyo |Δ| supere este porcentaje se listan aparte como "extremos" y NO se aplican. 0 = sin tope. Útil para detectar productos que el proveedor vende en pack y nosotros vendemos por unidad (la división por 6/12 dispara variaciones del 80-500%).</small>
        </p>
        <p>
            <label><input type="checkbox" name="apply_extremes" value="1"> Aplicar también los extremos (desactiva el tope)</label>
            <small style="color:#888;display:block;margin-top:4px;">Solo marcar cuando hayas revisado el plan anterior y los extremos sean legítimos (subida real del proveedor).</small>
        </p>
        <p>
            <label><input type="checkbox" name="disable_orphans" value="1"> Desactivar (status=2) productos MB de la web que ya NO están en el xlsx, si no tienen stock</label>
            <small style="color:#888;display:block;margin-top:4px;">Marca status=2 a los productos MB en BD que no aparecen en el xlsx actual <em>siempre que</em> <code>products_status = 1</code> (activos) Y <code>products_quantity ≤ 0</code> Y no exista ninguna variante (<code>products_stock</code>) con stock &gt; 0. Productos con <code>status=0</code> (legacy) o <code>status=2</code> (ya desactivados) se omiten. Los que tienen stock se listan aparte para revisión manual.</small>
        </p>
        <p>
            <label>Cambios máximos por ejecución (0 = sin límite):
                <input type="number" name="max" value="0" min="0" style="width:80px;">
            </label>
        </p>
        <p>
            <label><input type="checkbox" name="confirm_execute" value="1"> Aplicar cambios (sin marcar = solo PLAN/dry-run)</label>
        </p>
        <p><label><input type="checkbox" name="only_extremes" value="1"> <strong>Ver SOLO los productos saltados por extremos</strong> (en el plan, oculta el resto)</label></p>
		<button type="submit" name="action" value="plan" class="xbutton small hv9">Generar plan (dry-run)</button>
        <button type="submit" name="action" value="execute" class="xbutton small hv9 verde" onclick="return this.form.confirm_execute.checked || (alert('Marca la casilla \'Aplicar cambios\' antes de ejecutar.'), false);">Ejecutar</button>
    </form>
    <p style="margin-top:20px;color:#888;font-size:12px;">
        <strong>Reglas aplicadas</strong> (idénticas al importador):<br>
        - <code>products_cost</code>  ← Retail × <?php echo COST_MULT; ?> (sin IVA, 4 decimales).<br>
        - <code>products_price</code> ← <code>roundToNickel(Retail × <?php echo PRICE_MULT_NET; ?>)</code> (PVP con IVA en múltiplos de 0,05€).<br>
        - <strong>Grupo 1 (Profesionales)</strong>: <code>roundToNickel(Retail × <?php echo G1_MULT; ?>)</code>.<br>
        - Threshold mínimo: solo aplica si <code>|nuevo − actual| / |actual| &gt; <?php echo PRICE_THRESHOLD; ?></code>. Aplica por separado a price, cost y G1.<br>
        - Tope superior: los cambios mayores al porcentaje del form se excluyen automáticamente (sospecha de pack-vs-unidad). Pids con price o cost extremo se excluyen también del UPDATE/INSERT de G1.<br>
        - Match SKU contra <code>products.products_model</code> o <code>products.reference_prov</code> (case-insensitive).<br>
        - <strong>Stock NO se toca</strong> — esto solo actualiza precios.<br>
        - Productos en BD sin match en el xlsx se listan informativamente y se dejan tal cual.<br>
        - Una sola transacción: si algo falla, rollback total.<br>
        - Output en streaming en tiempo real.
    </p>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
