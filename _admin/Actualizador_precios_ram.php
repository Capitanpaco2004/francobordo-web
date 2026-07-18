<?php
require 'includes/application_top.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);

/* ──────────────────────────────────────────────────────────────────────────
 * Actualizador de precios RAM Mounts
 *
 * Fuente (2026-06-12, antes el xlsx de import/Ram): /descargas/gamp/MSI.csv
 * (lo sube RAM por FTP a diario; el mismo fichero que usa el actualizador de stock).
 *   Separador ';', cabecera "#;Cod Articulo;Descripcion;Cod EAN;PVP;DTO;Stock;Precio Coste;Cliente".
 *   Col B (idx 1) = SKU · Col D (idx 3) = EAN (UPC-A) · Col E (idx 4) = PVP (IVA incluido,
 *   coma decimal) · Col H (idx 7) = Precio Coste (= PVE, coma decimal).
 * Match: products_model (SKU) ↔ Cod Articulo; fallback product_ean ↔ UPC-A→EAN-13.
 *   products_cost  = Precio Coste.
 *   products_price = roundToNickel(PVP / 1,21).
 *   G1 (Profesionales) = roundToNickel(tiers de margen + piso cost×1,10).
 * RAM no tiene variantes → solo productos sueltos. Stock NO se toca.
 * Tope de variación 30% (excluye pack-vs-unidad / errores). PLAN/EXECUTE en transacción.
 * ────────────────────────────────────────────────────────────────────────── */

const CSV_PATH             = '/home/francobordo/public_html/descargas/gamp/MSI.csv';
const PRICE_DIFF_THRESHOLD = 0.005;
const MAX_CHANGE_PCT_DEF   = 30;
const IVA_ES               = 1.21;
const G1_GROUP_ID          = 1;
const MFG_NAME             = 'RAM Mounts';
const ORIGIN_FLAG          = 'ram';

function roundToNickel($net) { $wi = ((float) $net) * IVA_ES; $r = round($wi * 20) / 20; return round($r / IVA_ES, 4); }
function priceNetFromGross($g) { return roundToNickel(((float) $g) / IVA_ES); }
function g1Multiplier($pvp, $cost) { if ($pvp <= 0) return 0.90; $m = ($pvp - $cost) / $pvp; if ($m >= 0.45) return 0.75; if ($m >= 0.40) return 0.80; if ($m >= 0.35) return 0.82; if ($m >= 0.30) return 0.85; return 0.90; }
function calcG1Price($pvp, $cost) { return round(max($pvp * g1Multiplier($pvp, $cost), (float) $cost * 1.10), 4); }
function deltaPct($oldP, $newP) { $ref = max(abs((float) $oldP), 0.01); return abs((float) $newP - (float) $oldP) / $ref; }
function aboveThreshold($newVal, $oldVal) { $o = (float) $oldVal; $n = (float) $newVal; if (abs($o) < 1e-6) return abs($n - $o) > 0.0001; return abs($n - $o) / abs($o) > PRICE_DIFF_THRESHOLD; }
function fmt4($v) { return number_format((float) $v, 4, '.', ''); }
function ramLogMsg($m) { echo '<div class="small" style="font-family:monospace">' . htmlspecialchars((string) $m) . '</div>'; }

function ean13Checksum($p) { if (strlen($p) !== 12 || !ctype_digit($p)) return -1; $s = 0; for ($i = 0; $i < 12; $i++) { $d = (int) $p[$i]; $s += ($i % 2 === 0) ? $d : $d * 3; } return (10 - ($s % 10)) % 10; }
function isValidEan13($e) { $e = trim((string) $e); if (strlen($e) !== 13 || !ctype_digit($e)) return false; return ean13Checksum(substr($e, 0, 12)) === (int) $e[12]; }
function ramUpcToEan13($raw) { $d = preg_replace('/\D/', '', (string) $raw); if ($d === '') return ''; if (strlen($d) === 11) $d = '0' . $d; if (strlen($d) === 12) { $e = '0' . $d; return isValidEan13($e) ? $e : ''; } if (strlen($d) === 13) return isValidEan13($d) ? $d : ''; return ''; }
/** "86,410" (coma decimal) → 86.41; devuelve null si no es numérico. */
function ramCsvNum($raw) { $s = str_replace(',', '.', trim((string) $raw)); return is_numeric($s) ? (float) $s : null; }

$dryRun        = !isset($_GET['execute']);
$applyExtremes = isset($_GET['apply_extremes']);
$maxChangePct  = isset($_GET['max_change_pct']) ? (float) $_GET['max_change_pct'] : MAX_CHANGE_PCT_DEF;
if ($maxChangePct < 0) $maxChangePct = 0;
$maxChangeRatio = $maxChangePct / 100.0;
$scope         = (($_GET['scope'] ?? 'all') === 'no_stock') ? 'no_stock' : 'all';

/** CSV MSI → ['by_sku'=>[SKU=>row], 'by_ean'=>[EAN=>row]]; row = ['sku','cost','pvp','ean']. */
function parseCsv($path) {
    $fh = fopen($path, 'r');
    if (!$fh) return ['by_sku' => [], 'by_ean' => []];
    $bySku = []; $byEan = [];
    while (($r = fgetcsv($fh, 0, ';', '"', '\\')) !== false) {
        $sku = strtoupper(trim((string) ($r[1] ?? '')));
        if ($sku === '' || $sku === 'COD ARTICULO') continue;                // cabecera / vacías
        if (!ctype_digit(trim((string) ($r[0] ?? '')))) continue;           // solo filas numeradas
        $pvp = ramCsvNum($r[4] ?? '');                                       // PVP IVA incluido
        $pve = ramCsvNum($r[7] ?? '');                                       // Precio Coste (= PVE)
        $ean = ramUpcToEan13($r[3] ?? '');
        $row = ['sku' => $sku, 'cost' => $pve, 'pvp' => $pvp, 'ean' => $ean];
        $bySku[$sku] = $row;
        if ($ean !== '' && !isset($byEan[$ean])) $byEan[$ean] = $row;
    }
    fclose($fh);
    return ['by_sku' => $bySku, 'by_ean' => $byEan];
}

function ramMfgId() { $r = tep_db_query("SELECT manufacturers_id FROM manufacturers WHERE manufacturers_name='" . tep_db_input(MFG_NAME) . "' LIMIT 1"); $row = tep_db_fetch_array($r); return $row ? (int) $row['manufacturers_id'] : 0; }

function loadProducts($mfgId, $scope) {
    $rows = [];
    $f = $mfgId > 0 ? "(manufacturers_id = $mfgId OR products_import_origin LIKE 'ram%')" : "(products_import_origin LIKE 'ram%')";
    if ($scope === 'no_stock') $f .= " AND products_quantity <= 0";
    $r = tep_db_query("SELECT products_id, products_model, product_ean, products_price, products_cost FROM products WHERE $f");
    while ($p = tep_db_fetch_array($r)) {
        $pid = (int) $p['products_id'];
        $rows[$pid] = ['pid' => $pid, 'model' => strtoupper(trim((string) $p['products_model'])), 'ean' => trim((string) $p['product_ean']), 'price' => (float) $p['products_price'], 'cost' => (float) $p['products_cost']];
    }
    return $rows;
}
function loadG1Products(array $pids) { $by = []; if (!$pids) return $by; $ids = implode(',', array_map('intval', $pids)); $r = tep_db_query("SELECT products_id, customers_group_price FROM products_groups WHERE customers_group_id = " . G1_GROUP_ID . " AND products_id IN ($ids)"); while ($x = tep_db_fetch_array($r)) $by[(int) $x['products_id']] = (float) $x['customers_group_price']; return $by; }

function buildPlan(array $prods, array $xlsx, array $g1prods, float $maxChangeRatio, bool $applyExtremes) {
    $plan = ['updates_product' => [], 'updates_g1_product' => [], 'inserts_g1_product' => [], 'extremes' => [],
             'skipped_no_match' => 0, 'skipped_no_cost' => 0, 'skipped_unchanged' => 0, 'unmatched_codes' => []];
    foreach ($prods as $p) {
        $pid = $p['pid'];
        $row = $xlsx['by_sku'][$p['model']] ?? null;
        if (!$row && $p['ean'] !== '') $row = $xlsx['by_ean'][$p['ean']] ?? null;
        if (!$row) { $plan['unmatched_codes'][] = $p['model']; $plan['skipped_no_match']++; continue; }
        if ($row['cost'] === null || $row['cost'] <= 0 || $row['pvp'] === null || $row['pvp'] <= 0) { $plan['skipped_no_cost']++; continue; }
        $newCost  = round((float) $row['cost'], 4);
        $newPrice = priceNetFromGross((float) $row['pvp']);
        $newG1    = roundToNickel(calcG1Price($newPrice, $newCost));

        $dP = deltaPct($p['price'], $newPrice); $dC = deltaPct($p['cost'], $newCost);
        if (!$applyExtremes && $maxChangeRatio > 0 && max($dP, $dC) > $maxChangeRatio) {
            $plan['extremes'][] = ['pid' => $pid, 'model' => $p['model'], 'old_price' => $p['price'], 'new_price' => $newPrice, 'old_cost' => $p['cost'], 'new_cost' => $newCost];
            continue;
        }
        $changed = false;
        if (aboveThreshold($newPrice, $p['price']) || aboveThreshold($newCost, $p['cost'])) {
            $plan['updates_product'][] = ['pid' => $pid, 'model' => $p['model'], 'old_price' => $p['price'], 'new_price' => $newPrice, 'old_cost' => $p['cost'], 'new_cost' => $newCost];
            $changed = true;
        }
        $oldG1 = $g1prods[$pid] ?? null;
        if ($oldG1 === null) { $plan['inserts_g1_product'][] = ['pid' => $pid, 'price' => $newG1]; $changed = true; }
        elseif (aboveThreshold($newG1, $oldG1)) { $plan['updates_g1_product'][] = ['pid' => $pid, 'old' => $oldG1, 'new' => $newG1]; $changed = true; }
        if (!$changed) $plan['skipped_unchanged']++;
    }
    return $plan;
}

function applyPlan(array $plan) {
    foreach ($plan['updates_product'] as $u) tep_db_query("UPDATE products SET products_price = " . fmt4($u['new_price']) . ", products_cost = " . fmt4($u['new_cost']) . ", products_last_modified = NOW() WHERE products_id = " . (int) $u['pid']);
    foreach ($plan['updates_g1_product'] as $u) tep_db_query("UPDATE products_groups SET customers_group_price = " . fmt4($u['new']) . " WHERE products_id = " . (int) $u['pid'] . " AND customers_group_id = " . G1_GROUP_ID);
    foreach ($plan['inserts_g1_product'] as $u) tep_db_query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", " . (int) $u['pid'] . ", " . fmt4($u['price']) . ", 1, 1)");
}

$csvPath = file_exists(CSV_PATH) ? CSV_PATH : null;
?>
<?php require THEME . 'html/header.php'; ?>
<style>
.ram-wrap { padding: 10px 20px; font-family: Arial, sans-serif; font-size: 13px; }
.ram-wrap h1 { font-size: 22px; margin: 8px 0 14px; }
.ram-wrap h2 { font-size: 16px; margin: 18px 0 8px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
.ram-wrap table { border-collapse: collapse; width: 100%; margin: 6px 0 14px; }
.ram-wrap th, .ram-wrap td { border: 1px solid #ddd; padding: 4px 8px; text-align: left; vertical-align: top; }
.ram-wrap th { background: #f0f0f0; }
.ram-wrap td.num { text-align: right; font-family: monospace; }
.ram-wrap .btn { display: inline-block; padding: 8px 16px; border-radius: 4px; text-decoration: none; color: #fff; }
.ram-wrap .btn-run { background: #c33; } .ram-wrap .btn-back { background: #888; }
.ram-wrap .badge-dry { background: #fa3; color: #000; padding: 3px 8px; border-radius: 3px; font-weight: bold; }
.ram-wrap .badge-exec { background: #393; color: #fff; padding: 3px 8px; border-radius: 3px; font-weight: bold; }
.ram-wrap details summary { cursor: pointer; padding: 4px 0; font-weight: bold; }
.ram-wrap .small { color: #666; font-size: 11px; }
</style>
<div class="ram-wrap">
<h1>Actualizador de precios RAM Mounts</h1>
<p>Modo: <span class="<?php echo $dryRun ? 'badge-dry' : 'badge-exec'; ?>"><?php echo $dryRun ? 'DRY RUN (no escribe)' : 'EJECUTANDO'; ?></span></p>
<?php
if (!$csvPath) { echo '<p style="color:red"><strong>No existe ' . htmlspecialchars(CSV_PATH) . '</strong></p></div>'; require THEME . 'html/footer.php'; require DIR_WS_INCLUDES . 'application_bottom.php'; exit; }
echo '<p>Fichero: <code>' . htmlspecialchars(CSV_PATH) . '</code> <span class="small">(modificado ' . date('Y-m-d H:i', filemtime($csvPath)) . ')</span></p>';

$t0 = microtime(true);
$xlsx = parseCsv($csvPath);
echo '<p>SKUs CSV leídos: <strong>' . count($xlsx['by_sku']) . '</strong> <span class="small">(' . round(microtime(true) - $t0, 2) . 's)</span></p>';

$mfgId = ramMfgId();
$prods = loadProducts($mfgId, $scope);
$g1prods = loadG1Products(array_keys($prods));
echo '<p>Productos RAM (' . ($scope === 'no_stock' ? 'solo sin stock' : 'todos') . '): <strong>' . count($prods) . '</strong> <span class="small">(mfg id=' . $mfgId . ')</span></p>';

$t0 = microtime(true);
$plan = buildPlan($prods, $xlsx, $g1prods, $maxChangeRatio, $applyExtremes);
echo '<p>Plan calculado en ' . round(microtime(true) - $t0, 2) . 's.</p>';

// ─── Bloque A: cómputo specials a borrar (política V3: solo repreciados en este run) ───
// Política V3 (2026-07-17): solo ofertas de productos cuyo PVP cambia en ESTE run.
// (La V2 borraba todas las del scope — incidente Osculati 2026-07-16, restaurado.)
$badSpecials = [];
if (!$applyExtremes && !empty($plan['updates_product'])) {
    $effPrice = [];
    foreach ($plan['updates_product'] as $u) $effPrice[(int) $u['pid']] = (float) $u['new_price'];

    $ids = implode(',', array_map('intval', array_keys($effPrice)));
    try {
        $rs = tep_db_query("SELECT specials_id, products_id, specials_new_products_price, specials_date_added, expires_date, expires_repeat FROM specials WHERE status=1 AND products_id IN ($ids)");
        while ($rs && ($s = tep_db_fetch_array($rs))) {
            $pid = (int) $s['products_id'];
            $eff = (float) ($effPrice[$pid] ?? 0);
            $sp  = (float) $s['specials_new_products_price'];
            $dtoPct = $eff > 0 ? (($eff - $sp) / $eff) * 100 : 0.0;
            $badSpecials[] = [
                'specials_id' => (int) $s['specials_id'],
                'pid' => $pid,
                'ref' => $prods[$pid]['model'] ?? '?',
                'eff_price' => $eff,
                'sp_price'  => $sp,
                'dto_pct'   => $dtoPct,
                'reason'    => ($sp > $eff) ? 'NEGATIVO (special > PVP nuevo)' : (sprintf('dto %.1f%%', $dtoPct) . ' sobre PVP nuevo — PVP repreciado en este run'),
                'created'   => substr((string) $s['specials_date_added'], 0, 10),
                'expires'   => substr((string) $s['expires_date'], 0, 10),
            ];
        }
    } catch (\Throwable $e) {
        ramLogMsg("ERROR SELECT specials: " . $e->getMessage());
        $badSpecials = [];
    }
}

if ($dryRun) {
    echo '<form method="get" style="background:#f5f5f5;padding:10px;border-radius:4px;margin-bottom:15px;">';
    echo '<strong>Tope de variación</strong>: excluir cambios &gt; <input type="number" name="max_change_pct" value="' . (int) $maxChangePct . '" min="0" max="500" step="1" style="width:60px;"> %';
    echo ' &nbsp; <label><input type="checkbox" name="apply_extremes" value="1"' . ($applyExtremes ? ' checked' : '') . '> Aplicar también los extremos</label>';
    echo ' &nbsp; <label>Ámbito: <select name="scope"><option value="all"' . ($scope === 'all' ? ' selected' : '') . '>Todos</option><option value="no_stock"' . ($scope === 'no_stock' ? ' selected' : '') . '>Solo sin stock</option></select></label>';
    echo ' &nbsp; <button type="submit" class="btn btn-back" style="background:#36c;">Recalcular plan</button>';
    echo '<br><span class="small">Si el price o cost cambia más del porcentaje, el producto se excluye. 0 = sin tope. Protege contra errores de feed.</span>';
    echo '</form>';
}
?>
<h2>Resumen</h2>
<ul>
    <li>UPDATE <code>products</code> precio/coste: <strong><?php echo count($plan['updates_product']); ?></strong></li>
    <li>UPDATE <code>products_groups</code> Grupo 1: <strong><?php echo count($plan['updates_g1_product']); ?></strong></li>
    <li>INSERT <code>products_groups</code> Grupo 1: <strong><?php echo count($plan['inserts_g1_product']); ?></strong></li>
    <?php if (!$applyExtremes && $maxChangeRatio > 0): ?>
    <li>⚠️ Productos EXTREMOS excluidos (&gt; <?php echo $maxChangePct; ?>%): <strong><?php echo count($plan['extremes']); ?></strong></li>
    <?php endif; ?>
    <?php if (!$applyExtremes): ?>
    <li>🗑️ Specials a BORRAR (solo de productos repreciados en este run): <strong><?php echo count($badSpecials); ?></strong><?php if (empty($badSpecials)) echo ' <span class="small">(ninguno)</span>'; ?></li>
    <?php endif; ?>
    <li class="small">Sin match en CSV: <?php echo $plan['skipped_no_match']; ?> | sin coste/PVP válido: <?php echo $plan['skipped_no_cost']; ?> | sin cambio significativo: <?php echo $plan['skipped_unchanged']; ?></li>
</ul>
<?php
function renderTable($title, $rows, $cols, $limit = 300) {
    $n = count($rows); if ($n === 0) return;
    echo '<details><summary>' . htmlspecialchars($title) . ' (' . $n . ($n > $limit ? ', primeros ' . $limit : '') . ')</summary><table><tr>';
    foreach ($cols as $h) echo '<th>' . htmlspecialchars($h[0]) . '</th>';
    echo '</tr>'; $i = 0;
    foreach ($rows as $r) { if ($i++ >= $limit) break; echo '<tr>'; foreach ($cols as $c) { $cls = isset($c[2]) && $c[2] === 'num' ? ' class="num"' : ''; echo '<td' . $cls . '>' . htmlspecialchars((string) $c[1]($r)) . '</td>'; } echo '</tr>'; }
    echo '</table></details>';
}
if (!empty($plan['extremes'])) renderTable('⚠️ EXTREMOS excluidos', $plan['extremes'], [
    ['pid', fn($r) => $r['pid']], ['model', fn($r) => $r['model']],
    ['old_price', fn($r) => number_format($r['old_price'], 4), 'num'], ['new_price', fn($r) => number_format($r['new_price'], 4), 'num'],
    ['Δ price %', fn($r) => number_format(deltaPct($r['old_price'], $r['new_price']) * 100, 1), 'num'],
    ['old_cost', fn($r) => number_format($r['old_cost'], 4), 'num'], ['new_cost', fn($r) => number_format($r['new_cost'], 4), 'num'],
    ['Δ cost %', fn($r) => number_format(deltaPct($r['old_cost'], $r['new_cost']) * 100, 1), 'num'],
]);
renderTable('Cambios precio/coste', $plan['updates_product'], [
    ['pid', fn($r) => $r['pid']], ['model', fn($r) => $r['model']],
    ['old_price', fn($r) => number_format($r['old_price'], 4), 'num'], ['new_price', fn($r) => number_format($r['new_price'], 4), 'num'],
    ['old_cost', fn($r) => number_format($r['old_cost'], 4), 'num'], ['new_cost', fn($r) => number_format($r['new_cost'], 4), 'num'],
]);
renderTable('UPDATEs Grupo 1', $plan['updates_g1_product'], [['pid', fn($r) => $r['pid']], ['old', fn($r) => number_format($r['old'], 4), 'num'], ['new', fn($r) => number_format($r['new'], 4), 'num']]);
renderTable('INSERTs Grupo 1', $plan['inserts_g1_product'], [['pid', fn($r) => $r['pid']], ['price', fn($r) => number_format($r['price'], 4), 'num']]);
$unmatchedRows = array_map(fn($c) => ['code' => $c], array_values(array_unique($plan['unmatched_codes'])));
renderTable('Códigos sin match en CSV (informativo)', $unmatchedRows, [['code', fn($r) => $r['code']]]);

if (!empty($badSpecials)) renderTable('🗑️ Specials a BORRAR (solo de productos repreciados en este run — política V3)', $badSpecials, [
    ['specials_id', fn($r) => $r['specials_id']],
    ['pid', fn($r) => $r['pid']],
    ['ref', fn($r) => $r['ref']],
    ['PVP (IVA inc)', fn($r) => number_format($r['eff_price'] * IVA_ES, 2), 'num'],
    ['sp (IVA inc)', fn($r) => number_format($r['sp_price'] * IVA_ES, 2), 'num'],
    ['dto %', fn($r) => number_format($r['dto_pct'], 1), 'num'],
    ['creado', fn($r) => $r['created']],
    ['expira', fn($r) => $r['expires']],
    ['motivo', fn($r) => $r['reason']],
]);

if (!$dryRun) {
    $t0 = microtime(true);
    tep_db_query('START TRANSACTION');
    try {
        applyPlan($plan);

        // ─── Bloque D: backup + DELETE specials (política V3: solo repreciados en este run) ───
        if (!empty($badSpecials)) {
            $bakDir = '/home/francobordo/backups';
            @mkdir($bakDir, 0755, true);
            $bakPath = $bakDir . '/ram_specials_purge_' . date('Ymd_His') . '.sql';
            $freeBytes = @disk_free_space($bakDir);
            if ($freeBytes !== false && $freeBytes < 100 * 1024 * 1024) {
                ramLogMsg("WARN: poco espacio en $bakDir (" . round(($freeBytes ?: 0) / 1024 / 1024) . "MB libres, mínimo 100MB) — abortando DELETE de specials.");
                throw new Exception("disco insuficiente para backup specials");
            }
            $fh = @fopen($bakPath, 'w');
            if (!$fh) {
                ramLogMsg("WARN: no pude crear backup en $bakDir — abortando DELETE de specials por seguridad.");
                throw new Exception("backup specials no escribible");
            }
            fwrite($fh, "-- Backup specials borrados por Actualizador_precios_ram.php " . date('Y-m-d H:i:s') . "\n");
            fwrite($fh, "-- Política V3: !apply_extremes ⇒ borrar ofertas de productos repreciados en este run. Total: " . count($badSpecials) . " filas.\n\n");
            $idList = implode(',', array_map(fn($b) => (int) $b['specials_id'], $badSpecials));
            $rb = tep_db_query("SELECT * FROM specials WHERE specials_id IN ($idList)");
            if ($rb) while ($srow = tep_db_fetch_array($rb)) {
                $cols = array_keys($srow);
                $vals = array_map(function ($v) {
                    if ($v === null) return 'NULL';
                    return "'" . tep_db_input((string) $v) . "'";
                }, array_values($srow));
                fwrite($fh, "INSERT INTO specials (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n");
            }
            fclose($fh);
            $bakSize = @filesize($bakPath);
            if ($bakSize === false || $bakSize < 100) {
                @unlink($bakPath);
                ramLogMsg("WARN: backup escrito vacío/truncado ($bakSize bytes) — abortando DELETE de specials.");
                throw new Exception("backup specials truncado o vacío");
            }
            ramLogMsg("Backup specials borrados: $bakPath ($bakSize bytes)");

            $delStmt = tep_db_query("DELETE FROM specials WHERE specials_id IN ($idList)");
            ramLogMsg("Specials borrados: " . tep_db_affected_rows($delStmt));

            // ─── Bloque E: bump products_last_modified de los pids cuyos specials se borraron ───
            $pidsBump = implode(',', array_unique(array_map(fn($b) => (int) $b['pid'], $badSpecials)));
            tep_db_query("UPDATE products SET products_last_modified=NOW() WHERE products_id IN ($pidsBump)");
        }

        tep_db_query('COMMIT');
        echo '<p style="color:#393;font-weight:bold">✔ Cambios aplicados en ' . round(microtime(true) - $t0, 2) . 's.</p>';
    }
    catch (\Throwable $e) { tep_db_query('ROLLBACK'); echo '<p style="color:#c33;font-weight:bold">✘ Error: ' . htmlspecialchars($e->getMessage()) . ' — ROLLBACK.</p>'; }
    echo '<p><a class="btn btn-back" href="' . tep_href_link('Actualizador_precios_ram.php') . '">Volver a dry-run</a></p>';
} else {
    $total = count($plan['updates_product']) + count($plan['updates_g1_product']) + count($plan['inserts_g1_product']);
    if ($total > 0) {
        $execParams = 'execute=1&max_change_pct=' . (int) $maxChangePct . ($applyExtremes ? '&apply_extremes=1' : '') . '&scope=' . $scope;
        echo '<p><a class="btn btn-run" href="' . tep_href_link('Actualizador_precios_ram.php', $execParams) . '" onclick="return confirm(\'¿Aplicar ' . $total . ' cambios a la base de datos?\')">Ejecutar ' . $total . ' cambios</a></p>';
    } else echo '<p><em>No hay cambios que aplicar.</em></p>';
}
?>
</div>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
