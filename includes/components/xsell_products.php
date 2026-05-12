<?php
	// Cantidad de elementos
	$nElementos = 6;

	// Eliminamos si el id viene con atributos
	$sIdProducto = preg_replace( '/\{.+$/i', '', $_GET['products_id'] );

	// Array de productos
	$aProductos = array();

	// Consulta
	$sSql = 'SELECT distinct p.products_id, p.products_image, p.products_status, pd.products_name, p.products_tax_class_id, products_price
			 FROM ' . TABLE_PRODUCTS_XSELL . ' xp
			 INNER JOIN ' . TABLE_PRODUCTS . ' p ON(xp.xsell_id = p.products_id)
			 INNER JOIN products_description pd ON(p.products_id = pd.products_id)
			 WHERE xp.products_id = "' . (int)$sIdProducto . '" and language_id = ' . (int)$languages_id . ' AND products_status = 1 
			 ORDER BY RAND() LIMIT ' . $nElementos;


	// Obtenemos los productos cambiando el precio segun tipo de cliente
	$aAux = changePriceCustomer( $sSql, array( 'PAGINAR' => false, 'PRODUCTS_ARRAY' => true ) );

	// Si hemos obtenido resultados pintamos
	if( $aAux['TOTAL'] > 0 )
	{
		$aProductos = $aAux['PRODUCTOS'];

		// Idioma
		include( DIR_WS_LANGUAGES . $language . '/' . basename(__FILE__) );

		// Template	
		include( DIR_THEME. 'html/components/' . basename(__FILE__) );
	}
?>