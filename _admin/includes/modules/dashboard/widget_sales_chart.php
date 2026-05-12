<?php
/**
 * Dashboard Widget: Sales Chart (30 days / 12 months)
 * Optimized: 2 queries instead of 42
 */

// Ventas últimos 30 días - UNA sola query con GROUP BY
$date_from = date('Y-m-d', strtotime('-29 days'));
$date_to = date('Y-m-d');
$daily_query = tep_db_query("SELECT DATE(o.date_purchased) as day_date,
    COALESCE(SUM(ot.value), 0) as total, COUNT(DISTINCT o.orders_id) as qty
    FROM " . TABLE_ORDERS . " o
    INNER JOIN " . TABLE_ORDERS_TOTAL . " ot ON o.orders_id = ot.orders_id AND ot.class = 'ot_total'
    WHERE DATE(o.date_purchased) BETWEEN '" . tep_db_input($date_from) . "' AND '" . tep_db_input($date_to) . "'
    GROUP BY DATE(o.date_purchased)
    ORDER BY day_date ASC");

$daily_data = [];
while ($row = tep_db_fetch_array($daily_query)) {
    $daily_data[$row['day_date']] = $row;
}

$sales_30_days = [];
$labels_30_days = [];
$orders_30_days = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $sales_30_days[] = isset($daily_data[$date]) ? round($daily_data[$date]['total'], 2) : 0;
    $orders_30_days[] = isset($daily_data[$date]) ? (int)$daily_data[$date]['qty'] : 0;
    $labels_30_days[] = date('d M', strtotime($date));
}

// Ventas últimos 12 meses - UNA sola query con GROUP BY
$month_from = date('Y-m', strtotime('-11 months'));
$monthly_query = tep_db_query("SELECT YEAR(o.date_purchased) as yr, MONTH(o.date_purchased) as mn,
    COALESCE(SUM(ot.value), 0) as total, COUNT(DISTINCT o.orders_id) as qty
    FROM " . TABLE_ORDERS . " o
    INNER JOIN " . TABLE_ORDERS_TOTAL . " ot ON o.orders_id = ot.orders_id AND ot.class = 'ot_total'
    WHERE o.date_purchased >= '" . tep_db_input($month_from . '-01') . "'
    GROUP BY YEAR(o.date_purchased), MONTH(o.date_purchased)
    ORDER BY yr ASC, mn ASC");

$monthly_data = [];
while ($row = tep_db_fetch_array($monthly_query)) {
    $key = $row['yr'] . '-' . str_pad($row['mn'], 2, '0', STR_PAD_LEFT);
    $monthly_data[$key] = $row;
}

$sales_12_months = [];
$labels_12_months = [];
$orders_12_months = [];
$formatter_month = new IntlDateFormatter('es_ES', IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'Europe/Madrid', IntlDateFormatter::GREGORIAN, 'MMM yy');

for ($i = 11; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-{$i} months"));
    $sales_12_months[] = isset($monthly_data[$key]) ? round($monthly_data[$key]['total'], 2) : 0;
    $orders_12_months[] = isset($monthly_data[$key]) ? (int)$monthly_data[$key]['qty'] : 0;
    $dt = new DateTime($key . '-01');
    $labels_12_months[] = ucfirst($formatter_month->format($dt));
}
?>

<div class="dash-widget" id="widget-sales-chart" data-widget="sales-chart">
    <div class="dash-widget-header">
        <h3><i class="fa fa-chart-area"></i> Ventas</h3>
        <div class="dash-widget-actions">
            <div class="dash-chart-tabs">
                <button class="dash-chart-tab active" data-chart-period="30days">30 Dias</button>
                <button class="dash-chart-tab" data-chart-period="12months">12 Meses</button>
            </div>
            <button class="dash-widget-action" onclick="dashRefreshWidget('sales-chart')" title="Refrescar"><i class="fa fa-sync-alt"></i></button>
        </div>
    </div>
    <div class="dash-widget-body">
        <div class="dash-chart-container">
            <canvas id="salesChart"></canvas>
        </div>
    </div>
</div>

<script>
var dashSalesData = {
    '30days': {
        labels: <?php echo json_encode($labels_30_days); ?>,
        sales: <?php echo json_encode($sales_30_days); ?>,
        orders: <?php echo json_encode($orders_30_days); ?>
    },
    '12months': {
        labels: <?php echo json_encode($labels_12_months); ?>,
        sales: <?php echo json_encode($sales_12_months); ?>,
        orders: <?php echo json_encode($orders_12_months); ?>
    }
};
</script>
