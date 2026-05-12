<?php
/**
 * Dashboard Widget: Low Stock Alerts
 */
$stock_query = tep_db_query("SELECT p.products_id, pd.products_name, p.products_quantity, p.products_quantity_deseada
    FROM " . TABLE_PRODUCTS . " p
    INNER JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON p.products_id = pd.products_id AND pd.language_id = '" . (int)$languages_id . "'
    WHERE (p.products_quantity <= " . (int)STOCK_REORDER_LEVEL . " OR p.products_quantity < p.products_quantity_deseada)
    AND p.products_status > 0
    ORDER BY p.products_quantity ASC LIMIT 15");
?>

<div class="dash-widget" id="widget-low-stock" data-widget="low-stock">
    <div class="dash-widget-header">
        <h3><i class="fa fa-exclamation-triangle"></i> Alertas de Stock</h3>
        <div class="dash-widget-actions">
            <button class="dash-widget-action" onclick="dashRefreshWidget('low-stock')" title="Refrescar"><i class="fa fa-sync-alt"></i></button>
        </div>
    </div>
    <div class="dash-widget-body no-padding">
        <div class="dash-alert-list">
            <?php
            if (tep_db_num_rows($stock_query) > 0) {
                while ($stock = tep_db_fetch_array($stock_query)) {
                    $icon_class = ($stock['products_quantity'] <= 0) ? 'danger' : 'warning';
            ?>
            <div class="dash-alert-item">
                <div class="dash-alert-icon <?php echo $icon_class; ?>">
                    <i class="fa fa-<?php echo ($stock['products_quantity'] <= 0) ? 'times-circle' : 'exclamation-triangle'; ?>"></i>
                </div>
                <div class="dash-alert-text">
                    <a href="<?php echo tep_href_link(FILENAME_CATEGORIES, 'action=new_product&pID=' . $stock['products_id']); ?>" style="color: inherit;">
                        <?php echo tep_db_prepare_input($stock['products_name']); ?>
                    </a>
                </div>
                <div class="dash-alert-qty"><?php echo (int)$stock['products_quantity']; ?></div>
            </div>
            <?php
                }
            } else {
            ?>
                <div class="dash-empty" style="padding: 30px;"><i class="fa fa-check-circle" style="color: var(--dash-success);"></i><p>Todo el stock esta OK</p></div>
            <?php } ?>
        </div>
    </div>
    <a class="dash-view-all" href="<?php echo tep_href_link(defined('FILENAME_STATS_STOCK') ? FILENAME_STATS_STOCK : 'stats_stock.php'); ?>">Ver informe de stock <i class="fa fa-arrow-right"></i></a>
</div>
