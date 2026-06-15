<?php
// auto_specials_preview.php — Fases 1-3
// Lista candidatos a oferta. En Fase 3 permite aplicar via POST a auto_specials_apply.php.
require 'includes/application_top.php';
require __DIR__ . '/auto_specials_helpers.php';

$flash = $_SESSION['auto_specials_flash'] ?? '';
if ($flash) unset($_SESSION['auto_specials_flash']);

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
$f_buy_before = trim((string)($_GET['buy_before'] ?? ''));
$f_inc_no_buy = isset($_GET['inc_no_buy']) ? 1 : 1; // default: incluir productos sin compra registrada
$f_modif_before = trim((string)($_GET['modif_before'] ?? '')); // oferta modificada antes de
$f_rule_id   = isset($_GET['rule_id']) ? (int)$_GET['rule_id'] : 0;
$f_order     = $_GET['order'] ?? 'priority';
$f_dir       = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
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
if ($f_buy_before !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_buy_before)) {
    $bb = tep_db_input($f_buy_before);
    if ($f_inc_no_buy) {
        $where[] = "(v.ultima_compra IS NULL OR v.ultima_compra <= '{$bb}')";
    } else {
        $where[] = "v.ultima_compra <= '{$bb}'";
    }
}
// Filtro: oferta (specials cgid=0) modificada en/antes de fecha. Solo productos con oferta.
if ($f_modif_before !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_modif_before)) {
    $mb = tep_db_input($f_modif_before);
    $where[] = "EXISTS (SELECT 1 FROM specials s WHERE s.products_id=p.products_id AND s.status=1
                        AND s.customers_group_id=0
                        AND COALESCE(s.specials_last_modified, s.specials_date_added) <= '{$mb} 23:59:59')";
}

// Filtro rule_id: solo variantes cuya regla GANADORA sea exactamente esa.
// Pre-pase: cargo elegibles con criterios mínimos + mfg si la regla los tiene
// y luego en PHP filtro las que pick_rule devuelve esa regla.
$rule_filter_row = null;
if ($f_rule_id > 0) {
    $_rq = tep_db_query("SELECT * FROM auto_specials_tier_rules WHERE id={$f_rule_id} LIMIT 1");
    $rule_filter_row = tep_db_fetch_array($_rq) ?: null;
}
if ($rule_filter_row) {
    $_all_rules = [];
    $_rq2 = tep_db_query("SELECT * FROM auto_specials_tier_rules WHERE activo=1");
    while ($_r2 = tep_db_fetch_array($_rq2)) $_all_rules[] = $_r2;

    // Restringe pre-pase a productos cuya mfg coincide (si la regla tiene mfg específico)
    $_pre_where = ['p.products_status = 1', 'v.stock_variant > 0'];
    if ((int)$rule_filter_row['manufacturers_id'] > 0) {
        $_pre_where[] = "p.manufacturers_id = " . (int)$rule_filter_row['manufacturers_id'];
    }
    $_pre_sql = "SELECT v.products_id, v.options_values_id, v.dias_sin_venta, v.dias_sin_compra, v.dias_cobertura,
                        p.manufacturers_id
                 FROM qfac_sales_velocity v
                 JOIN products p ON p.products_id = v.products_id
                 WHERE " . implode(' AND ', $_pre_where);
    $matching_keys = [];
    $_prq = tep_db_query($_pre_sql);
    while ($_pre_r = tep_db_fetch_array($_prq)) {
        $picked = as_pick_rule(
            $_pre_r['dias_sin_venta'], $_pre_r['dias_cobertura'] ?? null,
            (int)$_pre_r['manufacturers_id'], $_all_rules,
            $_pre_r['dias_sin_compra'] ?? null);
        if ($picked && (int)$picked['id'] === $f_rule_id) {
            $matching_keys[] = [(int)$_pre_r['products_id'], (int)$_pre_r['options_values_id']];
        }
    }
    if (empty($matching_keys)) {
        $where[] = '0';
    } else {
        // Construir IN compuesto eficiente vía OR para (pid, ovid)
        $clauses = [];
        foreach ($matching_keys as $k) {
            $clauses[] = "(v.products_id={$k[0]} AND v.options_values_id={$k[1]})";
        }
        $where[] = '(' . implode(' OR ', $clauses) . ')';
    }
}

$where_sql = implode(' AND ', $where);

// Expresiones base; la dirección (ASC/DESC) se aplica al final salvo en 'days'
// donde NULL siempre va al final.
$DIR = strtoupper($f_dir);
$order_map = [
    'priority'    => "v.stock_variant * COALESCE(v.dias_sin_venta, 9999) {$DIR}",
    'stock'       => "v.stock_variant {$DIR}",
    'days'        => "v.dias_sin_venta IS NULL ASC, v.dias_sin_venta {$DIR}, v.stock_variant DESC",
    'pvp'         => "{$pvp_effective} {$DIR}",
    'cost'        => "p.products_cost {$DIR}",
    'margin'      => "CASE WHEN {$pvp_effective} > 0 THEN (({$pvp_effective} - COALESCE(p.products_cost,0)) / {$pvp_effective}) ELSE -9999 END {$DIR}",
    'imp_lost'    => "(v.stock_variant * {$pvp_effective}) {$DIR}",
    'coste_stock' => "(v.stock_variant * COALESCE(NULLIF(p.products_cost,0), 0)) {$DIR}",
    'modif'       => "(SELECT COALESCE(s.specials_last_modified, s.specials_date_added) FROM specials s WHERE s.products_id=p.products_id AND s.status=1 AND s.customers_group_id=0 ORDER BY s.specials_id DESC LIMIT 1) IS NULL ASC, (SELECT COALESCE(s.specials_last_modified, s.specials_date_added) FROM specials s WHERE s.products_id=p.products_id AND s.status=1 AND s.customers_group_id=0 ORDER BY s.specials_id DESC LIMIT 1) {$DIR}",
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
           p.products_tax_class_id,
           p.check_stock, p.products_liquidacion,
           m.manufacturers_name,
           pd.products_name,
           v.options_values_id, v.ccodival,
           v.stock_variant, v.ultima_venta, v.ultima_compra,
           v.dias_sin_venta, v.dias_sin_compra,
           v.ventas_30d, v.ventas_90d, v.ventas_180d, v.ventas_365d,
           v.importe_365d,
           pa.options_values_price, pa.price_prefix,
           {$pvp_effective} AS pvp_var,
           pov.products_options_values_name AS variant_name,
           (SELECT s.specials_new_products_price FROM specials s
            WHERE s.products_id=p.products_id AND s.status=1 LIMIT 1) AS special_price,
           (SELECT COALESCE(s.specials_last_modified, s.specials_date_added) FROM specials s
            WHERE s.products_id=p.products_id AND s.status=1 AND s.customers_group_id=0
            ORDER BY s.specials_id DESC LIMIT 1) AS special_modif
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
$rows = [];
while ($_r = tep_db_fetch_array($rows_q)) $rows[] = $_r;

// Map de ofertas auto activas (para pintar badge "AUTO" en columna Special)
$active_auto = [];
if (!empty($rows)) {
    $pid_list = implode(',', array_unique(array_map(fn($x) => (int)$x['products_id'], $rows)));
    $aq = tep_db_query("SELECT products_id, options_values_id, customers_group_id, pvp_oferta, fecha_fin, rule_id
                        FROM auto_specials_active
                        WHERE products_id IN ({$pid_list}) AND estado='active'");
    while ($ar = tep_db_fetch_array($aq)) {
        $key = (int)$ar['products_id'] . '|' . (int)$ar['options_values_id'] . '|' . (int)$ar['customers_group_id'];
        $active_auto[$key] = $ar;
    }
}

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

// ---- Reglas + overrides ----------------------------------------------------
$rules = [];
$rq = tep_db_query("SELECT * FROM auto_specials_tier_rules WHERE activo=1
                    ORDER BY descuento_pct DESC, prioridad ASC");
while ($rr = tep_db_fetch_array($rq)) $rules[] = $rr;
$overrides_map = as_load_overrides_map();

// Mapping tax_class → rate España (peninsula). Memoria francobordo_tienda confirma tax_zone_id=31.
$TAX_BY_CLASS = [0 => 0.0, 1 => 21.0, 2 => 4.0, 3 => 10.0];

function fmt_d($n) { return $n === null ? '—' : (string)$n; }
function fmt_n($n, $d=0) { return $n === null ? '—' : number_format((float)$n, $d, ',', '.'); }

// pick_rule vive ahora en auto_specials_helpers.php (`as_pick_rule`).

function order_link($key, $label, $f_order, $f_dir) {
    $is_active = ($f_order === $key);
    // Toggle: si ya está activa, invierte dir. Si no, entra como DESC.
    $next_dir = $is_active ? ($f_dir === 'desc' ? 'asc' : 'desc') : 'desc';
    $qs = $_GET;
    $qs['order'] = $key;
    $qs['dir']   = $next_dir;
    unset($qs['p']);
    $href = '?' . htmlspecialchars(http_build_query($qs));
    $arrow_char = $is_active ? ($f_dir === 'desc' ? '↓' : '↑') : '';
    $arrow = $is_active ? ' <span class="as-arrow">'.$arrow_char.'</span>' : '';
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
  .as-name { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; max-width: 360px; min-width: 240px; line-height: 1.25; }
  .as-iva-tag { color:#94a3b8; font-weight:normal; font-size:10px; }
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
  .as-disc        { background:#dcfce7; color:#166534; padding:2px 8px; border-radius:8px; font-weight:700; font-size:11px; cursor:help; }
  .as-disc-warn   { color:#94a3b8; font-size:10px; margin-left:3px; cursor:help; }
  .as-floor       { background:#fee2e2; color:#991b1b; padding:1px 6px; border-radius:4px; font-size:10px; font-weight:700; margin-left:4px; cursor:help; }
  .as-disc-cell, .as-pvp-sug-cell { cursor: pointer; display: inline-block; padding: 2px; }
  .as-pvp-sug-cell:hover { background: #fef3c7; border-radius: 3px; }
  .as-disc-ovr    { background:#fed7aa !important; color:#7c2d12 !important; }
  .as-ovr-tag     { background:#7c2d12; color:#fed7aa; padding:0 4px; border-radius:3px; font-size:9px; font-weight:700; margin-left:2px; }
  .as-disc-empty  { color:#cbd5e1; cursor:pointer; font-size:14px; }
  .as-margin-ok   { color:#166534; font-weight:600; }
  .as-margin-low  { color:#a16207; font-weight:600; }
  .as-margin-neg  { background:#fee2e2; color:#991b1b; padding:1px 6px; border-radius:4px; font-weight:700; }
  .as-edit-input  { width: 64px; font-size: 11px; padding: 1px 4px; }
  .as-edit-actions button { font-size: 11px; padding: 1px 6px; margin-left: 2px; }
  .as-badge-auto  { background:#1d4ed8; color:#fff; padding:1px 5px; border-radius:4px; font-size:9px; font-weight:700; margin-left:4px; letter-spacing:.5px; vertical-align:middle; }
  .as-row-auto    { background:#eff6ff !important; }
  .as-check-row   { cursor:pointer; }
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
    <span class="as-stats"><span class="lbl">Valor stock a PVP (c/IVA aprox 21%)</span><span class="val"><?=fmt_n(($summary['valor_stock'] ?? 0)*1.21, 2)?> €</span></span>
    <span class="as-stats"><span class="lbl">Vendido 365d (c/IVA aprox 21%)</span><span class="val"><?=fmt_n(($summary['imp_365'] ?? 0)*1.21, 2)?> €</span></span>
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
      <div class="form-floating">
        <input type="date" name="buy_before" id="buy_before" class="form-control"
               value="<?=htmlspecialchars($f_buy_before)?>">
        <label for="buy_before" style="font-size:11px">Compra ≤ fecha</label>
      </div>
    </div>
    <div class="col-md-2">
      <div class="form-floating">
        <input type="date" name="modif_before" id="modif_before" class="form-control"
               value="<?=htmlspecialchars($f_modif_before)?>">
        <label for="modif_before" style="font-size:11px">Oferta modif. ≤ fecha</label>
      </div>
    </div>
    <div class="col-md-2">
      <select name="order" class="form-select">
        <option value="priority"    <?=$f_order=='priority'?'selected':''?>>Prioridad (stock×días)</option>
        <option value="stock"       <?=$f_order=='stock'?'selected':''?>>Más stock</option>
        <option value="days"        <?=$f_order=='days'?'selected':''?>>Más días sin venta</option>
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

  <?php if (!empty($flash)): ?>
    <div class="alert alert-info" style="white-space:pre-wrap;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px"><?=htmlspecialchars($flash)?></div>
  <?php endif; ?>

  <?php if ($rule_filter_row): ?>
    <div class="alert alert-warning" style="padding:8px 12px;font-size:13px">
      Filtrado por regla <strong>#<?=$f_rule_id?></strong> (−<?=number_format((float)$rule_filter_row['descuento_pct'],1,',','.')?>% ·
      sin venta ≥ <?=$rule_filter_row['dias_sin_venta_min']?>d ·
      cob. ≥ <?=$rule_filter_row['dias_cobertura_min'] ?? '—'?>d ·
      <?= ((int)$rule_filter_row['manufacturers_id'] === 0) ? 'GLOBAL' : 'mfg=' . (int)$rule_filter_row['manufacturers_id'] ?>) ·
      <?=htmlspecialchars($rule_filter_row['nota'] ?? '')?>
      <a href="?<?=htmlspecialchars(http_build_query(array_diff_key($_GET, ['rule_id'=>1, 'p'=>1])))?>" style="margin-left:10px">× quitar filtro</a>
    </div>
  <?php endif; ?>

  <!-- Form externo: los checkboxes de las filas viven aquí vía atributo form="as-apply-form" -->
  <form id="as-apply-form" method="post" action="auto_specials_apply.php"
        onsubmit="return confirm('¿Aplicar la oferta a las variantes marcadas?\n\nEsto modifica precios en specials inmediatamente.');"
        style="margin-bottom:12px;display:flex;align-items:center;gap:12px;">
    <input type="hidden" name="return_url" value="<?=htmlspecialchars($_SERVER['REQUEST_URI'])?>">
    <button type="submit" class="btn btn-success btn-sm">
      Aplicar oferta a marcados
    </button>
    <span class="text-muted" style="font-size:12px">
      Productos planos → <code>specials</code> · variantes → <code>products_attributes</code> (delta) + <code>products_attributes_groups</code> (G1 piping).
    </span>
  </form>

  <p class="text-muted" style="font-size:12px">
    <?=fmt_n($total)?> productos · página <?=$page?> / <?=$pages?>
  </p>

  <table class="table table-sm as-table table-hover table-striped">
    <thead>
      <tr>
        <th title="Marcar/desmarcar todos los aplicables"><input type="checkbox" id="as-check-all"></th>
        <th></th>
        <th>pid</th>
        <th>Modelo</th>
        <th>Variante</th>
        <th>Nombre</th>
        <th>Marca</th>
        <th class="text-end"><?=order_link('stock','Stock',$f_order, $f_dir)?></th>
        <th class="text-end">v30</th>
        <th class="text-end">v90</th>
        <th class="text-end">v180</th>
        <th class="text-end">v365</th>
        <th class="text-end">€365</th>
        <th class="text-center">Últ. venta</th>
        <th class="text-end"><?=order_link('days','Sin venta (d)',$f_order, $f_dir)?></th>
        <th class="text-center" title="Última entrada de stock (albarán de proveedor)">Últ. compra</th>
        <th class="text-end" title="Días desde última compra">Sin compra (d)</th>
        <th class="text-end" title="Precio con IVA. Tooltip = sin IVA."><?=order_link('pvp','PVP',$f_order, $f_dir)?><br><small class="as-iva-tag">c/IVA</small></th>
        <th class="text-end" title="Coste con IVA. Tooltip = sin IVA."><?=order_link('cost','Coste',$f_order, $f_dir)?><br><small class="as-iva-tag">c/IVA</small></th>
        <th class="text-end" title="Margen sobre venta sin IVA = (PVP − Coste) / PVP"><?=order_link('margin','Margen',$f_order, $f_dir)?><br><small class="as-iva-tag">%</small></th>
        <th class="text-end">Special<br><small class="as-iva-tag">c/IVA</small></th>
        <th class="text-center" title="Fecha de última modificación / alta de la oferta (público)"><?=order_link('modif','Oferta',$f_order, $f_dir)?><br><small class="as-iva-tag">modif.</small></th>
        <th class="text-end" title="Descuento sugerido por regla aplicable">Desc.<br><small class="as-iva-tag">sug.</small></th>
        <th class="text-end" title="PVP propuesto con IVA = PVP × (1 - descuento) × (1 + IVA)">PVP sug.<br><small class="as-iva-tag">c/IVA</small></th>
        <th class="text-end" title="Margen tras descuento = (PVP sug − Coste) / PVP sug">Margen<br><small class="as-iva-tag">tras dto.</small></th>
        <th class="text-center" title="Control de Stock (products.check_stock) — si está activo, no permite vender sin stock">Ctrl. Stock</th>
        <th class="text-center" title="products_liquidacion — flag de producto en liquidación">Liq.</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
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
        // Evaluar regla + oferta sugerida ANTES del <tr> para que checkbox tenga las vars
        $rule = as_pick_rule_with_override(
            $r['dias_sin_venta'], $r['dias_cobertura'] ?? null,
            (int)$r['manufacturers_id'], $rules,
            $overrides_map, (int)$r['products_id'], (int)$r['options_values_id'],
            $r['dias_sin_compra'] ?? null);
        $is_override = !empty($rule['is_override']);
        $pvp = (float)$r['pvp_var'];
        $cost = (float)($r['products_cost'] ?? 0);
        if ($rule) {
            $pct = (float)$rule['descuento_pct'];
            [$pvp_sug, $floor, $capped] = as_compute_offer($pvp, $cost, $pct, $rule['min_margin_pct']);
            $below = $capped;
            $special_cur = $r['special_price'] !== null ? (float)$r['special_price'] : null;
            $better = ($special_cur === null) || ($pvp_sug < $special_cur);
        } else {
            $pct = null; $pvp_sug = null; $below = false; $better = false; $floor = null;
            $special_cur = $r['special_price'] !== null ? (float)$r['special_price'] : null;
        }
        $auto_key = (int)$r['products_id'] . '|' . (int)$r['options_values_id'] . '|0';
        $is_auto = isset($active_auto[$auto_key]);
        $can_apply = ($rule !== null) && ($pvp_sug !== null) && ($pvp_sug < $pvp)
                     && ($is_variant || $better);
        $apply_value = $is_variant
            ? ((int)$r['products_id'] . ':' . (int)$r['options_values_id'])
            : (string)(int)$r['products_id'];
      ?>
      <tr<?=$is_auto?' class="as-row-auto"':''?>>
        <td class="text-center">
          <?php if ($can_apply): ?>
            <input type="checkbox" name="items[]" value="<?=htmlspecialchars($apply_value)?>" form="as-apply-form" checked class="as-check-row">
          <?php else:
              if ($rule === null) {
                  $no_apply_reason = 'Sin regla aplicable (revisa estado de reglas)';
              } elseif ($pvp_sug !== null && $pvp_sug >= $pvp) {
                  $no_apply_reason = sprintf(
                      'Piso de margen (%.2f €) ≥ PVP actual (%.2f €). El descuento subiría el precio: no se aplica.',
                      (float)$pvp_sug, (float)$pvp);
              } elseif (!$is_variant && !$better) {
                  $no_apply_reason = sprintf(
                      'El specials actual (%.2f €) ya es igual o mejor que la propuesta (%.2f €).',
                      (float)$special_cur, (float)$pvp_sug);
              } else {
                  $no_apply_reason = 'No aplicable';
              }
          ?>
            <span title="<?=htmlspecialchars($no_apply_reason)?>" style="color:#94a3b8;font-size:14px;cursor:help">—</span>
          <?php endif; ?>
        </td>
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
        <td class="text-center as-num"><?=$r['ultima_compra']??'—'?></td>
        <td class="text-end as-num">
          <?php $dsc = $r['dias_sin_compra']; if ($dsc === null): ?>—<?php else: ?>
            <span style="color:<?=$dsc <= 90 ? '#16a34a' : ($dsc <= 365 ? '#a16207' : '#94a3b8')?>"><?=$dsc?></span>
          <?php endif; ?>
        </td>
        <?php
          $tax_rate   = $TAX_BY_CLASS[(int)$r['products_tax_class_id']] ?? 21.0;
          $iva_mult   = 1 + $tax_rate / 100;
          $pvp_var_f  = (float)$r['pvp_var'];
          $cost_f     = (float)($r['products_cost'] ?? 0);
          $pvp_iva    = $pvp_var_f * $iva_mult;
          $cost_iva   = $cost_f * $iva_mult;
          $margin_now = ($pvp_var_f > 0) ? round(($pvp_var_f - $cost_f) / $pvp_var_f * 100, 1) : null;
        ?>
        <td class="text-end as-num" title="Sin IVA: <?=fmt_n($pvp_var_f,2)?> €"><?=fmt_n($pvp_iva,2)?></td>
        <td class="text-end as-num" title="Sin IVA: <?=fmt_n($cost_f,2)?> €"><?=fmt_n($cost_iva,2)?></td>
        <td class="text-end as-num">
          <?php if ($margin_now === null): ?>—<?php else:
              $cls = $margin_now < 0 ? 'as-margin-neg' : ($margin_now < 10 ? 'as-margin-low' : 'as-margin-ok');
          ?>
            <span class="<?=$cls?>"><?=number_format($margin_now,1,',','.')?>%</span>
          <?php endif; ?>
        </td>
        <td class="text-end as-num">
          <?php if ($r['special_price'] !== null):
              $tax_rate = $TAX_BY_CLASS[(int)$r['products_tax_class_id']] ?? 21.0;
              $special_iva = (float)$r['special_price'] * (1 + $tax_rate / 100);
          ?>
            <span class="as-special" title="Sin IVA: <?=fmt_n($r['special_price'],2)?> € · IVA <?=fmt_n($tax_rate,0)?>%"><?=fmt_n($special_iva,2)?></span>
            <?php if ($is_auto): ?><span class="as-badge-auto" title="Oferta generada por motor auto_specials">AUTO</span><?php endif; ?>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td class="text-center as-num" style="font-size:11px;white-space:nowrap">
          <?php
            if (!empty($r['special_modif'])) {
                $ts = strtotime((string)$r['special_modif']);
                echo '<span title="' . htmlspecialchars((string)$r['special_modif']) . '">' . date('d/m/y', $ts) . '</span>';
            } else {
                echo '<span style="color:#cbd5e1">—</span>';
            }
          ?>
        </td>
        <td class="text-end as-num">
          <span class="as-disc-cell"
                data-pid="<?=(int)$r['products_id']?>"
                data-ovid="<?=(int)$r['options_values_id']?>"
                data-pct="<?=$rule?htmlspecialchars((string)$pct):''?>">
          <?php if ($rule): ?>
            <span class="as-disc <?=$is_override?'as-disc-ovr':''?>"
                  title="<?=$is_override?'OVERRIDE manual':'Regla #'.$rule['id'].' · '.htmlspecialchars($rule['nota'] ?? '')?> · vigencia <?=$rule['vigencia_dias']?>d. Clic para editar.">
              −<?=fmt_n($pct,0)?>%<?=$is_override?' <span class="as-ovr-tag">OVR</span>':''?>
            </span>
            <?php if (!$is_variant && !$better): ?><span class="as-disc-warn" title="El special actual ya es mejor o igual">≤</span><?php endif; ?>
          <?php else: ?>
            <span class="as-disc-empty" title="Sin regla aplicable. Clic para asignar override manual.">—</span>
          <?php endif; ?>
          </span>
        </td>
        <td class="text-end as-num">
          <span class="as-pvp-sug-cell"
                data-pid="<?=(int)$r['products_id']?>"
                data-ovid="<?=(int)$r['options_values_id']?>"
                data-pvp-var="<?=htmlspecialchars((string)$pvp_var_f)?>"
                data-iva-mult="<?=htmlspecialchars((string)$iva_mult)?>"
                data-pvp-sug-iva="<?=$rule?htmlspecialchars(number_format($pvp_sug * $iva_mult, 2, '.', '')):''?>">
          <?php if ($rule):
              $pvp_sug_iva = $pvp_sug * $iva_mult;
              $floor_iva   = $floor !== null ? $floor * $iva_mult : null;
          ?>
            <span title="Sin IVA: <?=fmt_n($pvp_sug,2)?> €. Clic para fijar PVP manual."><?=fmt_n($pvp_sug_iva, 2)?></span>
            <?php if ($below && $floor !== null): ?><span class="as-floor" title="PVP capado al piso de margen = <?=fmt_n($floor_iva,2)?> € c/IVA (cost / (1 − margen))">↓piso</span><?php endif; ?>
          <?php else: ?>
            <span title="Clic para fijar PVP manual" style="color:#cbd5e1;cursor:pointer">—</span>
          <?php endif; ?>
          </span>
        </td>
        <td class="text-end as-num">
          <?php if ($rule && $pvp_sug > 0):
              $margin_after = round(($pvp_sug - $cost_f) / $pvp_sug * 100, 1);
              $cls2 = $margin_after < 0 ? 'as-margin-neg' : ($margin_after < 10 ? 'as-margin-low' : 'as-margin-ok');
          ?>
            <span class="<?=$cls2?>"><?=number_format($margin_after,1,',','.')?>%</span>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td class="text-center"><?=((int)$r['check_stock']===1)?'<span class="as-check on">✓</span>':'<span class="as-check off">—</span>'?></td>
        <td class="text-center"><?=((int)$r['products_liquidacion']===1)?'<span class="as-check liq">LIQ</span>':'<span class="as-check off">—</span>'?></td>
      </tr>
    <?php endforeach; ?>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Select-all
  var all = document.getElementById('as-check-all');
  if (all) {
    var rows = document.querySelectorAll('.as-check-row');
    all.checked = rows.length > 0 && Array.from(rows).every(function(c){return c.checked;});
    all.addEventListener('change', function() {
      rows.forEach(function(c){ c.checked = all.checked; });
    });
  }

  // Edición inline del descuento sugerido
  document.querySelectorAll('.as-disc-cell').forEach(function(cell) {
    cell.addEventListener('click', function(e) {
      if (cell.querySelector('.as-edit-input')) return; // ya en edición
      var pid = cell.dataset.pid;
      var ovid = cell.dataset.ovid;
      var currentPct = cell.dataset.pct || '';
      var original = cell.innerHTML;
      cell.innerHTML =
        '<input type="number" min="0" max="100" step="0.5" class="as-edit-input" value="' + currentPct + '" placeholder="%">' +
        ' <span class="as-edit-actions">' +
        '   <button type="button" class="as-save btn btn-sm btn-success">✓</button>' +
        '   <button type="button" class="as-clear btn btn-sm btn-warning" title="Quitar override y volver a regla">×</button>' +
        '   <button type="button" class="as-cancel btn btn-sm btn-light">Esc</button>' +
        ' </span>';
      var input = cell.querySelector('.as-edit-input');
      input.focus(); input.select();
      function save(pct) {
        var fd = new FormData();
        fd.append('pid', pid); fd.append('ovid', ovid); fd.append('pct', pct);
        fetch('auto_specials_override.php', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(r => r.json())
          .then(j => {
            if (j.ok) location.reload();
            else { alert('Error: ' + (j.error || 'unknown')); cell.innerHTML = original; }
          })
          .catch(e => { alert('Error: ' + e); cell.innerHTML = original; });
      }
      cell.querySelector('.as-save').addEventListener('click', function() { save(input.value.trim()); });
      cell.querySelector('.as-clear').addEventListener('click', function() { save(''); });
      cell.querySelector('.as-cancel').addEventListener('click', function() { cell.innerHTML = original; });
      input.addEventListener('keydown', function(ev) {
        if (ev.key === 'Enter') { ev.preventDefault(); save(input.value.trim()); }
        else if (ev.key === 'Escape') { cell.innerHTML = original; }
      });
    });
  });

  // Edición inline del PVP sug (con IVA). Al guardar, calculamos el % equivalente.
  document.querySelectorAll('.as-pvp-sug-cell').forEach(function(cell) {
    cell.addEventListener('click', function(e) {
      if (cell.querySelector('.as-edit-input')) return;
      const pid = cell.dataset.pid;
      const ovid = cell.dataset.ovid;
      const pvpVarSin = parseFloat(cell.dataset.pvpVar || '0');
      const ivaMult   = parseFloat(cell.dataset.ivaMult || '1.21');
      const currentIva = cell.dataset.pvpSugIva || '';
      const original = cell.innerHTML;
      if (pvpVarSin <= 0) return;
      cell.innerHTML =
        '<input type="number" min="0" step="0.01" class="as-edit-input" value="' + currentIva + '" placeholder="€ c/IVA" style="width:84px">' +
        ' <span class="as-edit-actions">' +
        '   <button type="button" class="as-save btn btn-sm btn-success">✓</button>' +
        '   <button type="button" class="as-clear btn btn-sm btn-warning" title="Quitar override y volver a regla">×</button>' +
        '   <button type="button" class="as-cancel btn btn-sm btn-light">Esc</button>' +
        ' </span>';
      const input = cell.querySelector('.as-edit-input');
      input.focus(); input.select();
      function save(pvpIvaStr) {
        const fd = new FormData();
        fd.append('pid', pid); fd.append('ovid', ovid);
        if (pvpIvaStr === '' || pvpIvaStr === 'clear') {
          fd.append('pct', '');
        } else {
          const pvpIva = parseFloat(pvpIvaStr.replace(',', '.'));
          if (isNaN(pvpIva) || pvpIva <= 0) { alert('Precio inválido'); return; }
          const pvpSin = pvpIva / ivaMult;
          const pct = (1 - pvpSin / pvpVarSin) * 100;
          if (pct < 0 || pct > 100) {
            alert('El PVP introducido (' + pvpIva.toFixed(2) + ' c/IVA) implica un descuento ' + pct.toFixed(1) + '% fuera de 0–100%.');
            return;
          }
          fd.append('pct', pct.toFixed(2));
          fd.append('nota', 'PVP manual ' + pvpIva.toFixed(2) + ' € c/IVA');
        }
        fetch('auto_specials_override.php', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(r => r.json())
          .then(j => { if (j.ok) location.reload(); else { alert('Error: ' + (j.error || 'unknown')); cell.innerHTML = original; } })
          .catch(e => { alert('Error: ' + e); cell.innerHTML = original; });
      }
      cell.querySelector('.as-save').addEventListener('click', function() { save(input.value.trim()); });
      cell.querySelector('.as-clear').addEventListener('click', function() { save(''); });
      cell.querySelector('.as-cancel').addEventListener('click', function() { cell.innerHTML = original; });
      input.addEventListener('keydown', function(ev) {
        if (ev.key === 'Enter') { ev.preventDefault(); save(input.value.trim()); }
        else if (ev.key === 'Escape') { cell.innerHTML = original; }
      });
    });
  });
});
</script>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
