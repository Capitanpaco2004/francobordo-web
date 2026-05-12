<?php
	$sIdCategoriasSrch = getRecursiveIdCategories( $_aAllCategorias, $current_category_id ) . $current_category_id;

	$sSql = 'SELECT ' . SQL_SELECT . ' p.products_id, p.products_ship_free, p.products_image, p.products_tax_class_id, p.products_price, pd.products_name, p.products_quantity
			 FROM products p 
			 INNER JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ON (p.products_id = pd.products_id)
			 INNER JOIN ' . TABLE_PRODUCTS_TO_CATEGORIES . ' ptc ON (p.products_id = ptc.products_id)
			 INNER JOIN featured f ON (p.products_id = f.products_id)
			 WHERE p.products_status = 1 AND 
			 pd.language_id = ' . (int)$languages_id . ' AND
			 f.status = 1' . 
			 ($sIdCategoriasSrch != '' ? ' AND ptc.categories_id in (' . $sIdCategoriasSrch . ')' : '') . '
			 GROUP BY p.products_id
			 ORDER BY rand()
			 LIMIT 12';

	// Obtenemos los productos cambiando el precio
	$aAux = changePriceCustomer( $sSql, array( 'PAGINAR' => false ) );

	// Mostramos productos
	if( $aAux['TOTAL'] > 0 )
	{
		$aProductos = $aAux['PRODUCTOS'];
		include( DIR_THEME. 'html/components/' . basename(__FILE__) );
	}
?>