<?php
    include( 'includes/application_top.php' );

	// Variables
	$sWhere = '';
	$nCategory = (int)tep_db_prepare_input( $_GET['c'] );

	// Si estamos en una categoria
	if( $nCategory > 0 )
	{
		$sIdCategoriasSrch = getRecursiveIdCategories( $_aAllCategorias, $nCategory ) . $nCategory;
		$sWhere = ' and ptc.categories_id IN(' . $sIdCategoriasSrch . ')';
	}

	// Consulta con los productos
    $sSql = 'select ' . SQL_SELECT . ' p.products_quantity, pd.products_description, GROUP_CONCAT( CONCAT( ptc.categories_id, ", ") ORDER BY ptc.categories_id ASC SEPARATOR " ") grcats, p.products_ship_free, p.products_weight, p.products_id, pd.products_name, p.products_image, p.products_price, p.products_tax_class_id, IF(s.status, s.specials_new_products_price, NULL) as specials_new_products_price, IF(s.status, s.specials_new_products_price, p.products_price) as final_price
			 from ' . TABLE_PRODUCTS . ' p
			 inner join ' . TABLE_PRODUCTS_DESCRIPTION . ' pd on (p.products_id = pd.products_id)
			 INNER JOIN products_to_categories ptc on (p.products_id = ptc.products_id)
			 INNER JOIN featured f on (p.products_id = f.products_id AND f.status = 1)
			 ' . $sJoin . SQL_FROM . '
			 where p.products_status = 1 and pd.language_id = ' . (int)$languages_id . $sWhere . '
			 group by p.products_id
			 order by p.products_date_added DESC, pd.products_name ';

	// Cambiamos el SQL si existe un filtro
	changeFilter( $sSql );	
	
	// Obtenemos el paginador y los productos
	$aAux = changePriceCustomer( $sSql );
	$aProductos = $aAux['PRODUCTOS'];
	$aPaginador = $aAux['PAGE_PRODUCTOS'];
	$nProductosTotal = $aAux['TOTAL'];

	// Si no es ajax mostramos todo. Esto se usa para la paginación mediante ajax
	if( ! isAjax() )
	{
		// Incluimos el archivo de lenguaje
		require( DIR_WS_LANGUAGES . $language . '/' . FILENAME_FEATURED );
		
		// Breadcrumb
		$breadcrumb->add( NAVBAR_TITLE . ($nCategory > 0 ? ' - ' . $_aAllDatos[$nCategory]['categories_name'] : ''), tep_href_link( 'products_featured.php', 'c=' . $nCategory ) );

		require(DIR_THEME. 'html/header.php');
		require(DIR_THEME. 'html/column_left.php');

		// Theme
		include( DIR_THEME_ROOT . 'html/partial/_product_listing.php' );

		include( DIR_THEME. 'html/column_right.php' );
		include( DIR_THEME. 'html/footer.php' );
		include( DIR_WS_INCLUDES . 'application_bottom.php' );
	}
	else
	{
		echo '<div class="contentScroll ax rows" data-url="' . tep_href_link( basename($PHP_SELF), '' . tep_get_all_get_params( array('type', 'info', 'x', 'y' ) ) ) . '">';
			while( $aProducto = eachProducts() )
				echo _product();
		echo '</div>';
	}

	// Liberamos
	unset( $aAux, $aProductos, $aPaginador, $sSql );
?>