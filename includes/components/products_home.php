<?php

	/*
	Si está activado en la configuración, mostramos solo productos con stock
	@daniel.lucia
	#JDI-925-64407
	*/
	$sWhere = '';
	if (DISABLE_SHIPPING_5_DAYS == 'true') {
		$sWhere = 'p.products_quantity > 0 AND ';
	}

	$sSql = 'SELECT ' . SQL_SELECT . ' p.products_id, p.products_quantity, p.products_ship_free, p.products_image, p.products_tax_class_id, p.products_price, pd.products_name
			 FROM products p
			 INNER JOIN products_description pd ON (pd.products_id = p.products_id)
			 INNER JOIN products_to_categories ptc ON (p.products_id = ptc.products_id)
			 ' . SQL_FROM . '
			 WHERE '.$sWhere.' p.products_status = 1 AND p.products_featured = 1 and language_id = ' . (int)$languages_id . '
			 AND ptc.categories_id in (' . $sIdCategoriasUsar . ')
			 order by rand(' . random() . ') DESC
			 limit ' . MAX_DISPLAY_FEATURED_PRODUCTS;

	// Obtenemos los productos cambiando el precio
	$aAux = changePriceCustomer( $sSql, array( 'PAGINAR' => false ) );

	// Si no obtenemso productos obtenemos los productos ultimos añadido
	if( $aAux['TOTAL'] == 0 )
	{
		$sSql = 'SELECT ' . SQL_SELECT . ' p.products_id, p.products_ship_free, GROUP_CONCAT( CONCAT( ptc.categories_id, ", ") ORDER BY ptc.categories_id ASC SEPARATOR " ") grcats, p.products_image, p.products_tax_class_id, p.products_price, pd.products_name, p.products_quantity
				 FROM ' . TABLE_PRODUCTS . ' p
				 INNER JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ON (p.products_id = pd.products_id)
				 INNER JOIN products_to_categories ptc ON (p.products_id = ptc.products_id)
				 ' . SQL_FROM . '
				 WHERE '.$sWhere.' p.products_status = 1 AND pd.language_id = ' . (int)$languages_id . ' 
				 AND ptc.categories_id in (' . $sIdCategoriasUsar . ')
				 ORDER BY p.products_date_added desc
				 LIMIT ' . MAX_DISPLAY_FEATURED_PRODUCTS;

		// Obtenemos los productos cambiando el precio
		$aAux = changePriceCustomer( $sSql, array( 'PAGINAR' => false ) );
	}

	$aProductos = $aAux['PRODUCTOS'];
	include( DIR_THEME. 'html/components/' . basename(__FILE__) );
?>
