<?php
// Fase 1 — auto_specials_preview.php
// Vista read-only de candidatos a oferta basada en qfac_sales_velocity.
// El motor de descuento (Fase 2-4) se añadirá encima.
require 'includes/application_top.php';

// ---- Filtros ---------------------------------------------------------------
$f_search    = trim($_GET['q'] ?? '');
$f_cat       = (int)($_GET['cat'] ?? 0);
$f_mfg       = (int)($_GET['mfg'] ?? 0);
$f_stock_min = isset($_GET['stock_min']) ? (int)$_GET['stock_min'] : 1;
$f_days_min  = isset($_GET['days_min']) ? (int)$_GET['days_min'] : 180;
$f_inc_never = isset($_GET['inc_never']) ? 1 : 1; // por defecto incluir nunca-vendidos
$f_cstock    = isset($_GET['cstock']) ? (int)$_GET['cstock'] : 0;       // 1 = solo check_stock
$f_liquid    = isset($_GET['liquid']) ? (int)$_GET['liquid'] : 0;       // 1 = solo en liquidación
$f_pvp_min   = isset($_GET['pvp_min']) && $_GET['pvp_min'] !== '' ? (float)$_GET['pvp_min'] : 0.0;
$f_order     = $_GET['order'] ?? 'priority';
$page        = max(1, (int)($_GET['p'] ?? 1));
$per_page    = 50;

$where = ['p.products_status = 1', 'v.stock_variant >= ' . $f_stock_min];
if ($f_days_min > 0) {
    if ($f_inc_never) {
        $where[] = '(v.dias_sin_venta IS NULL OR v.dias_sin_venta >= ' . $f_days_min . ')';
    } else {
        $where[] = 'v.dias_sin_venta >= ' . $f_days_min;
    }
}
if ($f_search !== '') {
    $qe = tep_db_input($f_search);
    $is_num = ctype_digit($f_search);
    $pid_clause = $is_num ? "OR p.products_id = '{$qe}'" : '';
    $where[] = "(p.products_model LIKE '%{$qe}%' OR p.CCODIART LIKE '%{$qe}%' "
             . "OR pd.products_name LIKE '%{$qe}%' OR v.ccodival LIKE '%{$qe}%' {$pid_clause})";
}
if ($f_cat > 0) {
    $where[] = "EXISTS (SELECT 1 FROM products_to_categories pc "
             . "WHERE pc.products_id=p.products_id AND pc.categories_id={$f_cat})";
}
if ($f_mfg > 0) {
    $where[] = "p.manufacturers_id = {$f_mfg}";
}
if ($f_cstock === 1) $where[] = "p.check_stock = 1";
if ($f_liquid === 1) $where[] = "p.products_liquidacion = 1";

// PVP efectivo de la variante: padre + delta de options_values_price
$pvp_effective = "(p.products_price + COALESCE(
    CASE WHEN pa.price_prefix='-' THEN -pa.options_values_price
         ELSE pa.options_values_price END, 0))";

if ($f_pvp_min > 0) $where[] = "{$pvp_effective} >= " . sprintf('%.4f', $f_pvp_min);
$where_sql = implode(' AND ', $where);

$order_map = [
    'priority'    => 'v.stock_variant * COALESCE(v.dias_sin_venta, 9999) DESC',
    'stock'       => 'v.stock_variant DESC',
    'days'        => 'v.dias_sin_venta IS NULL ASC, v.dias_sin_venta DESC, v.stock_variant DESC',
    'cob90'       => 'CASE WHEN v.ventas_90d > 0 THEN v.stock_variant / (v.ventas_90d/90) ELSE 999999 END DESC',
    'cob180'      => 'CASE WHEN v.ventas_180d > 0 THEN v.stock_variant / (v.ventas_180d/180) ELSE 999999 END DESC',
    'cob365'      => 'CASE WHEN v.ventas_365d > 0 THEN v.stock_variant / (v.ventas_365d/365) ELSE 999999 END DESC',
    'pvp'         => "{$pvp_effective} DESC",
    'cost'        => 'p.products_cost DESC',
    'imp_lost'    => "(v.stock_variant * {$pvp_effective}) DESC",
    'coste_stock' => '(v.stock_variant * COALESCE(NULLIF(p.products_cost,0), 0)) DESC',
];
$order_sql = $order_map[$f_order] ?? $order_map['priority'];

// ---- Conteo + page ---------------------------------------------------------
$count_sql = "SELECT COUNT(*) AS n
              FROM products p
              JOIN qfac_sales_velocity v ON v.products_id=p.products_id
              LEFT JOIN products_attributes pa
                ON pa.products_id=p.products_id AND pa.options_values_id=v.options_values_id
              LEFT JOIN products_description pd ON pd.products_id=p.products_id AND pd.language_id=3
              WHERE {$where_sql}";
$total = (int) tep_db_fetch_array(tep_db_query($count_sql))['n'];
$pages = max(1, (int) ceil($total / $per_page));
if ($page > $pages) $page = $pages;
$offset = ($page - 1) * $per_page;

// ---- Filas -----------------------------------------------------------------
$rows_sql = "
    SELECT p.products_id, p.products_model, p.CCODIART, p.products_image,
           p.products_price, p.products_cost, p.manufacturers_id,
           p.check_stock, p.products_liquidacion,
           m.manufacturers_name,
           pd.products_name,
           v.options_values_id, v.ccodival,
           v.stock_variant, v.ultima_venta, v.dias_sin_venta,
           v.ventas_30d, v.ventas_90d, v.ventas_180d, v.ventas_365d,
           v.importe_365d,
           CASE WHEN v.ventas_90d  > 0 THEN ROUND(v.stock_variant / (v.ventas_90d /90 ),0) END AS cob_90,
           CASE WHEN v.ventas_180d > 0 THEN ROUND(v.stock_variant / (v.ventas_180d/180),0) END AS cob_180,
           CASE WHEN v.ventas_365d > 0 THEN ROUND(v.stock_variant / (v.ventas_365d/365),0) END AS cob_365,
           pa.options_values_price, pa.price_prefix,
           {$pvp_effective} AS pvp_var,
           pov.products_options_values_name AS variant_name,
           (SELECT s.specials_new_products_price FROM specials s
            WHERE s.products_id=p.products_id AND s.status=1 LIMIT 1) AS special_price
    FROM products p
    JOIN qfac_sales_velocity v ON v.products_id=p.products_id
    LEFT JOIN products_attributes pa
      ON pa.products_id=p.products_id AND pa.options_values_id=v.options_values_id
    LEFT JOIN products_options_values pov
      ON pov.products_options_values_id=v.options_values_id AND pov.language_id=3
    LEFT JOIN products_description pd ON pd.products_id=p.products_id AND pd.language_id=3
    LEFT JOIN manufacturers m ON m.manufacturers_id=p.manufacturers_id
    WHERE {$where_sql}
    ORDER BY {$order_sql}
    LIMIT {$offset}, {$per_page}";
$rows_q = tep_db_query($rows_sql);

// ---- Listas para filtros ---------------------------------------------------
$cat_q = tep_db_query("
    SELECT c.categories_id, cd.categories_name
    FROM categories c
    JOIN categories_description cd ON cd.categories_id=c.categories_id AND cd.language_id=3
    WHERE c.parent_id = 0
    ORDER BY cd.categories_name
");
$mfg_q = tep_db_query("
    SELECT manufacturers_id, manufacturers_name
    FROM manufacturers
    ORDER BY manufacturers_name
");

// ---- Sumario stats (sólo del filtro actual, no de la página) --------------
$summary = tep_db_fetch_array(tep_db_query("
    SELECT COUNT(*) AS n_prods,
           SUM(v.stock_variant) AS sum_stock,
           SUM(v.stock_variant * {$pvp_effective}) AS valor_stock,
           SUM(v.importe_365d) AS imp_365
    FROM products p
    JOIN qfac_sales_velocity v ON v.products_id=p.products_id
    LEFT JOIN products_attributes pa
      ON pa.products_id=p.products_id AND pa.options_values_id=v.options_values_id
    LEFT JOIN products_description pd ON pd.products_id=p.products_id AND pd.language_id=3
    WHERE {$where_sql}
"));

$calc_row = tep_db_fetch_array(tep_db_query(
    "SELECT MAX(calculado_en) AS t FROM qfac_sales_velocity"
));
$calc_en = $calc_row['t'] ?? '—';

function fmt_d($n) { return $n === null ? '—' : (string)$n; }
function fmt_n($n, $d=0) { return $n === null ? '—' : number_format((float)$n, $d, ',', '.'); }

function order_link($key, $label, $f_order) {
    $is_active = ($f_order === $key);
    $qs = $_GET;
    $qs['order'] = $key;
    unset($qs['p']);
    $href = '?' . htmlspecialchars(http_build_query($qs));
    $arrow = $is_active ? ' <span class="as-arrow">↓</span>' : '';
    $cls = 'as-th-link' . ($is_active ? ' active' : '');
    return '<a href="'.$href.'" class="'.$cls.'">'.htmlspecialchars($label).$arrow.'</a>';
}
?>
<?php require THEME . 'html/header.php'; ?>

<style>
  .as-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
  .as-num  { font-variant-numeric: tabular-nums; font-feature-settings: "tnum"; }
  .as-table { font-size: 12px; border-collapse: separate; border-spacing: 0; }
  .as-table thead th {
    position: sticky; top: 0; z-index: 5;
    background: #f8fafc; border-bottom: 2px solid #cbd5e1;
    font-weight: 600; color: #334155; white-space: nowrap;
    padding: 8px 6px;
  }
  .as-table td { padding: 6px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
  .as-table tbody tr:hover { background: #f0f9ff; }
  .as-table tbody tr:nth-child(even) { background: #fcfcfd; }
  .as-table tbody tr:nth-child(even):hover { background: #f0f9ff; }
  .as-table img.thumb { width: 44px; height: 44px; object-fit: contain; border-radius: 4px; background:#fff; border:1px solid #e5e7eb; }
  .as-name { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; max-width: 260px; line-height: 1.25; }
  .as-th-link { color: inherit; text-decoration: none; cursor: pointer; display: inline-block; padding: 2px 4px; border-radius: 3px; }
  .as-th-link:hover { background: #e2e8f0; color: #0f172a; text-decoration: none; }
  .as-th-link.active { color: #0c4a6e; background: #e0f2fe; }
  .as-arrow { color: #0284c7; font-weight: bold; }
  .as-badge-never { background:#dc2626;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600; }
  .as-badge-old   { background:#ea580c;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600; }
  .as-badge-warm  { background:#facc15;color:#000;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600; }
  .as-variant     { background:#e0e7ff;color:#3730a3;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;white-space:nowrap; }
  .as-no-variant  { color:#94a3b8; font-size:11px; }
  .as-check.on    { color:#16a34a; font-weight:bold; font-size:14px; }
  .as-check.off   { color:#cbd5e1; }
  .as-check.liq   { background:#fbbf24; color:#78350f; padding:1px 6px; border-radius:4px; font-size:10px; font-weight:700; letter-spacing:.5px; }
  .as-pid a { font-weight: 600; color: #1d4ed8; text-decoration: none; }
  .as-pid a:hover { text-decoration: underline; }
  .as-ext { color: #64748b; font-size: 11px; }
  .as-ext:hover { color: #1d4ed8; }
  .as-special { color:#16a34a; font-weight: 600; }
  .as-stats { background:#fff; border:1px solid #e5e7eb; padding:10px 14px;border-radius:8px;display:inline-block;margin-right:10px; }
  .as-stats .lbl { display:block; font-size:11px; color:#64748b; text-transform: uppercase; letter-spacing: .5px; }
  .as-stats .val { font-size: 16px; font-weight: 700; color:#0f172a; font-variant-numeric: tabular-nums; }
  .as-filters .form-control, .as-filters .form-select { font-size: 13px; }
  .as-model { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 11px; color:#475569; }
  .as-mfg { font-size: 11px; color:#64748b; }
</style>

<div class="container-fluid p-3 as-wrap">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h3 class="mb-0">Candidatos a oferta — stock con baja rotación
      <small class="text-muted" style="font-size:13px">Fase 1 · vista read-only</small></h3>
    <span class="text-muted" style="font-size:12px">Último cálculo: <?=htmlspecialchars($calc_en)?></span>
  </div>

  <div class="mb-3">
    <span class="as-stats"><span class="lbl">Productos</span><span class="val"><?=fmt_n($summary['n_prods'])?></span></span>
    <span class="as-stats"><span class="lbl">Unidades stock</span><span class="val"><?=fmt_n($summary['sum_stock'])?></span></span>
    <span class="as-stats"><span class="lbl">Valor stock a PVP</span><span class="val"><?=fmt_n($summary['valor_stock'],2)?> €</span></span>
    <span class="as-stats"><span class="lbl">Vendido 365d</span><span class="val"><?=fmt_n($summary['imp_365'],2)?> €</span></span>
  </div>

  <form method="get" class="as-filters row g-2 mb-3">
    <div class="col-md-3">
      <input type="text" name="q" class="form-control" placeholder="model / CCODIART / nombre / pid"
             value="<?=htmlspecialchars($f_search)?>">
    </div>
    <div class="col-md-2">
      <select name="cat" class="form-select">
        <option value="0">— Categoría raíz —</option>
        <?php while ($c = tep_db_fetch_array($cat_q)): ?>
          <option value="<?=$c['categories_id']?>" <?=$f_cat==$c['categories_id']?'selected':''?>>
            <?=htmlspecialchars($c['categories_name'])?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select name="mfg" class="form-select">
        <option value="0">— Marca —</option>
        <?php while ($m = tep_db_fetch_array($mfg_q)): ?>
          <option value="<?=$m['manufacturers_id']?>" <?=$f_mfg==$m['manufacturers_id']?'selected':''?>>
            <?=htmlspecialchars($m['manufacturers_name'])?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="col-md-1">
      <div class="form-floating">
        <input type="number" name="stock_min" id="stock_min" class="form-control" min="0"
               value="<?=$f_stock_min?>" placeholder="Stock mín.">
        <label for="stock_min" style="font-size:11px">Stock mín.</label>
      </div>
    </div>
    <div class="col-md-1">
      <div class="form-floating">
        <input type="number" name="days_min" id="days_min" class="form-control" min="0"
               value="<?=$f_days_min?>" placeholder="Días sin venta">
        <label for="days_min" style="font-size:11px">Días sin venta</label>
      </div>
    </div>
    <div class="col-md-1">
      <div class="form-floating">
        <input type="number" name="pvp_min" id="pvp_min" class="form-control" min="0" step="0.01"
               value="<?=$f_pvp_min>0?htmlspecialchars((string)$f_pvp_min):''?>" placeholder="PVP mín">
        <label for="pvp_min" style="font-size:11px">PVP mín. €</label>
      </div>
    </div>
    <div class="col-md-2">
      <select name="order" class="form-select">
        <option value="priority"    <?=$f_order=='priority'?'selected':''?>>Prioridad (stock×días)</option>
        <option value="stock"       <?=$f_order=='stock'?'selected':''?>>Más stock</option>
        <option value="days"        <?=$f_order=='days'?'selected':''?>>Más días sin venta</option>
        <option value="cob90"       <?=$f_order=='cob90'?'selected':''?>>Cob. 90d (más)</option>
        <option value="cob180"      <?=$f_order=='cob180'?'selected':''?>>Cob. 180d (más)</option>
        <option value="cob365"      <?=$f_order=='cob365'?'selected':''?>>Cob. 365d (más)</option>
        <option value="imp_lost"    <?=$f_order=='imp_lost'?'selected':''?>>Valor stock a PVP €</option>
        <option value="coste_stock" <?=$f_order=='coste_stock'?'selected':''?>>Valor stock a coste €</option>
      </select>
    </div>
    <div class="col-md-2 d-flex align-items-center gap-3">
      <div class="form-check form-check-inline" title="Solo productos con Control de Stock activo (check_stock=1)">
        <input class="form-check-input" type="checkbox" name="cstock" id="cstock" value="1" <?=$f_cstock?'checked':''?>>
        <label class="form-check-label" for="cstock" style="font-size:12px">Ctrl. Stock</label>
      </div>
      <div class="form-check form-check-inline" title="Solo productos en liquidación">
        <input class="form-check-input" type="checkbox" name="liquid" id="liquid" value="1" <?=$f_liquid?'checked':''?>>
        <label class="form-check-label" for="liquid" style="font-size:12px">Liq.</label>
      </div>
    </div>
    <div class="col-md-1">
      <button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button>
    </div>
  </form>

  <p class="text-muted" style="font-size:12px">
    <?=fmt_n($total)?> productos · página <?=$page?> / <?=$pages?>
  </p>

  <table class="table table-sm as-table table-hover table-striped">
    <thead>
      <tr>
        <th></th>
        <th>pid</th>
        <th>Modelo</th>
        <th>Variante</th>
        <th>Nombre</th>
        <th>Marca</th>
        <th class="text-end"><?=order_link('stock','Stock',$f_order)?></th>
        <th class="text-end">v30</th>
        <th class="text-end">v90</th>
        <th class="text-end">v180</th>
        <th class="text-end">v365</th>
        <th class="text-end">€365</th>
        <th class="text-center">Últ. venta</th>
        <th class="text-end"><?=order_link('days','Sin venta (d)',$f_order)?></th>
        <th class="text-end" title="stock / (v90/90)"><?=order_link('cob90','Cob 90',$f_order)?></th>
        <th class="text-end" title="stock / (v180/180)"><?=order_link('cob180','Cob 180',$f_order)?></th>
        <th class="text-end" title="stock / (v365/365)"><?=order_link('cob365','Cob 365',$f_order)?></th>
        <th class="text-end"><?=order_link('pvp','PVP',$f_order)?></th>
        <th class="text-end"><?=order_link('cost','Coste',$f_order)?></th>
        <th class="text-end">Special</th>
        <th class="text-center" title="Control de Stock (products.check_stock) — si está activo, no permite vender sin stock">Ctrl. Stock</th>
        <th class="text-center" title="products_liquidacion — flag de producto en liquidación">Liq.</th>
      </tr>
    </thead>
    <tbody>
    <?php while ($r = tep_db_fetch_array($rows_q)): ?>
      <?php
        $sin = $r['dias_sin_venta'];
        $badge = '';
        if ($sin === null) {
            $badge = '<span class="as-badge-never">nunca</span>';
        } elseif ($sin >= 365) {
            $badge = '<span class="as-badge-never">'.$sin.'</span>';
        } elseif ($sin >= 180) {
            $badge = '<span class="as-badge-old">'.$sin.'</span>';
        } else {
            $badge = '<span class="as-badge-warm">'.$sin.'</span>';
        }
        $img = $r['products_image']
          ? '<img class="thumb" src="../images/productos/' . htmlspecialchars($r['products_image']) . '" loading="lazy">'
          : '';
        $url_edit = 'categories.php?action=new_product&pID=' . (int)$r['products_id'];
        $url_pub  = 'https://www.francobordo.com/product_info.php?products_id=' . (int)$r['products_id'];
      ?>
      <?php
        $is_variant = ((int)$r['options_values_id']) > 0;
        $variant_label = '';
        if ($is_variant) {
            $name = trim((string)($r['variant_name'] ?? ''));
            $code = trim((string)($r['ccodival'] ?? ''));
            $variant_label = $name !== '' ? $name : $code;
        }
      ?>
      <tr>
        <td><?=$img?></td>
        <td class="as-pid">
          <a href="<?=$url_edit?>" title="Editar">#<?=$r['products_id']?></a><br>
          <a href="<?=$url_pub?>" target="_blank" class="as-ext">ver web ↗</a>
        </td>
        <td><span class="as-model"><?=htmlspecialchars($r['products_model'] ?? '')?></span></td>
        <td>
          <?php if ($is_variant): ?>
            <span class="as-variant" title="ovid <?=(int)$r['options_values_id']?> · CCODIVAL <?=htmlspecialchars($r['ccodival'] ?? '')?>">
              <?=htmlspecialchars($variant_label)?>
            </span>
          <?php else: ?>
            <span class="as-no-variant">—</span>
          <?php endif; ?>
        </td>
        <td><div class="as-name" title="<?=htmlspecialchars($r['products_name'] ?? '')?>"><?=htmlspecialchars($r['products_name'] ?? '')?></div></td>
        <td class="as-mfg"><?=htmlspecialchars($r['manufacturers_name'] ?? '')?></td>
        <td class="text-end as-num"><strong><?=fmt_n($r['stock_variant'])?></strong></td>
        <td class="text-end as-num"><?=fmt_n($r['ventas_30d'],0)?></td>
        <td class="text-end as-num"><?=fmt_n($r['ventas_90d'],0)?></td>
        <td class="text-end as-num"><?=fmt_n($r['ventas_180d'],0)?></td>
        <td class="text-end as-num"><?=fmt_n($r['ventas_365d'],0)?></td>
        <td class="text-end as-num"><?=fmt_n($r['importe_365d'],0)?></td>
        <td class="text-center as-num"><?=$r['ultima_venta']??'—'?></td>
        <td class="text-end"><?=$badge?></td>
        <td class="text-end as-num"><?=fmt_n($r['cob_90'],0)?></td>
        <td class="text-end as-num"><?=fmt_n($r['cob_180'],0)?></td>
        <td class="text-end as-num"><?=fmt_n($r['cob_365'],0)?></td>
        <td class="text-end as-num"><?=fmt_n($r['pvp_var'],2)?></td>
        <td class="text-end as-num"><?=fmt_n($r['products_cost'],2)?></td>
        <td class="text-end as-num">
          <?php if ($r['special_price'] !== null): ?>
            <span class="as-special"><?=fmt_n($r['special_price'],2)?></span>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td class="text-center"><?=((int)$r['check_stock']===1)?'<span class="as-check on">✓</span>':'<span class="as-check off">—</span>'?></td>
        <td class="text-center"><?=((int)$r['products_liquidacion']===1)?'<span class="as-check liq">LIQ</span>':'<span class="as-check off">—</span>'?></td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>

  <?php
    $qs_base = $_GET; unset($qs_base['p']);
    $base = htmlspecialchars(http_build_query($qs_base));
  ?>
  <nav>
    <ul class="pagination pagination-sm">
      <?php if ($page > 1): ?>
        <li class="page-item"><a class="page-link" href="?<?=$base?>&p=1">«</a></li>
        <li class="page-item"><a class="page-link" href="?<?=$base?>&p=<?=$page-1?>">‹</a></li>
      <?php endif; ?>
      <li class="page-item active"><span class="page-link"><?=$page?> / <?=$pages?></span></li>
      <?php if ($page < $pages): ?>
        <li class="page-item"><a class="page-link" href="?<?=$base?>&p=<?=$page+1?>">›</a></li>
        <li class="page-item"><a class="page-link" href="?<?=$base?>&p=<?=$pages?>">»</a></li>
      <?php endif; ?>
    </ul>
  </nav>

  <p class="text-muted" style="font-size:11px;margin-top:20px">
    Fase 1: vista de diagnóstico. Las pestañas <em>Reglas</em>, <em>Aplicar oferta</em>,
    <em>Activas</em> e <em>Historial</em> llegarán en fases 2-5. La velocidad se recalcula
    cada noche a las 04:15 desde QFacWin (EA15_ALBALIN + EA15_FRESLIN directa).
  </p>
</div>

<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
