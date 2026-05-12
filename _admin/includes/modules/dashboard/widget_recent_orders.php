<?php
/**
 * Dashboard Widget: Recent Orders Table
 * Uses real colors from orders_status.color field
 */
require_once(DIR_WS_FUNCTIONS . 'orders.php');

$recent_orders_query = tep_db_query("SELECT o.orders_id, o.customers_name, o.payment_method, o.date_purchased, o.currency,
    os.orders_status_name, os.orders_status_id, os.color as status_color, ot.text as order_total, ot.value as order_value
    FROM " . TABLE_ORDERS . " o
    LEFT JOIN " . TABLE_ORDERS_TOTAL . " ot ON o.orders_id = ot.orders_id AND ot.class = 'ot_total'
    INNER JOIN " . TABLE_ORDERS_STATUS . " os ON o.orders_status = os.orders_status_id AND os.language_id = '" . (int)$languages_id . "'
    ORDER BY o.orders_id DESC LIMIT 10");
?>

<div class="dash-widget" id="widget-recent-orders" data-widget="recent-orders">
    <div class="dash-widget-header">
        <h3><i class="fa fa-shopping-basket"></i> Ultimos Pedidos</h3>
        <div class="dash-widget-actions">
            <button class="dash-widget-action" onclick="dashRefreshWidget('recent-orders')" title="Refrescar"><i class="fa fa-sync-alt"></i></button>
        </div>
    </div>
    <div class="dash-widget-body no-padding">
        <table class="dash-table">
            <thead>
                <tr>
                    <th>#Pedido</th>
                    <th>Cliente</th>
                    <th>Total</th>
                    <th>Pago</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (tep_db_num_rows($recent_orders_query) > 0) {
                    while ($order = tep_db_fetch_array($recent_orders_query)) {
                        // Color real del estado, gris por defecto
                        $bg_color = (!empty($order['status_color']) && $order['status_color'] != '#000000')
                            ? $order['status_color']
                            : '#9e9e9e';

                        // Texto claro u oscuro segun el fondo
                        $text_color = isDarkColor($bg_color) ? '#ffffff' : '#333333';
                ?>
                <tr>
                    <td><a class="dash-order-id" href="<?php echo tep_href_link(FILENAME_ORDERS, 'oID=' . $order['orders_id'] . '&action=edit'); ?>">#<?php echo $order['orders_id']; ?></a></td>
                    <td><?php echo tep_db_prepare_input($order['customers_name']); ?></td>
                    <td><strong><?php echo strip_tags($order['order_total']); ?></strong></td>
                    <td><?php echo tep_db_prepare_input($order['payment_method']); ?></td>
                    <td><?php echo tep_datetime_short($order['date_purchased']); ?></td>
                    <td>
                        <span class="dash-badge" style="background-color: <?php echo htmlspecialchars($bg_color); ?>; color: <?php echo $text_color; ?>;">
                            <?php echo $order['orders_status_name']; ?>
                        </span>
                    </td>
                </tr>
                <?php
                    }
                } else {
                    echo '<tr><td colspan="6"><div class="dash-empty"><i class="fa fa-inbox"></i><p>No hay pedidos recientes</p></div></td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <a class="dash-view-all" href="<?php echo tep_href_link(FILENAME_ORDERS); ?>">Ver todos los pedidos <i class="fa fa-arrow-right"></i></a>
</div>
