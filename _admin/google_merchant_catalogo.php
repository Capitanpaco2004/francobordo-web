<?php
/**
 * google_merchant_catalogo.php — Salud del catálogo en Google Merchant Center
 * (cuenta 7605527) vía Merchant API v1, sub-API reports (solo lectura).
 *
 *  1 · Productos DESAPROBADOS ahora mismo, agrupados por motivo (product_view).
 *  2 · Competitividad de precios: tu precio vs benchmark de Google por producto
 *      (price_competitiveness_product_view) + export CSV para cruzar con auto_specials.
 *  3 · Best sellers de tus categorías en ES (best_sellers_*_view, semanal con retardo
 *      ~1 semana → fallback automático de fechas) con flag de si lo tienes en catálogo.
 *
 * El cron diario _admin/scripts/gm_health_cron.php guarda snapshot en
 * /home/francobordo/gm_snapshots/ y avisa por email de nuevos desaprobados;
 * esta página muestra también el estado del último snapshot.
 */
require 'includes/application_top.php';
require_once DIR_FS_CATALOG . 'includes/classes/google_merchant.php';

$gm = new google_merchant();

function gmc_eur_micros($m) {
    if ($m === null || $m === '') return '—';
    return number_format(((float)$m) / 1000000, 2, ',', '.') . ' €';
}
function gmc_admin_link($offerId) {
    $id = (int)$offerId;
    return '<a href="categories.php?pID=' . $id . '&action=new_product" target="_blank">' . $id . '</a>';
}

/* ---------------- consultas ---------------- */

function gmc_disapproved($gm) {
    $q = "SELECT offer_id, id, title, brand, aggregated_reporting_context_status, item_issues "
       . "FROM product_view WHERE aggregated_reporting_context_status = 'NOT_ELIGIBLE_OR_DISAPPROVED'";
    $r = $gm->reportSearch($q, 30000);
    if ($r['code'] !== 200) return array('error' => $r['error']);
    $out = array();
    foreach ((array)$r['data']['results'] as $row) if (isset($row['productView'])) $out[] = $row['productView'];
    return array('rows' => $out);
}

function gmc_competitividad($gm) {
    $q = "SELECT report_country_code, offer_id, id, title, brand, price, benchmark_price "
       . "FROM price_competitiveness_product_view WHERE report_country_code = 'ES'";
    $r = $gm->reportSearch($q, 10000);
    if ($r['code'] !== 200) return array('error' => $r['error']);
    $out = array();
    foreach ((array)$r['data']['results'] as $row) {
        if (!isset($row['priceCompetitivenessProductView'])) continue;
        $v = $row['priceCompetitivenessProductView'];
        $p = isset($v['price']['amountMicros']) ? (float)$v['price']['amountMicros'] : null;
        $b = isset($v['benchmarkPrice']['amountMicros']) ? (float)$v['benchmarkPrice']['amountMicros'] : null;
        if ($p === null || $b === null || $b <= 0) continue;
        $v['diff_pct'] = ($p - $b) / $b * 100.0;
        $out[] = $v;
    }
    return array('rows' => $out);
}

/** Best sellers con fallback de lunes (el informe semanal se publica con retardo). */
function gmc_bestsellers($gm, $view, $fields) {
    $monday = strtotime('last monday', strtotime('-2 days'));
    for ($i = 0; $i < 5; $i++) {
        $d = date('Y-m-d', $monday - $i * 7 * 86400);
        $q = "SELECT report_country_code, report_date, report_granularity, report_category_id, $fields "
           . "FROM $view WHERE report_country_code = 'ES' AND report_granularity = 'WEEKLY' AND report_date = '$d'";
        $r = $gm->reportSearch($q, 3000);
        if ($r['code'] !== 200) return array('error' => $r['error']);
        if (!empty($r['data']['results'])) {
            $out = array();
            foreach ($r['data']['results'] as $row) { $v = reset($row); if (is_array($v)) $out[] = $v; }
            return array('rows' => $out, 'date' => $d);
        }
    }
    return array('rows' => array(), 'date' => null);
}

/* ---------------- export CSV competitividad (antes de cualquier HTML) ---------------- */

if (isset($_GET['action']) && $_GET['action'] === 'csv_comp' && $gm->configured()) {
    $comp = gmc_competitividad($gm);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename=gmc_competitividad_' . date('Ymd') . '.csv');
    $fh = fopen('php://output', 'w');
    fwrite($fh, "\xEF\xBB\xBF");
    fputcsv($fh, array('products_id', 'titulo', 'marca', 'precio_eur', 'benchmark_eur', 'dif_eur', 'dif_pct'), ';', '"', '');
    if (empty($comp['error'])) {
        foreach ($comp['rows'] as $v) {
            $p = (float)$v['price']['amountMicros'] / 1000000;
            $b = (float)$v['benchmarkPrice']['amountMicros'] / 1000000;
            fputcsv($fh, array(
                $v['offerId'],
                $v['title'] ?? '',
                $v['brand'] ?? '',
                number_format($p, 2, ',', ''),
                number_format($b, 2, ',', ''),
                number_format($p - $b, 2, ',', ''),
                number_format($v['diff_pct'], 1, ',', ''),
            ), ';', '"', '');
        }
    }
    fclose($fh);
    exit;
}

/* ---------------- datos para el render ---------------- */

$desap = $comp = $clusters = $brands = null;
if ($gm->configured()) {
    $desap    = gmc_disapproved($gm);
    $comp     = gmc_competitividad($gm);
    $clusters = gmc_bestsellers($gm, 'best_sellers_product_cluster_view',
        'rank, previous_rank, relative_demand, relative_demand_change, title, brand, category_l1, inventory_status, variant_gtins');
    $brands   = gmc_bestsellers($gm, 'best_sellers_brand_view', 'rank, previous_rank, relative_demand, brand');
}

$snapshot = null;
$snapFile = '/home/francobordo/gm_snapshots/latest.json';
if (is_file($snapFile)) $snapshot = json_decode((string)@file_get_contents($snapFile), true);

/* agrupación de issues */
$issues = array();
$nDesap = 0;
if ($desap !== null && empty($desap['error'])) {
    $nDesap = count($desap['rows']);
    foreach ($desap['rows'] as $pv) {
        foreach ((array)($pv['itemIssues'] ?? array()) as $ii) {
            $code = $ii['type']['code'] ?? 'desconocido';
            if (!isset($issues[$code])) {
                $issues[$code] = array('count' => 0, 'sev' => '', 'res' => '', 'prods' => array());
            }
            $issues[$code]['count']++;
            $issues[$code]['sev'] = $ii['severity']['aggregatedSeverity'] ?? $issues[$code]['sev'];
            $issues[$code]['res'] = $ii['resolution'] ?? $issues[$code]['res'];
            if (count($issues[$code]['prods']) < 40) {
                $issues[$code]['prods'][] = array($pv['offerId'] ?? '', $pv['title'] ?? '', $pv['brand'] ?? '');
            }
        }
    }
    uasort($issues, function ($a, $b) { return $b['count'] <=> $a['count']; });
}

/* resumen competitividad */
$compStats = array('n' => 0, 'caro' => 0, 'igual' => 0, 'barato' => 0);
$compCaros = $compBaratos = array();
if ($comp !== null && empty($comp['error'])) {
    $rows = $comp['rows'];
    $compStats['n'] = count($rows);
    foreach ($rows as $v) {
        if ($v['diff_pct'] > 5)       $compStats['caro']++;
        elseif ($v['diff_pct'] < -5)  $compStats['barato']++;
        else                          $compStats['igual']++;
    }
    usort($rows, function ($a, $b) { return $b['diff_pct'] <=> $a['diff_pct']; });
    $compCaros   = array_slice($rows, 0, 50);
    $compBaratos = array_slice(array_reverse($rows), 0, 25);
}
?>
<?php require THEME . 'html/header.php'; ?>
<style>
.gmc-wrap{max-width:1280px;margin:0 auto;padding:12px;font-size:13px}
.gmc-wrap h1{font-size:20px;margin:8px 0 14px}
.gmc-wrap h2{font-size:16px;margin:22px 0 8px;border-bottom:2px solid #3598DB;padding-bottom:4px}
.gmc-kpis{display:flex;gap:14px;flex-wrap:wrap;margin:10px 0}
.gmc-kpi{border:1px solid #d5dbe1;border-radius:6px;background:#fff;padding:10px 16px;text-align:center}
.gmc-kpi b{display:block;font-size:22px}
.gmc-t{border-collapse:collapse;background:#fff;margin-top:6px}
.gmc-t th,.gmc-t td{border:1px solid #e1e6ea;padding:3px 8px;font-size:12px;text-align:left}
.gmc-t th{background:#f4f7f9}
.gmc-t td.num{text-align:right}
.gmc-badge{display:inline-block;border-radius:3px;padding:1px 6px;font-size:11px;color:#fff}
.b-red{background:#c0392b}.b-orange{background:#e67e22}.b-green{background:#27ae60}.b-grey{background:#7f8c8d}
.gmc-msg{padding:8px 12px;border-radius:4px;margin:6px 0}
.gmc-msg.err{background:#fdedec;border:1px solid #c0392b}
.gmc-msg.info{background:#eaf2f8;border:1px solid #3598DB}
.gmc-btn{background:#3598DB;color:#fff;border:0;border-radius:4px;padding:6px 14px;cursor:pointer;font-size:13px;text-decoration:none;display:inline-block}
details.gmc-d{margin:4px 0 10px}
details.gmc-d summary{cursor:pointer}
</style>
<div class="gmc-wrap">
<h1>Google Merchant — Catálogo: salud y mercado (cuenta <?php echo htmlspecialchars($gm->accountId()); ?>)</h1>

<?php if (!$gm->configured()) { ?>
  <div class="gmc-msg err">Falta configuración: <?php echo htmlspecialchars($gm->error()); ?> (ver panel de Tarifas de envío)</div>
<?php } else { ?>

<?php if ($snapshot) { ?>
  <div class="gmc-msg info">Cron diario: último snapshot <b><?php echo htmlspecialchars((string)($snapshot['fecha'] ?? '?')); ?></b>
    — <?php echo (int)($snapshot['total'] ?? 0); ?> desaprobados
    <?php if (isset($snapshot['nuevos'])) echo ' (+' . (int)$snapshot['nuevos'] . ' nuevos, -' . (int)$snapshot['resueltos'] . ' resueltos vs día anterior)'; ?>
  </div>
<?php } ?>

<h2>1 · Productos desaprobados ahora (<?php echo $nDesap; ?>)</h2>
<?php if (!empty($desap['error'])) { ?>
  <div class="gmc-msg err"><?php echo htmlspecialchars($desap['error']); ?></div>
<?php } elseif (!$nDesap) { ?>
  <p>🎉 Ningún producto desaprobado.</p>
<?php } else { ?>
  <table class="gmc-t"><tr><th>Motivo (código)</th><th>Productos</th><th>Gravedad</th><th>Acción</th></tr>
  <?php foreach ($issues as $code => $d) { ?>
    <tr>
      <td><b><?php echo htmlspecialchars($code); ?></b></td>
      <td class="num"><?php echo $d['count']; ?></td>
      <td><?php echo $d['sev'] === 'DISAPPROVED' ? '<span class="gmc-badge b-red">desaprueba</span>' : '<span class="gmc-badge b-orange">' . htmlspecialchars($d['sev']) . '</span>'; ?></td>
      <td><?php echo $d['res'] === 'MERCHANT_ACTION' ? 'requiere tu acción' : htmlspecialchars($d['res']); ?></td>
    </tr>
    <tr><td colspan="4">
      <details class="gmc-d"><summary><?php echo min(40, count($d['prods'])); ?> ejemplos</summary>
        <table class="gmc-t"><tr><th>pID</th><th>Producto</th><th>Marca</th></tr>
        <?php foreach ($d['prods'] as $p) echo '<tr><td>' . gmc_admin_link($p[0]) . '</td><td>' . htmlspecialchars($p[1]) . '</td><td>' . htmlspecialchars($p[2]) . '</td></tr>'; ?>
        </table>
      </details>
    </td></tr>
  <?php } ?>
  </table>
<?php } ?>

<h2>2 · Competitividad de precios (benchmark Google, ES)</h2>
<?php if (!empty($comp['error'])) { ?>
  <div class="gmc-msg err"><?php echo htmlspecialchars($comp['error']); ?></div>
<?php } elseif (!$compStats['n']) { ?>
  <p>Sin datos de benchmark (Google los publica cuando hay suficiente señal de mercado por producto).</p>
<?php } else { ?>
  <div class="gmc-kpis">
    <div class="gmc-kpi"><b><?php echo $compStats['n']; ?></b>productos con benchmark</div>
    <div class="gmc-kpi"><b style="color:#c0392b"><?php echo $compStats['caro']; ?></b>&gt; +5% vs mercado</div>
    <div class="gmc-kpi"><b style="color:#7f8c8d"><?php echo $compStats['igual']; ?></b>±5%</div>
    <div class="gmc-kpi"><b style="color:#27ae60"><?php echo $compStats['barato']; ?></b>&lt; −5% (más baratos)</div>
    <div class="gmc-kpi"><a class="gmc-btn" href="google_merchant_catalogo.php?action=csv_comp">Descargar CSV completo</a><br><span style="font-size:11px;color:#777">para cruzar con auto_specials</span></div>
  </div>

  <h3>Top 50 más caros que el mercado</h3>
  <table class="gmc-t"><tr><th>pID</th><th>Producto</th><th>Marca</th><th>Tu precio</th><th>Benchmark</th><th>Dif.</th></tr>
  <?php foreach ($compCaros as $v) { ?>
    <tr>
      <td><?php echo gmc_admin_link($v['offerId']); ?></td>
      <td><?php echo htmlspecialchars($v['title'] ?? ''); ?></td>
      <td><?php echo htmlspecialchars($v['brand'] ?? ''); ?></td>
      <td class="num"><?php echo gmc_eur_micros($v['price']['amountMicros']); ?></td>
      <td class="num"><?php echo gmc_eur_micros($v['benchmarkPrice']['amountMicros']); ?></td>
      <td class="num" style="color:#c0392b"><b>+<?php echo number_format($v['diff_pct'], 1, ',', ''); ?>%</b></td>
    </tr>
  <?php } ?>
  </table>

  <h3>Top 25 más baratos que el mercado (margen posible)</h3>
  <table class="gmc-t"><tr><th>pID</th><th>Producto</th><th>Marca</th><th>Tu precio</th><th>Benchmark</th><th>Dif.</th></tr>
  <?php foreach ($compBaratos as $v) { ?>
    <tr>
      <td><?php echo gmc_admin_link($v['offerId']); ?></td>
      <td><?php echo htmlspecialchars($v['title'] ?? ''); ?></td>
      <td><?php echo htmlspecialchars($v['brand'] ?? ''); ?></td>
      <td class="num"><?php echo gmc_eur_micros($v['price']['amountMicros']); ?></td>
      <td class="num"><?php echo gmc_eur_micros($v['benchmarkPrice']['amountMicros']); ?></td>
      <td class="num" style="color:#27ae60"><b><?php echo number_format($v['diff_pct'], 1, ',', ''); ?>%</b></td>
    </tr>
  <?php } ?>
  </table>
<?php } ?>

<h2>3 · Best sellers en España (Google Shopping<?php if (!empty($clusters['date'])) echo ', semana del ' . htmlspecialchars($clusters['date']); ?>)</h2>
<?php if (!empty($clusters['error'])) { ?>
  <div class="gmc-msg err"><?php echo htmlspecialchars($clusters['error']); ?></div>
<?php } elseif (empty($clusters['rows'])) { ?>
  <p>Sin datos publicados todavía (el informe semanal sale con ~1 semana de retardo; si persiste, puede requerir activar Market Insights en Merchant Center).</p>
<?php } else {
    $rows = $clusters['rows'];
    usort($rows, function ($a, $b) { return (int)($a['rank'] ?? 0) <=> (int)($b['rank'] ?? 0); });
    $rows = array_slice($rows, 0, 50);
?>
  <p class="gmc-msg info">"En catálogo" lo dice Google comparando los GTIN del cluster con tu feed: <b>NOT_IN_INVENTORY</b> = producto que se vende bien en tu sector y NO tienes.</p>
  <table class="gmc-t"><tr><th>#</th><th>Anterior</th><th>Producto (cluster)</th><th>Marca</th><th>Categoría</th><th>Demanda</th><th>En catálogo</th></tr>
  <?php foreach ($rows as $v) {
      $inv = (string)($v['inventoryStatus'] ?? '');
      $badge = $inv === 'IN_STOCK' ? '<span class="gmc-badge b-green">sí, con stock</span>'
             : ($inv === 'OUT_OF_STOCK' ? '<span class="gmc-badge b-orange">sí, sin stock</span>'
             : '<span class="gmc-badge b-red">no lo vendes</span>');
  ?>
    <tr>
      <td class="num"><?php echo (int)($v['rank'] ?? 0); ?></td>
      <td class="num"><?php echo isset($v['previousRank']) ? (int)$v['previousRank'] : '—'; ?></td>
      <td><?php echo htmlspecialchars($v['title'] ?? ''); ?></td>
      <td><?php echo htmlspecialchars($v['brand'] ?? ''); ?></td>
      <td><?php echo htmlspecialchars($v['categoryL1'] ?? ''); ?></td>
      <td><?php echo htmlspecialchars((string)($v['relativeDemand'] ?? '')); ?></td>
      <td><?php echo $badge; ?></td>
    </tr>
  <?php } ?>
  </table>

  <?php if (!empty($brands['rows'])) {
      $br = $brands['rows'];
      usort($br, function ($a, $b) { return (int)($a['rank'] ?? 0) <=> (int)($b['rank'] ?? 0); });
      $br = array_slice($br, 0, 20);
  ?>
  <h3>Top 20 marcas</h3>
  <table class="gmc-t"><tr><th>#</th><th>Anterior</th><th>Marca</th><th>Demanda relativa</th></tr>
  <?php foreach ($br as $v) { ?>
    <tr>
      <td class="num"><?php echo (int)($v['rank'] ?? 0); ?></td>
      <td class="num"><?php echo isset($v['previousRank']) ? (int)$v['previousRank'] : '—'; ?></td>
      <td><?php echo htmlspecialchars($v['brand'] ?? ''); ?></td>
      <td><?php echo htmlspecialchars((string)($v['relativeDemand'] ?? '')); ?></td>
    </tr>
  <?php } ?>
  </table>
  <?php } ?>
<?php } ?>

<?php } /* configured */ ?>
<p style="color:#777;font-size:11px;margin-top:20px">Solo lectura (sub-API reports de Merchant API v1). El detalle de cada desaprobación está en Merchant Center → Productos. Cron diario de vigilancia: _admin/scripts/gm_health_cron.php (07:25).</p>
</div>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
