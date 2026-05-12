<?php
	// Idioma
	include( DIR_WS_LANGUAGES . $language . '/' . basename(__FILE__) );

	// Consulta con los productos
	$sSql = 'SELECT ' . SQL_SELECT . ' p.products_id, p.products_ship_free, pd.products_name, products_date_available as date_expected 
			 FROM products p 
			 INNER JOIN products_description pd ON (p.products_id = pd.products_id)
			 ' . SQL_FROM . '
			 WHERE to_days(products_date_available) >= to_days(now()) AND pd.language_id = ' . (int)$languages_id . '
			 ORDER BY products_date_available ASC LIMIT 3';

	// Obtenemos los productos cambiando el precio segun tipo de cliente
	$aAux = changePriceCustomer( $sSql, array( 'PAGINAR' => false ) );
	$aProductos = $aAux['PRODUCTOS'];

	// Mostramos productos
	if( count( $aProductos ) > 0 )
		include( DIR_THEME. 'html/components/' . basename(__FILE__) );
?>