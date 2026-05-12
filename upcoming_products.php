<?php
    include( 'includes/application_top.php' );

	// Si no es ajax mostramos todo. Esto se usa para la paginación mediante ajax
	if( ! isAjax() )
	{
		// Incluimos el archivo de lenguaje
		require( DIR_WS_LANGUAGES . $language . '/' . FILENAME_UPCOMING_PRODUCTS );

		// Titulo
		$sTitular = HEADING_TITLE;

		// Breadcrumb
		$breadcrumb->add( HEADING_TITLE, tep_href_link( FILENAME_UPCOMING_PRODUCTS ) );

		require(DIR_THEME. 'html/header.php');
		require(DIR_THEME. 'html/column_left.php');
	}

	// Consulta con los productos
    $sSql = 'select p.products_id, p.products_date_available as date_expected, p.products_ship_free, p.products_quantity, p.products_tax_class_id, p.products_image,p.products_price, pd.products_name, products_date_available as date_expected
			 from ' . TABLE_PRODUCTS . ' p
			 inner join ' . TABLE_PRODUCTS_DESCRIPTION . ' pd on (p.products_id = pd.products_id)
			 where to_days(products_date_available) >= to_days(now()) and pd.language_id = ' . (int)$languages_id . '
			 order by products_date_available asc';

	// Cambiamos el SQL si existe un filtro
	changeFilter( $sSql );

	// Obtenemos el paginador y los productos
	$aAux = changePriceCustomer( $sSql );
	$aProductos = $aAux['PRODUCTOS'];
	$aPaginador = $aAux['PAGE_PRODUCTOS'];
	$nProductosTotal = $aAux['TOTAL'];


	// Theme
	include( DIR_THEME_ROOT . 'html/templates/' . basename(__FILE__) );

	// Liberamos
	unset( $aAux, $aProductos, $aPaginador, $sSql );

	// Si no es ajax mostramos todo. Esto se usa para la paginación mediante ajax
	if( ! isAjax() )
	{
		include( DIR_THEME. 'html/column_right.php' );
		include( DIR_THEME. 'html/footer.php' );
		include( DIR_WS_INCLUDES . 'application_bottom.php' );
	}
?>