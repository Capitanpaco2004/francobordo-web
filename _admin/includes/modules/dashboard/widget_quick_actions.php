<?php
/**
 * Dashboard Widget: Quick Actions
 */
?>

<div class="dash-widget" id="widget-quick-actions" data-widget="quick-actions">
    <div class="dash-widget-header">
        <h3><i class="fa fa-bolt"></i> Acciones Rapidas</h3>
    </div>
    <div class="dash-widget-body no-padding">
        <div class="dash-quick-actions">
            <?php if (tep_admin_check_boxes('orders.php')): ?>
            <a class="dash-quick-action" href="<?php echo tep_href_link(FILENAME_ORDERS); ?>">
                <i class="fa fa-shopping-basket"></i>
                <span>Pedidos</span>
            </a>
            <?php endif; ?>

            <?php if (tep_admin_check_boxes('catalog.php')): ?>
            <a class="dash-quick-action" href="<?php echo tep_href_link(FILENAME_CATEGORIES); ?>">
                <i class="fa fa-plus-circle"></i>
                <span>Productos</span>
            </a>
            <?php endif; ?>

            <?php if (tep_admin_check_boxes('customers.php')): ?>
            <a class="dash-quick-action" href="<?php echo tep_href_link(FILENAME_CUSTOMERS); ?>">
                <i class="fa fa-users"></i>
                <span>Clientes</span>
            </a>
            <?php endif; ?>

            <?php if (tep_admin_check_boxes('reports.php')): ?>
            <a class="dash-quick-action" href="<?php echo tep_href_link(defined('FILENAME_STATS_STOCK') ? FILENAME_STATS_STOCK : 'stats_stock.php'); ?>">
                <i class="fa fa-warehouse"></i>
                <span>Stock</span>
            </a>
            <?php endif; ?>

            <a class="dash-quick-action" href="<?php echo HTTPS_CATALOG_SERVER; ?>" target="_blank">
                <i class="fa fa-store"></i>
                <span>Ver Tienda</span>
            </a>

            <?php if (tep_admin_check_boxes('promotions.php')): ?>
            <a class="dash-quick-action" href="<?php echo tep_href_link('coupons.php'); ?>">
                <i class="fa fa-tag"></i>
                <span>Cupones</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>
