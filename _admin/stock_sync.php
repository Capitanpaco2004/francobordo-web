<?php
/**
 * _admin/stock_sync.php
 *
 * Dashboard de la sincronización de stock VStock → Francobordo.
 * Lee de la tabla stock_sync_runs alimentada por api-stock-sync-status.php.
 */
require 'includes/application_top.php';

// Latest run + last 50 for the trend table
$lastRun = tep_db_fetch_array(tep_db_query(
    "SELECT * FROM stock_sync_runs ORDER BY started_at DESC LIMIT 1"
));

// Fetch friendly product info for the not-found-variant SKUs (so the user
// can see what each missing combination IS rather than just an opaque code)
$skuNotFoundList = [];
$variantNotFoundList = [];
if ($lastRun) {
    if (!empty($lastRun['sku_not_found_sample'])) {
        $decoded = json_decode($lastRun['sku_not_found_sample'], true);
        if (is_array($decoded)) { $skuNotFoundList = $decoded; }
    }
    if (!empty($lastRun['variant_not_found_sample'])) {
        $decoded = json_decode($lastRun['variant_not_found_sample'], true);
        if (is_array($decoded)) { $variantNotFoundList = $decoded; }
    }
}
// Resolve product names for variant-not-found SKUs (those DO exist in products)
$variantSkuToName = [];
if (!empty($variantNotFoundList)) {
    $skus = array_map(fn($v) => is_array($v) ? ($v['sku'] ?? '') : '', $variantNotFoundList);
    $skus = array_filter(array_unique($skus));
    if (!empty($skus)) {
        $escaped = array_map(fn($s) => "'" . tep_db_input($s) . "'", $skus);
        $sql = "SELECT p.CCODIART, pd.products_name FROM products p
                LEFT JOIN products_description pd ON pd.products_id = p.products_id AND pd.language_id = 3
                WHERE p.CCODIART IN (" . implode(',', $escaped) . ")";
        $q = tep_db_query($sql);
        while ($r = tep_db_fetch_array($q)) {
            $variantSkuToName[$r['CCODIART']] = $r['products_name'];
        }
    }
}
$qHistory = tep_db_query(
    "SELECT run_id, started_at, finished_at, elapsed_ms, dry_run,
            lines_received, no_change, product_qty_updated, variant_qty_updated,
            preserved_sentinel, sku_not_found, variant_not_found
     FROM stock_sync_runs ORDER BY started_at DESC LIMIT 50"
);
$history = [];
while ($r = tep_db_fetch_array($qHistory)) { $history[] = $r; }

$qStats24 = tep_db_query(
    "SELECT
        COUNT(*) AS runs,
        SUM(product_qty_updated) AS total_prod_upd,
        SUM(variant_qty_updated) AS total_var_upd,
        SUM(preserved_sentinel) AS total_preserved,
        AVG(elapsed_ms) AS avg_ms
     FROM stock_sync_runs
     WHERE started_at >= NOW() - INTERVAL 24 HOUR"
);
$stats24 = tep_db_fetch_array($qStats24);

$diffSample = [];
if ($lastRun && !empty($lastRun['diff_sample'])) {
    $decoded = json_decode($lastRun['diff_sample'], true);
    if (is_array($decoded)) { $diffSample = $decoded; }
}

function fmt_ago($datetime) {
    if (!$datetime) return '—';
    $diff = time() - strtotime($datetime);
    if ($diff < 0) return $datetime;
    if ($diff < 60) return $diff . ' s';
    if ($diff < 3600) return floor($diff / 60) . ' min';
    if ($diff < 86400) return floor($diff / 3600) . ' h';
    return floor($diff / 86400) . ' d';
}

function diff_kind_label($kind) {
    return match ($kind) {
        'variant' => 'variante',
        'product_total' => 'producto (total)',
        'product_simple' => 'producto',
        default => $kind,
    };
}

function classify_change($d) {
    $old = $d['old_qty'] ?? null;
    $new = $d['new_qty'] ?? null;
    if ($old === null) return ['nuevo', '#666'];
    $oldF = (float)$old; $newF = (float)$new;
    if ($oldF < 0 && $newF > 0) return ['🟢 stock real', '#22a06b'];
    if (abs($oldF - 2000) < 0.01 && $newF > 0) return ['🟢 stock real', '#22a06b'];
    if ($oldF > $newF)        return ['↘ venta', '#d23a3a'];
    if ($oldF < $newF)        return ['↗ entrada', '#3373c4'];
    return ['= igual', '#888'];
}
?>
<?php require THEME . 'html/header.php'; ?>

<style>
.sync-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin: 16px 0; }
.sync-card { background:#fff; border:1px solid #e4e4e4; border-radius:8px; padding:14px 16px; }
.sync-card h4 { margin:0 0 4px 0; font-size:11px; color:#666; text-transform:uppercase; letter-spacing:0.5px; font-weight:600; }
.sync-card .v { font-size:24px; font-weight:600; color:#222; }
.sync-card .sub { font-size:11px; color:#999; margin-top:2px; }
.sync-card.ok .v { color:#22a06b; }
.sync-card.warn .v { color:#d28b1f; }
.sync-card.bad .v { color:#d23a3a; }
.sync-table { width:100%; border-collapse:collapse; margin-top:8px; font-size:13px; }
.sync-table th, .sync-table td { padding:8px 10px; border-bottom:1px solid #f0f0f0; text-align:left; }
.sync-table th { background:#f9f9f9; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#666; }
.sync-table tr:hover td { background:#fafafa; }
.sync-pill { display:inline-block; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:600; color:#fff; }
.sync-pill.dry { background:#f0a020; }
.sync-pill.live { background:#22a06b; }
.sync-section h3 { margin:24px 0 8px 0; font-size:16px; }
.sync-mono { font-family: ui-monospace, Menlo, Consolas, monospace; font-size:12px; }
</style>

<div style="padding:18px 24px;">
<h1 style="margin:0 0 4px 0;">Sincronización Stock VStock → Web</h1>
<p style="color:#777; margin:0 0 8px 0;">
    Snapshot de la última corrida del cron (cada 5 min) en <span class="sync-mono">192.168.1.50</span>.
    En modo <strong>dry-run</strong> los UPDATEs no se aplican, sólo se computan diffs.
</p>

<?php if (!$lastRun): ?>
    <div style="padding:30px; background:#fff5e6; border:1px solid #f0a020; border-radius:8px; margin:16px 0;">
        Aún no hay corridas registradas. El primer ciclo del cron se ejecuta cada 5 min.
    </div>
<?php else: ?>

<!-- KPIs de la última corrida -->
<div class="sync-section">
    <h3>Última corrida (<?= fmt_ago($lastRun['started_at']) ?> · <?= $lastRun['elapsed_ms'] ?> ms)
        <?php if ($lastRun['dry_run']): ?>
            <span class="sync-pill dry">DRY-RUN</span>
        <?php else: ?>
            <span class="sync-pill live">EN VIVO</span>
        <?php endif; ?>
    </h3>
    <div class="sync-grid">
        <div class="sync-card"><h4>Líneas recibidas de VStock</h4><div class="v"><?= number_format($lastRun['lines_received']) ?></div></div>
        <div class="sync-card"><h4>Sin cambios</h4><div class="v"><?= number_format($lastRun['no_change']) ?></div></div>
        <div class="sync-card"><h4>Variantes actualizadas</h4><div class="v"><?= number_format($lastRun['variant_qty_updated']) ?></div></div>
        <div class="sync-card"><h4>Productos actualizados (total)</h4><div class="v"><?= number_format($lastRun['product_qty_updated']) ?></div></div>
        <div class="sync-card warn"><h4>Centinelas preservados</h4><div class="v"><?= number_format($lastRun['preserved_sentinel']) ?></div><div class="sub">−900 / −800 / 2000…</div></div>
        <div class="sync-card bad"><h4>SKU no encontrados</h4><div class="v"><?= number_format($lastRun['sku_not_found']) ?></div><div class="sub">No existen en products.CCODIART</div></div>
        <div class="sync-card bad"><h4>Variantes no encontradas</h4><div class="v"><?= number_format($lastRun['variant_not_found']) ?></div><div class="sub">No existen en products_stock</div></div>
    </div>
</div>

<!-- Stats últimas 24h -->
<div class="sync-section">
    <h3>Últimas 24 horas</h3>
    <div class="sync-grid">
        <div class="sync-card"><h4>Corridas ejecutadas</h4><div class="v"><?= number_format($stats24['runs'] ?? 0) ?></div><div class="sub">esperado: ~288</div></div>
        <div class="sync-card"><h4>Σ Productos actualizados</h4><div class="v"><?= number_format($stats24['total_prod_upd'] ?? 0) ?></div></div>
        <div class="sync-card"><h4>Σ Variantes actualizadas</h4><div class="v"><?= number_format($stats24['total_var_upd'] ?? 0) ?></div></div>
        <div class="sync-card"><h4>Σ Centinelas preservados</h4><div class="v"><?= number_format($stats24['total_preserved'] ?? 0) ?></div></div>
        <div class="sync-card"><h4>Tiempo medio por run</h4><div class="v"><?= number_format(($stats24['avg_ms'] ?? 0) / 1000, 1) ?> s</div></div>
    </div>
</div>

<!-- Sample diffs de la última corrida -->
<?php if (!empty($diffSample)): ?>
<div class="sync-section">
    <h3>Muestra de cambios (última corrida) — <?= count($diffSample) ?> de hasta 50</h3>
    <table class="sync-table">
        <thead>
        <tr>
            <th>Tipo</th><th>SKU</th><th>Variante</th>
            <th style="text-align:right;">Old</th><th style="text-align:right;">New</th>
            <th>Clasificación</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($diffSample as $d):
            $kind = $d['kind'] ?? '';
            $sku  = $d['sku'] ?? ('id=' . ($d['products_id'] ?? '?'));
            $props = isset($d['props']) ? implode(' · ', $d['props']) : '—';
            [$label, $color] = classify_change($d);
        ?>
            <tr>
                <td><span class="sync-mono"><?= htmlspecialchars(diff_kind_label($kind)) ?></span></td>
                <td class="sync-mono"><?= htmlspecialchars((string)$sku) ?></td>
                <td><?= htmlspecialchars((string)$props) ?></td>
                <td style="text-align:right;" class="sync-mono"><?= htmlspecialchars((string)($d['old_qty'] ?? '—')) ?></td>
                <td style="text-align:right;" class="sync-mono"><strong><?= htmlspecialchars((string)($d['new_qty'] ?? '—')) ?></strong></td>
                <td style="color:<?= $color ?>; font-weight:600;"><?= $label ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- SKU no encontrados -->
<?php if (!empty($skuNotFoundList)): ?>
<div class="sync-section">
    <details<?= $lastRun['sku_not_found'] > 0 ? '' : '' ?> style="background:#fff; border:1px solid #e4e4e4; border-radius:8px; padding:12px 16px;">
        <summary style="cursor:pointer; font-weight:600; font-size:15px;">
            🔴 SKUs no encontrados en <span class="sync-mono">products.CCODIART</span>
            — <?= count($skuNotFoundList) ?> de muestra (de <?= number_format($lastRun['sku_not_found']) ?> totales)
        </summary>
        <p style="color:#666; margin:10px 0 8px;">
            Estos SKU existen en VStock pero NO en la tabla <code>products</code>. Casos típicos:
            productos descatalogados que sólo viven en VStock, o productos nuevos pendientes
            de bajar vía <code>qfacwin_insert.php</code>. La sync los ignora.
        </p>
        <div style="max-height:300px; overflow-y:auto; font-family: ui-monospace, Menlo, monospace; font-size:12px; background:#f9f9f9; border-radius:4px; padding:10px; column-count:4; column-gap: 18px;">
            <?php foreach ($skuNotFoundList as $sku): ?>
                <div style="break-inside:avoid;"><?= htmlspecialchars((string)$sku) ?></div>
            <?php endforeach; ?>
        </div>
    </details>
</div>
<?php endif; ?>

<!-- Variantes no encontradas -->
<?php if (!empty($variantNotFoundList)): ?>
<div class="sync-section">
    <details style="background:#fff; border:1px solid #e4e4e4; border-radius:8px; padding:12px 16px;">
        <summary style="cursor:pointer; font-weight:600; font-size:15px;">
            🟠 Variantes no encontradas en <span class="sync-mono">products_stock</span>
            — <?= count($variantNotFoundList) ?> de muestra (de <?= number_format($lastRun['variant_not_found']) ?> totales)
        </summary>
        <p style="color:#666; margin:10px 0 8px;">
            El SKU base SÍ existe en <code>products</code>, pero la combinación de variantes
            (CCODIVAL_1/2/3) no tiene fila en <code>products_stock</code>. Posibles causas:
            variante creada en VStock pero no aún en MySQL, código de variante distinto
            entre sistemas, o variante eliminada de la web. La sync los ignora.
        </p>
        <table class="sync-table">
            <thead><tr><th>SKU</th><th>Producto</th><th>Variante (CCODIVAL)</th></tr></thead>
            <tbody>
            <?php foreach ($variantNotFoundList as $v): ?>
                <?php $sku = is_array($v) ? ($v['sku'] ?? '') : ''; $props = is_array($v) ? ($v['props'] ?? []) : []; ?>
                <tr>
                    <td class="sync-mono"><?= htmlspecialchars((string)$sku) ?></td>
                    <td><?= htmlspecialchars((string)($variantSkuToName[$sku] ?? '—')) ?></td>
                    <td class="sync-mono"><?= htmlspecialchars(implode(' · ', $props)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </details>
</div>
<?php endif; ?>

<!-- Histórico de las últimas 50 corridas -->
<div class="sync-section">
    <h3>Histórico (últimas 50 corridas)</h3>
    <table class="sync-table">
        <thead>
        <tr>
            <th>Inicio</th><th>Modo</th>
            <th style="text-align:right;">ms</th>
            <th style="text-align:right;">Líneas</th>
            <th style="text-align:right;">Sin cambios</th>
            <th style="text-align:right;">Var. UPD</th>
            <th style="text-align:right;">Prod. UPD</th>
            <th style="text-align:right;">Sent.</th>
            <th style="text-align:right;">SKU NF</th>
            <th style="text-align:right;">Var. NF</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($history as $h): ?>
            <tr>
                <td class="sync-mono"><?= htmlspecialchars($h['started_at']) ?></td>
                <td>
                    <?php if ($h['dry_run']): ?>
                        <span class="sync-pill dry">DRY</span>
                    <?php else: ?>
                        <span class="sync-pill live">LIVE</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:right;"><?= number_format($h['elapsed_ms']) ?></td>
                <td style="text-align:right;"><?= number_format($h['lines_received']) ?></td>
                <td style="text-align:right;color:#999;"><?= number_format($h['no_change']) ?></td>
                <td style="text-align:right;"><?= number_format($h['variant_qty_updated']) ?></td>
                <td style="text-align:right;"><?= number_format($h['product_qty_updated']) ?></td>
                <td style="text-align:right;color:#d28b1f;"><?= number_format($h['preserved_sentinel']) ?></td>
                <td style="text-align:right;color:#d23a3a;"><?= number_format($h['sku_not_found']) ?></td>
                <td style="text-align:right;color:#d23a3a;"><?= number_format($h['variant_not_found']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>
</div>

<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
