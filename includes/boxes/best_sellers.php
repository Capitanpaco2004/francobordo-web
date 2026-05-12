<?php
	// Si nos encontramos en un categoria
	if( isset( $current_category_id ) && $current_category_id > 0 ) 
	{
		$sSql = 'SELECT DISTINCT p.products_id, p.products_price, p.products_tax_class_id, p.products_model, pd.products_name 
				 FROM products p 
				 INNER JOIN products_description pd ON (p.products_id = pd.products_id)
				 INNER JOIN products_to_categories p2c ON (p.products_id = p2c.products_id)
				 INNER JOIN categories c ON (p2c.categories_id = c.categories_id)
				 WHERE p.products_status = 1 AND p.products_ordered > 0 AND pd.language_id = ' . (int)$languages_id . ' AND (c.categories_id = ' . (int)$current_category_id . ' OR c.parent_id = ' . (int)$current_category_id . ')
				 ORDER BY p.products_ordered desc, pd.products_name
				 LIMIT 5';

		$aAux = changePriceCustomer( $sSql, array( 'PAGINAR' => false ) );
	}
	else
	{
		$sSql = 'select DISTINCT p.products_id, p.products_price, p.products_tax_class_id, p.products_model, pd.products_name
				 FROM products p 
				 INNER JOIN products_description pd ON(p.products_id = pd.products_id)
				 WHERE p.products_status = 1 AND p.products_ordered > 0 AND pd.language_id = ' . (int)$languages_id . '
				 ORDER BY p.products_ordered desc, pd.products_name
				 LIMIT 5';

		$aAux = changePriceCustomer( $sSql, array( 'PAGINAR' => false ) );
	}

	// Mostramos productos
	if( $aAux['TOTAL'] > 0 )
	{
		$aProductos = $aAux['PRODUCTOS'];
		include( DIR_THEME. 'html/boxes/' . basename(__FILE__) );
	}
?>