<?php
/**
 * Dashboard Widget: Top 10 Most Ordered Products
 */
$top_products_query = tep_db_query("SELECT p.products_id, pd.products_name, p.products_ordered
    FROM " . TABLE_PRODUCTS . " p
    INNER JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON p.products_id = pd.products_id AND pd.language_id = '" . (int)$languages_id . "'
    WHERE p.products_ordered > 0
    ORDER BY p.products_ordered DESC LIMIT 10");
?>

<div class="dash-widget" id="widget-top-products" data-widget="top-products">
    <div class="dash-widget-header">
        <h3><i class="fa fa-trophy"></i> Top Productos</h3>
        <div class="dash-widget-actions">
            <button class="dash-widget-action" onclick="dashRefreshWidget('top-products')" title="Refrescar"><i class="fa fa-sync-alt"></i></button>
        </div>
    </div>
    <div class="dash-widget-body no-padding">
        <?php
        $rank = 0;
        if (tep_db_num_rows($top_products_query) > 0) {
            while ($product = tep_db_fetch_array($top_products_query)) {
                $rank++;
                $rank_class = 'rank-other';
                if ($rank == 1) $rank_class = 'rank-1';
                elseif ($rank == 2) $rank_class = 'rank-2';
                elseif ($rank == 3) $rank_class = 'rank-3';
        ?>
        <div class="dash-product-row">
            <div class="dash-product-rank <?php echo $rank_class; ?>"><?php echo $rank; ?></div>
            <div class="dash-product-info">
                <a class="dash-product-name" href="<?php echo tep_href_link(FILENAME_CATEGORIES, 'action=new_product&pID=' . $product['products_id']); ?>"
                   title="<?php echo htmlspecialchars($product['products_name']); ?>">
                    <?php echo tep_db_prepare_input($product['products_name']); ?>
                </a>
            </div>
            <div class="dash-product-sales"><?php echo number_format($product['products_ordered']); ?></div>
        </div>
        <?php
            }
        } else {
            echo '<div class="dash-empty"><i class="fa fa-box-open"></i><p>No hay datos de ventas</p></div>';
        }
        ?>
    </div>
</div>
