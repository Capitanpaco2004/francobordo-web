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

	// Consulta con los productos
	if( !isset( $current_category_id ) || $current_category_id == '0' )
	{
		$sSql = 'SELECT ' . SQL_SELECT . ' p.products_id, p.products_status, p.products_ship_free, p.products_image, p.products_tax_class_id, p.products_price, pd.products_name, pd.products_description, p.products_quantity, p.manufacturers_id, GROUP_CONCAT( CONCAT( ptc.categories_id, ", ") ORDER BY ptc.categories_id ASC SEPARATOR " ") grcats
				 FROM ' . TABLE_PRODUCTS . ' p
				 INNER JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ON (p.products_id = pd.products_id)
				 INNER JOIN products_to_categories ptc ON (p.products_id = ptc.products_id)
				 ' . SQL_FROM . '
				 WHERE ' . $sWhere . '
				 p.products_status = 1 AND
				 pd.language_id = ' . (int)$languages_id . ' AND p.products_id NOT IN (353517, 353516)
				 GROUP BY p.products_id
				 ORDER BY p.products_date_added desc
				 LIMIT 9';
	}
	else
	{
		// Categorias que descienden de la categoria actual
		$sIdCategoriasUsar = substr( getRecursiveIdCategories($_aAllCategorias, $current_category_id), 0, -1 );

		/**
		 * No mostramos si no hay categorias.
		 * @author Daniel Lucia <daniel.lucia@denox.es>
		 * #SVJ-174-22581
		 */
		if ($sIdCategoriasUsar == '') {
			return;
		}

		$sSql = 'SELECT ' . SQL_SELECT . ' p.products_id, p.products_status, p.products_ship_free, p.products_image, p.products_tax_class_id, p.products_price, pd.products_name, pd.products_description, p.products_quantity, p.manufacturers_id, GROUP_CONCAT( CONCAT( ptc.categories_id, ", ") ORDER BY ptc.categories_id ASC SEPARATOR " ") grcats
				 FROM products p
				 INNER JOIN products_description pd ON (p.products_id = pd.products_id)
				 INNER JOIN products_to_categories ptc ON (p.products_id = ptc.products_id)
				 ' . SQL_FROM . '
				 WHERE ' . $sWhere . '
				 ptc.categories_id in (' . $sIdCategoriasUsar . ') AND
				 p.products_status = 1 AND
				 pd.language_id = ' . (int)$languages_id . '
				 GROUP BY p.products_id
				 ORDER BY p.products_date_added DESC
				 LIMIT 9';
	}

	// Obtenemos los productos cambiando el precio
	$aAux = changePriceCustomer( $sSql, array( 'PAGINAR' => false ) );

	// Mostramos productos
	if( $aAux['TOTAL'] > 0 )
	{
		$aProductos = $aAux['PRODUCTOS'];
		include( DIR_THEME. 'html/components/' . basename(__FILE__) );
	}
?>
