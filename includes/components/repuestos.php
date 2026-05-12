<?php
	// Variables
	$sIdProducto = preg_replace( '/\{.+$/i', '', $_GET['products_id'] );
	$aProductos = array();

	$sSql = 'SELECT ' . SQL_SELECT . ' rp.attributes, p.products_id, rp.alias, p.products_model, p.products_ship_free, p.products_tax_class_id, p.products_quantity, p.products_image, p.products_price, p.check_stock, p.products_min_order_qty, pd.products_id, pd.products_name
			 from products p
			 INNER JOIN products_description pd ON (pd.products_id = p.products_id)
			 INNER JOIN repuesto rp ON (rp.products_id_repuesto = p.products_id)
			 ' . SQL_FROM . '
			 WHERE rp.products_id = "' . (int)$sIdProducto . '" AND p.products_status = 1 AND pd.language_id = ' . $languages_id;

	// Obtenemos los productos cambiando el precio segun tipo de cliente
	$aAux = changePriceCustomer( $sSql, array( 'PAGINAR' => false ) );
	
	// Si contiene productos
	if( $aAux['TOTAL'] > 0 )
	{
		$aProductos = $aAux['PRODUCTOS'];
		include( DIR_THEME. 'html/components/' . basename(__FILE__) );
	}
?>