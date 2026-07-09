<?php
	$aDatos = tep_db_query( 'select pg.attributes, p.products_id, p.products_status, p.products_quantity, p.check_stock, p.products_min_order_qty, p.products_image, p.products_model, p.products_tax_class_id, pd.products_name, IF(s.specials_new_products_price is not null, p.products_price, NULL) as products_price_anterior, IF(s.specials_new_products_price is not null, s.specials_new_products_price, p.products_price) as products_price
							from products p 
							inner join products_together pg on (p.products_id = pg.products_id)
							inner join products_description pd on (p.products_id = pd.products_id)
							left join specials s on (s.products_id = p.products_id and s.customers_group_id = "' . $nCustomerGroupId . '")
							where pg.parent_id = "' . (int)$sGetProductsId . '" and p.products_status = 1 and pd.language_id = 3' );
							
	// Theme
	include( DIR_THEME. 'html/components/' . basename(__FILE__) );
?>