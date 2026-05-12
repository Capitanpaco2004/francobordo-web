<?php
/**
 * Dashboard Widget: KPI Cards
 * Each KPI is individually sortable, hideable, and resettable
 * Optimized: 4 queries instead of 9
 */

// Query 1: Ventas hoy + ayer + mes actual + mes anterior (en una sola query)
$sales_query = tep_db_query("SELECT
    COALESCE(SUM(CASE WHEN DATE(o.date_purchased) = CURDATE() THEN ot.value END), 0) as today_total,
    COUNT(DISTINCT CASE WHEN DATE(o.date_purchased) = CURDATE() THEN o.orders_id END) as today_qty,
    COALESCE(SUM(CASE WHEN DATE(o.date_purchased) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN ot.value END), 0) as yesterday_total,
    COUNT(DISTINCT CASE WHEN DATE(o.date_purchased) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN o.orders_id END) as yesterday_qty,
    COALESCE(SUM(CASE WHEN YEAR(o.date_purchased) = YEAR(CURDATE()) AND MONTH(o.date_purchased) = MONTH(CURDATE()) THEN ot.value END), 0) as month_total,
    COALESCE(SUM(CASE WHEN YEAR(o.date_purchased) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
        AND MONTH(o.date_purchased) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
        AND DAY(o.date_purchased) <= DAY(CURDATE()) THEN ot.value END), 0) as last_month_total
    FROM " . TABLE_ORDERS . " o
    INNER JOIN " . TABLE_ORDERS_TOTAL . " ot ON o.orders_id = ot.orders_id AND ot.class = 'ot_total'
    WHERE o.date_purchased >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)");
$sales = tep_db_fetch_array($sales_query);

// Query 2: Clientes nuevos hoy + ayer
$customers_query = tep_db_query("SELECT
    COUNT(CASE WHEN DATE(customers_info_date_account_created) = CURDATE() THEN 1 END) as today_qty,
    COUNT(CASE WHEN DATE(customers_info_date_account_created) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN 1 END) as yesterday_qty
    FROM " . TABLE_CUSTOMERS_INFO . "
    WHERE customers_info_date_account_created >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
$customers = tep_db_fetch_array($customers_query);

// Query 3: Productos activos + stock bajo
$products_query = tep_db_query("SELECT
    COUNT(*) as active,
    SUM(CASE WHEN products_quantity <= " . (int)STOCK_REORDER_LEVEL . " THEN 1 ELSE 0 END) as low_stock
    FROM " . TABLE_PRODUCTS . " WHERE products_status > 0");
$products = tep_db_fetch_array($products_query);

// Query 4: Usuarios online (solo sesiones activas últimos 15 min, excluyendo bots)
$xx_mins_ago = (time() - 900);
$query_online = tep_db_query("SELECT COUNT(*) as qty FROM " . TABLE_WHOS_ONLINE . " WHERE time_last_click >= '" . $xx_mins_ago . "' AND customer_id >= 0");
$online = tep_db_fetch_array($query_online);

// Funciones auxiliares
if (!function_exists('dash_trend')) {
    function dash_trend($current, $previous) {
        if ($previous == 0) return ['class' => 'neutral', 'icon' => 'fa-minus', 'text' => '--'];
        $diff = (($current - $previous) / $previous) * 100;
        if ($diff > 0) return ['class' => 'up', 'icon' => 'fa-arrow-up', 'text' => '+' . number_format($diff, 1) . '%'];
        if ($diff < 0) return ['class' => 'down', 'icon' => 'fa-arrow-down', 'text' => number_format($diff, 1) . '%'];
        return ['class' => 'neutral', 'icon' => 'fa-minus', 'text' => '0%'];
    }
}

$trend_today = dash_trend($sales['today_total'], $sales['yesterday_total']);
$trend_month = dash_trend($sales['month_total'], $sales['last_month_total']);
$trend_orders = dash_trend($sales['today_qty'], $sales['yesterday_qty']);
$trend_customers = dash_trend($customers['today_qty'], $customers['yesterday_qty']);

// KPI registry: id => html content
$kpi_cards = [
    'sales-today' => [
        'label' => 'Ventas Hoy',
        'icon' => 'bg-success', 'fa' => 'fa-euro-sign',
        'value' => $currencies->format($sales['today_total']),
        'trend_class' => $trend_today['class'],
        'trend_icon' => $trend_today['icon'],
        'trend_text' => $trend_today['text'] . ' vs ayer',
    ],
    'sales-month' => [
        'label' => 'Ventas Este Mes',
        'icon' => 'bg-primary', 'fa' => 'fa-chart-line',
        'value' => $currencies->format($sales['month_total']),
        'trend_class' => $trend_month['class'],
        'trend_icon' => $trend_month['icon'],
        'trend_text' => $trend_month['text'] . ' vs mes ant.',
    ],
    'orders-today' => [
        'label' => 'Pedidos Hoy',
        'icon' => 'bg-warning', 'fa' => 'fa-shopping-basket',
        'value' => number_format($sales['today_qty']),
        'trend_class' => $trend_orders['class'],
        'trend_icon' => $trend_orders['icon'],
        'trend_text' => $trend_orders['text'] . ' vs ayer',
    ],
    'new-customers' => [
        'label' => 'Clientes Nuevos Hoy',
        'icon' => 'bg-purple', 'fa' => 'fa-user-plus',
        'value' => number_format($customers['today_qty']),
        'trend_class' => $trend_customers['class'],
        'trend_icon' => $trend_customers['icon'],
        'trend_text' => $trend_customers['text'] . ' vs ayer',
    ],
    'products-active' => [
        'label' => 'Productos Activos',
        'icon' => 'bg-turquoise', 'fa' => 'fa-boxes',
        'value' => number_format($products['active']),
        'trend_class' => $products['low_stock'] > 0 ? 'down' : 'up',
        'trend_icon' => $products['low_stock'] > 0 ? 'fa-exclamation-triangle' : 'fa-check',
        'trend_text' => $products['low_stock'] > 0 ? $products['low_stock'] . ' con stock bajo' : 'Stock OK',
    ],
    'users-online' => [
        'label' => 'Usuarios Online',
        'icon' => 'bg-info', 'fa' => 'fa-eye',
        'value' => number_format($online['qty']),
        'trend_class' => 'neutral',
        'trend_icon' => '',
        'trend_text' => '<span class="dash-online-dot"></span> En tiempo real',
    ],
];

// Apply saved KPI config (order + visibility + sizes)
if ($saved_config && isset($saved_config['kpis'])) {
    $kpi_conf = $saved_config['kpis'];
    // Apply order
    if (isset($kpi_conf['order']) && is_array($kpi_conf['order'])) {
        $ordered = [];
        foreach ($kpi_conf['order'] as $kid) {
            if (isset($kpi_cards[$kid])) $ordered[$kid] = $kpi_cards[$kid];
        }
        foreach ($kpi_cards as $kid => $kdata) {
            if (!isset($ordered[$kid])) $ordered[$kid] = $kdata;
        }
        $kpi_cards = $ordered;
    }
    // Apply visibility
    if (isset($kpi_conf['hidden']) && is_array($kpi_conf['hidden'])) {
        foreach ($kpi_conf['hidden'] as $kid) {
            if (isset($kpi_cards[$kid])) $kpi_cards[$kid]['hidden'] = true;
        }
    }
    // Apply sizes
    if (isset($kpi_conf['sizes']) && is_array($kpi_conf['sizes'])) {
        foreach ($kpi_conf['sizes'] as $kid => $sz) {
            if (isset($kpi_cards[$kid])) $kpi_cards[$kid]['kpi_size'] = (int)$sz;
        }
    }
}
?>

<div class="dash-kpis" id="dash-kpis-grid">
    <?php foreach ($kpi_cards as $kpi_id => $kpi):
        $hidden_class = !empty($kpi['hidden']) ? ' dash-kpi-hidden' : '';
        $kpi_size = isset($kpi['kpi_size']) ? (int)$kpi['kpi_size'] : 2;
    ?>
    <div class="dash-kpi dash-kpi-w-<?php echo $kpi_size; ?><?php echo $hidden_class; ?>"
         data-kpi="<?php echo $kpi_id; ?>"
         data-kpi-size="<?php echo $kpi_size; ?>">
        <button class="dash-kpi-hide-btn" title="Ocultar KPI"><i class="fa fa-times"></i></button>
        <div class="dash-kpi-icon <?php echo $kpi['icon']; ?>"><i class="fa <?php echo $kpi['fa']; ?>"></i></div>
        <div class="dash-kpi-value"><?php echo $kpi['value']; ?></div>
        <div class="dash-kpi-label"><?php echo $kpi['label']; ?></div>
        <div class="dash-kpi-trend <?php echo $kpi['trend_class']; ?>">
            <?php if ($kpi['trend_icon']): ?><i class="fa <?php echo $kpi['trend_icon']; ?>"></i> <?php endif; ?>
            <?php echo $kpi['trend_text']; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
