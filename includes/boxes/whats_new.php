<?php
	// Consulta con los productos por defecto
	$sSqlDefault = 'SELECT p.products_id, p.products_image, p.products_tax_class_id, p.products_price, p.products_model, pd.products_name 
					FROM products p 
					LEFT JOIN products_description pd on p.products_id = pd.products_id 
					WHERE products_status = 1 AND pd.language_id = ' . (int)$languages_id . ' 
					ORDER BY p.products_date_added DESC 
					LIMIT 15';

	// Si estamos en una categoria intentamos obtener los productos de la categoria
	if( isset( $current_category_id ) && $current_category_id != '' )
	{
		// Consulta con los productos de la categoria
		$sSql = 'SELECT p.products_id, p.products_image, p.products_tax_class_id, p.products_price, p.products_model, pd.products_name 
				 FROM products p 
				 INNER JOIN products_description pd on p.products_id = pd.products_id
				 INNER JOIN  products_to_categories ptc on(ptc.products_id = p.products_id)
				 WHERE ptc.categories_id = ' . $current_category_id . ' AND products_status = 1 AND pd.language_id = ' . (int)$languages_id . ' 
				 ORDER BY p.products_date_added DESC 
				 LIMIT 15';

		// Obtenemos los productos cambiando el precio segun tipo de cliente
		$aAux = changePriceCustomer( $sSql, array( 'PAGINAR' => false ) );

		// Si no encontramos productos hacemos la consulta por defecto
		if( $aAux['TOTAL'] > 0 )
		{
			// Obtenemos los productos cambiando el precio segun tipo de cliente
			$aAux = changePriceCustomer( $sSqlDefault, array( 'PAGINAR' => false ) );
		}
	}
	else
	{
		// Obtenemos los productos cambiando el precio segun tipo de cliente
		$aAux = changePriceCustomer( $sSqlDefault, array( 'PAGINAR' => false ) );
	}

	// Incluimos el html
	if( $aAux['TOTAL'] > 0 )
	{
		$aProductos = $aAux['PRODUCTOS'];
		include( DIR_THEME_ROOT . 'html/boxes/' . basename(__FILE__) );
	}
?>