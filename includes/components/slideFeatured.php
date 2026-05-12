<?php
	// Variables
	$nRandom = random();
		
	// Consulta para obtener los productos destacados
	$sSql = 'SELECT ' . SQL_SELECT . ' p.products_ship_free, p.products_id, p.products_image, p.products_quantity, p.products_tax_class_id, p.products_price, pd.products_name, pd.products_description
			 FROM products p 
			 INNER JOIN products_description pd ON (pd.products_id = p.products_id)
			 LEFT JOIN featured f ON (p.products_id = f.products_id)
			 ' . SQL_FROM . '
			 WHERE p.products_status = 1 AND f.status = 1 AND pd.language_id = ' . (int)$languages_id . '
			 ORDER BY RAND(' . $nRandom . ') DESC 
			 LIMIT 10';

	// Obtenemos los productos cambiando el precio
	$aAux = changePriceCustomer( $sSql, array( 'PAGINAR' => false ) );

	// Mostramos productos
	if( $aAux['TOTAL'] > 0 )
	{
		$aProductos = $aAux['PRODUCTOS'];
		include( DIR_THEME. 'html/components/' . basename(__FILE__) );
	}
?>