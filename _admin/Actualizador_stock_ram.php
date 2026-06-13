<?php
require 'includes/application_top.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);

/* ──────────────────────────────────────────────────────────────────────────
 * Actualizador de STOCK RAM Mounts
 *
 * Fuente: /descargas/gamp/MSI.csv (lo sube RAM por FTP).
 *   Formato: separador ';', cabecera "#;Cod Articulo;Descripcion;Cod EAN;PVP;DTO;Stock;Precio Coste;Cliente".
 *   Col B (idx 1) = Cod Articulo (SKU) · Col D (idx 3) = EAN (UPC-A 12 díg) · Col G (idx 6) = Stock RAM.
 *
 * Reglas (usuario 2026-06-12):
 *   - Nuestro stock > 0            → NO se toca (stock real propio).
 *   - Nuestro stock <= 0 y RAM SÍ tiene stock → products_quantity = -100 (sentinel "en proveedor").
 *   - Nuestro stock <= 0 y RAM NO tiene stock → products_quantity = -800 (sentinel "bajo pedido").
 *   - Producto RAM sin fila en el CSV → NO se toca (se lista como informativo).
 *
 * Match: products_model ↔ Cod Articulo (fallback product_ean ↔ UPC-A→EAN-13).
 * Solo toca products_quantity. Precios/estado NO se tocan. PLAN/EXECUTE en transacción.
 * ────────────────────────────────────────────────────────────────────────── */

const CSV_PATH    = '/home/francobordo/public_html/descargas/gamp/MSI.csv';
const MFG_NAME    = 'RAM Mounts';
const STOCK_PROVEEDOR  = -100;   // RAM tiene stock → servible desde proveedor
const STOCK_BAJOPEDIDO = -800;   // RAM sin stock → bajo pedido

function ean13Checksum($p) { if (strlen($p) !== 12 || !ctype_digit($p)) return -1; $s = 0; for ($i = 0; $i < 12; $i++) { $d = (int) $p[$i]; $s += ($i % 2 === 0) ? $d : $d * 3; } return (10 - ($s % 10)) % 10; }
function isValidEan13($e) { $e = trim((string) $e); if (strlen($e) !== 13 || !ctype_digit($e)) return false; return ean13Checksum(substr($e, 0, 12)) === (int) $e[12]; }
function ramUpcToEan13($raw) { $d = preg_replace('/\D/', '', (string) $raw); if ($d === '') return ''; if (strlen($d) === 11) $d = '0' . $d; if (strlen($d) === 12) { $e = '0' . $d; return isValidEan13($e) ? $e : ''; } if (strlen($d) === 13) return isValidEan13($d) ? $d : ''; return ''; }

$dryRun = !isset($_GET['execute']);

/** CSV → ['by_sku'=>[SKU=>stock], 'by_ean'=>[EAN13=>stock]]. */
function parseCsv($path, &$err) {
    $err = '';
    if (!file_exists($path)) { $err = 'No existe ' . $path; return ['by_sku' => [], 'by_ean' => []]; }
    $fh = fopen($path, 'r');
    if (!$fh) { $err = 'No se pudo abrir ' . $path; return ['by_sku' => [], 'by_ean' => []]; }
    $bySku = []; $byEan = [];
    while (($row = fgetcsv($fh, 0, ';', '"', '\\')) !== false) {
        $sku = strtoupper(trim((string) ($row[1] ?? '')));
        if ($sku === '' || $sku === 'COD ARTICULO') continue;                    // cabecera / vacías
        if (!ctype_digit(trim((string) ($row[0] ?? '')))) continue;             // solo filas numeradas
        $stock = (int) str_replace(',', '.', trim((string) ($row[6] ?? '0')));
        // si el SKU se repite, conserva el stock mayor (defensivo)
        if (!isset($bySku[$sku]) || $stock > $bySku[$sku]) $bySku[$sku] = $stock;
        $ean = ramUpcToEan13($row[3] ?? '');
        if ($ean !== '' && (!isset($byEan[$ean]) || $stock > $byEan[$ean])) $byEan[$ean] = $stock;
    }
    fclose($fh);
    return ['by_sku' => $bySku, 'by_ean' => $byEan];
}

function ramMfgId() { $r = tep_db_query("SELECT manufacturers_id FROM manufacturers WHERE manufacturers_name='" . tep_db_input(MFG_NAME) . "' LIMIT 1"); $row = tep_db_fetch_array($r); return $row ? (int) $row['manufacturers_id'] : 0; }

function loadProducts($mfgId) {
    $rows = [];
    $f = $mfgId > 0 ? "(manufacturers_id = $mfgId OR products_import_origin LIKE 'ram%')" : "(products_import_origin LIKE 'ram%')";
    $r = tep_db_query("SELECT products_id, products_model, product_ean, products_quantity, products_status FROM products WHERE $f");
    while ($p = tep_db_fetch_array($r)) {
        $pid = (int) $p['products_id'];
        $rows[$pid] = ['pid' => $pid, 'model' => strtoupper(trim((string) $p['products_model'])), 'ean' => trim((string) $p['product_ean']), 'qty' => (int) $p['products_quantity'], 'status' => (int) $p['products_status']];
    }
    return $rows;
}

function buildPlan(array $prods, array $csv) {
    $plan = ['updates' => [], 'skipped_own_stock' => 0, 'skipped_unchanged' => 0, 'no_match' => []];
    foreach ($prods as $p) {
        if ($p['qty'] > 0) { $plan['skipped_own_stock']++; continue; }          // stock real propio → intocable
        $ramStock = $csv['by_sku'][$p['model']] ?? null;
        if ($ramStock === null && $p['ean'] !== '') $ramStock = $csv['by_ean'][$p['ean']] ?? null;
        if ($ramStock === null) { $plan['no_match'][] = $p['model']; continue; } // sin fila en CSV → no se toca
        $target = $ramStock > 0 ? STOCK_PROVEEDOR : STOCK_BAJOPEDIDO;
        if ($p['qty'] === $target) { $plan['skipped_unchanged']++; continue; }
        $plan['updates'][] = ['pid' => $p['pid'], 'model' => $p['model'], 'old' => $p['qty'], 'new' => $target, 'ram_stock' => $ramStock];
    }
    return $plan;
}

function applyPlan(array $plan) {
    foreach ($plan['updates'] as $u) {
        tep_db_query("UPDATE products SET products_quantity = " . (int) $u['new'] . ", products_last_modified = NOW() WHERE products_id = " . (int) $u['pid'] . " AND products_quantity <= 0");
    }
}
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
<h1>Actualizador de STOCK RAM Mounts</h1>
<p>Modo: <span class="<?php echo $dryRun ? 'badge-dry' : 'badge-exec'; ?>"><?php echo $dryRun ? 'DRY RUN (no escribe)' : 'EJECUTANDO'; ?></span></p>
<p style="background:#fffbe6;border:1px solid #ffd700;padding:8px;border-radius:4px;">
    <strong>Reglas</strong>: stock propio &gt; 0 → no se toca · stock &le; 0 y RAM con stock → <code><?php echo STOCK_PROVEEDOR; ?></code> (en proveedor) · stock &le; 0 y RAM sin stock → <code><?php echo STOCK_BAJOPEDIDO; ?></code> (bajo pedido) · sin fila en CSV → no se toca. Solo cambia <code>products_quantity</code>.
</p>
<?php
$err = '';
$csv = parseCsv(CSV_PATH, $err);
if ($err !== '') { echo '<p style="color:red"><strong>' . htmlspecialchars($err) . '</strong></p></div>'; require THEME . 'html/footer.php'; require DIR_WS_INCLUDES . 'application_bottom.php'; exit; }
echo '<p>Fichero: <code>' . htmlspecialchars(CSV_PATH) . '</code> <span class="small">(modificado ' . date('Y-m-d H:i', filemtime(CSV_PATH)) . ')</span> — <strong>' . count($csv['by_sku']) . '</strong> SKUs';
$conStock = 0; foreach ($csv['by_sku'] as $s) if ($s > 0) $conStock++;
echo ' (' . $conStock . ' con stock en RAM, ' . (count($csv['by_sku']) - $conStock) . ' sin stock)</p>';

$mfgId = ramMfgId();
$prods = loadProducts($mfgId);
echo '<p>Productos RAM en BD: <strong>' . count($prods) . '</strong> <span class="small">(mfg id=' . $mfgId . ')</span></p>';

$plan = buildPlan($prods, $csv);
?>
<h2>Resumen</h2>
<ul>
    <li>UPDATE <code>products_quantity</code>: <strong><?php echo count($plan['updates']); ?></strong>
        <?php $nProv = 0; $nBp = 0; foreach ($plan['updates'] as $u) { if ($u['new'] === STOCK_PROVEEDOR) $nProv++; else $nBp++; } ?>
        (<?php echo $nProv; ?> → <?php echo STOCK_PROVEEDOR; ?> en proveedor, <?php echo $nBp; ?> → <?php echo STOCK_BAJOPEDIDO; ?> bajo pedido)</li>
    <li class="small">Con stock propio &gt; 0 (intocables): <?php echo $plan['skipped_own_stock']; ?> | ya en el valor correcto: <?php echo $plan['skipped_unchanged']; ?> | sin fila en CSV (no se tocan): <?php echo count($plan['no_match']); ?></li>
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
renderTable('Cambios de stock', $plan['updates'], [
    ['pid', fn($r) => $r['pid']], ['model', fn($r) => $r['model']],
    ['stock actual', fn($r) => $r['old'], 'num'], ['stock nuevo', fn($r) => $r['new'], 'num'],
    ['stock RAM (CSV)', fn($r) => $r['ram_stock'], 'num'],
]);
$noMatchRows = array_map(fn($c) => ['code' => $c], array_values(array_unique($plan['no_match'])));
renderTable('Sin fila en el CSV (informativo, no se tocan)', $noMatchRows, [['code', fn($r) => $r['code']]]);

if (!$dryRun) {
    $t0 = microtime(true);
    tep_db_query('START TRANSACTION');
    try { applyPlan($plan); tep_db_query('COMMIT'); echo '<p style="color:#393;font-weight:bold">✔ ' . count($plan['updates']) . ' cambios aplicados en ' . round(microtime(true) - $t0, 2) . 's.</p>'; }
    catch (\Throwable $e) { tep_db_query('ROLLBACK'); echo '<p style="color:#c33;font-weight:bold">✘ Error: ' . htmlspecialchars($e->getMessage()) . ' — ROLLBACK.</p>'; }
    echo '<p><a class="btn btn-back" href="' . tep_href_link('Actualizador_stock_ram.php') . '">Volver a dry-run</a></p>';
} else {
    if (count($plan['updates']) > 0) {
        echo '<p><a class="btn btn-run" href="' . tep_href_link('Actualizador_stock_ram.php', 'execute=1') . '" onclick="return confirm(\'¿Aplicar ' . count($plan['updates']) . ' cambios de stock?\')">Ejecutar ' . count($plan['updates']) . ' cambios</a></p>';
    } else echo '<p><em>No hay cambios que aplicar.</em></p>';
}
?>
</div>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
