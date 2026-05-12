<?php
    include( 'includes/application_top.php' );
	
	// Si no es ajax mostramos todo. Esto se usa para la paginación mediante ajax
	if( ! isAjax() )
	{
		// Incluimos el archivo de lenguaje
		require( DIR_WS_LANGUAGES . $language . '/' . FILENAME_BEST_SELLERS );

		// Titulo
		$sTitular = HEADING_TITLE;
		
		// Breadcrumb
		$breadcrumb->add( NAVBAR_TITLE, tep_href_link( FILENAME_BEST_SELLERS ) );

		require(DIR_THEME. 'html/header.php');
		require(DIR_THEME. 'html/column_left.php');
	}

	// Consulta con los productos
	$sSql = 'select ' . SQL_SELECT . ' p.products_id, p.products_ship_free, p.products_price, p.products_tax_class_id, pd.products_name, p.products_quantity, p.products_image
			 from ' . TABLE_PRODUCTS . ' p 
			 inner join ' . TABLE_PRODUCTS_DESCRIPTION . ' pd on(p.products_id = pd.products_id)
			 ' . SQL_FROM . '
			 where p.products_status = 1 and p.products_ordered > 0 and pd.language_id = ' . (int)$languages_id . '
			 order by p.products_ordered desc, pd.products_name';

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