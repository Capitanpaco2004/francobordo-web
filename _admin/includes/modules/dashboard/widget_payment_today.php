<?php
/**
 * Dashboard Widget: Payment Methods - Today & Month
 */

// Ventas de hoy por método de pago
$pay_today_query = tep_db_query("SELECT o.payment_method, COALESCE(SUM(ot.value), 0) as total, COUNT(DISTINCT o.orders_id) as qty
    FROM " . TABLE_ORDERS . " o
    INNER JOIN " . TABLE_ORDERS_TOTAL . " ot ON o.orders_id = ot.orders_id AND ot.class = 'ot_total'
    WHERE DATE(o.date_purchased) = CURDATE()
    GROUP BY o.payment_method
    ORDER BY total DESC");

// Ventas del mes por método de pago
$pay_month_query = tep_db_query("SELECT o.payment_method, COALESCE(SUM(ot.value), 0) as total, COUNT(DISTINCT o.orders_id) as qty
    FROM " . TABLE_ORDERS . " o
    INNER JOIN " . TABLE_ORDERS_TOTAL . " ot ON o.orders_id = ot.orders_id AND ot.class = 'ot_total'
    WHERE YEAR(o.date_purchased) = YEAR(CURDATE()) AND MONTH(o.date_purchased) = MONTH(CURDATE())
    GROUP BY o.payment_method
    ORDER BY total DESC");

$pay_colors = ['#5d9cec', '#8dca35', '#ffbd4a', '#7266ba', '#5fbeaa', '#fb6d9d', '#34d3eb', '#da3610'];

// Nombre mes
$formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'Europe/Madrid', IntlDateFormatter::GREGORIAN, 'MMMM');
$month_name = ucfirst($formatter->format(new DateTime()));

// Datos para gráfico
$pay_month_labels = [];
$pay_month_data = [];
$pay_month_items = [];
while ($pm = tep_db_fetch_array($pay_month_query)) {
    $pay_month_labels[] = $pm['payment_method'];
    $pay_month_data[] = round($pm['total'], 2);
    $pay_month_items[] = $pm;
}
?>

<div class="dash-widget" id="widget-payment-methods" data-widget="payment-methods">
    <div class="dash-widget-header">
        <h3><i class="fa fa-credit-card"></i> Metodos de Pago</h3>
        <div class="dash-widget-actions">
            <div class="dash-chart-tabs">
                <button class="dash-chart-tab active" data-pay-tab="today">Hoy</button>
                <button class="dash-chart-tab" data-pay-tab="month"><?php echo $month_name; ?></button>
            </div>
        </div>
    </div>
    <div class="dash-widget-body">
        <!-- Tab Hoy -->
        <div id="pay-tab-today" class="dash-pay-content">
            <?php
            $total_today_pay = 0;
            $idx = 0;
            $pay_today_items = [];
            while ($pt = tep_db_fetch_array($pay_today_query)) {
                $pay_today_items[] = $pt;
                $total_today_pay += $pt['total'];
            }

            if (count($pay_today_items) > 0) {
                foreach ($pay_today_items as $pt) {
                    $color = $pay_colors[$idx % count($pay_colors)];
            ?>
            <div class="dash-payment-item">
                <div class="dash-payment-name">
                    <span class="dash-payment-dot" style="background: <?php echo $color; ?>"></span>
                    <?php echo tep_db_prepare_input($pt['payment_method']); ?>
                    <span style="color: var(--dash-text-light); font-size: 11px;">(<?php echo $pt['qty']; ?>)</span>
                </div>
                <div class="dash-payment-amount"><?php echo $currencies->format($pt['total']); ?></div>
            </div>
            <?php
                    $idx++;
                }
            ?>
            <div class="dash-payment-item" style="border-top: 2px solid var(--dash-border); margin-top: 8px; padding-top: 12px;">
                <div class="dash-payment-name"><strong>Total</strong></div>
                <div class="dash-payment-amount" style="font-size: 16px;"><?php echo $currencies->format($total_today_pay); ?></div>
            </div>
            <?php } else { ?>
                <div class="dash-empty"><i class="fa fa-credit-card"></i><p>No hay ventas hoy</p></div>
            <?php } ?>
        </div>

        <!-- Tab Mes -->
        <div id="pay-tab-month" class="dash-pay-content" style="display: none;">
            <?php
            $total_month_pay = 0;
            $idx = 0;
            if (count($pay_month_items) > 0) {
                foreach ($pay_month_items as $pm) {
                    $color = $pay_colors[$idx % count($pay_colors)];
                    $total_month_pay += $pm['total'];
            ?>
            <div class="dash-payment-item">
                <div class="dash-payment-name">
                    <span class="dash-payment-dot" style="background: <?php echo $color; ?>"></span>
                    <?php echo tep_db_prepare_input($pm['payment_method']); ?>
                    <span style="color: var(--dash-text-light); font-size: 11px;">(<?php echo $pm['qty']; ?>)</span>
                </div>
                <div class="dash-payment-amount"><?php echo $currencies->format($pm['total']); ?></div>
            </div>
            <?php
                    $idx++;
                }
            ?>
            <div class="dash-payment-item" style="border-top: 2px solid var(--dash-border); margin-top: 8px; padding-top: 12px;">
                <div class="dash-payment-name"><strong>Total</strong></div>
                <div class="dash-payment-amount" style="font-size: 16px;"><?php echo $currencies->format($total_month_pay); ?></div>
            </div>
            <?php } else { ?>
                <div class="dash-empty"><i class="fa fa-credit-card"></i><p>No hay ventas este mes</p></div>
            <?php } ?>
        </div>
    </div>
</div>

<script>
var dashPaymentData = {
    labels: <?php echo json_encode($pay_month_labels); ?>,
    data: <?php echo json_encode($pay_month_data); ?>,
    colors: <?php echo json_encode(array_slice($pay_colors, 0, count($pay_month_data))); ?>
};
</script>
