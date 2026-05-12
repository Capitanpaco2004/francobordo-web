<?php
	// Variables
	$customer_group_id = getCustomerGroupId();

	// Consulta con los productos
	$sSql = 'SELECT p.products_id, p.products_quantity, pd.products_name, p.products_price, p.products_tax_class_id, p.products_image, s.specials_new_products_price
			 FROM ' . TABLE_PRODUCTS . ' p
			 INNER JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ON (p.products_id = pd.products_id)
			 INNER JOIN ' . TABLE_SPECIALS . ' s ON (s.products_id = p.products_id)
			 INNER JOIN products_to_categories ptc ON (p.products_id = ptc.products_id)
			 WHERE p.products_status = 1 AND pd.language_id = ' . (int)$languages_id . ' AND s.status = 1 AND s.customers_group_id = ' . (int)$customer_group_id . '
			 AND ptc.categories_id in (' . $sIdCategoriasUsar . ')
			 ORDER BY RAND() DESC 
			 LIMIT ' . MAX_RANDOM_SELECT_SPECIALS;

	// Obtenemos los productos cambiando el precio segun tipo de cliente
	$aAux = changePriceCustomer( $sSql, array( 'PAGINAR' => false ) );

	// Mostramos productos
	if( $aAux['TOTAL'] > 0 )
	{
		$aProductos = $aAux['PRODUCTOS'];
		include( DIR_THEME. 'html/boxes/' . basename(__FILE__) );
	}
?>