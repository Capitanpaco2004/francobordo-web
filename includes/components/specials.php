<?php
	// Variables
	$customer_group_id = getCustomerGroupId();

	/*
	Si está activado en la configuración, mostramos solo productos con stock
	@daniel.lucia
	#JDI-925-64407
	*/
	$sWhere = '';
	if (DISABLE_SHIPPING_5_DAYS == 'true') {
		$sWhere = 'p.products_quantity > 0 AND ';
	}


	// Consulta con los productos
	$sSql = 'SELECT ' . SQL_SELECT . ' p.products_quantity, p.products_ship_free, p.products_id, pd.products_name, p.products_tax_class_id, p.products_image, p.products_price
			 from products p
			 inner join products_description pd ON (p.products_id = pd.products_id)
			 INNER JOIN products_to_categories ptc ON (p.products_id = ptc.products_id)
			 ' . SQL_FROM . '
			 where ' . $sWhere . '
			 p.products_status = 1 AND
			 pd.language_id = ' . (int)$languages_id . ' AND 
			 s.status = 1 AND 
			 s.customers_group_id = ' . (int)$customer_group_id . ' AND
			 ((s.venta_flash = 1 AND s.portada_flash = 1) OR s.venta_flash = 0)
			 ORDER BY s.portada_flash DESC, s.specials_date_added DESC
			 LIMIT 9';

	// Obtenemos los productos cambiando el precio segun tipo de cliente
	$aAux = changePriceCustomer( $sSql, array( 'PAGINAR' => false ) );
	$aProductos = $aAux['PRODUCTOS'];

	// Mostramos productos
	if( $aAux['TOTAL'] > 0 )
		include( DIR_THEME. 'html/components/' . basename(__FILE__) );
?>