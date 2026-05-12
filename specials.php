<?php
    include( 'includes/application_top.php' );
	
	// Variables
	$customer_group_id = getCustomerGroupId();
	$sWhere = '';

	// Si estamos en una categoria
	if( $current_category_id != '' )
	{
		$sCatIn = getIdCategoriasHijasRecursivoByIdCategoriaPadre($current_category_id);
		if ($sCatIn !== '') {
			$sWhere = ' and ptc.categories_id IN(' . $sCatIn . ')';
		}
	}

	// Consulta con los productos
	$sSql = 'select ' . SQL_SELECT . ' p.products_quantity, pd.products_description, GROUP_CONCAT( CONCAT( ptc.categories_id, ", ") ORDER BY ptc.categories_id ASC SEPARATOR " ") grcats, p.products_ship_free, p.products_weight, p.products_id, pd.products_name, p.products_tax_class_id, p.products_image, IF(s.specials_new_products_price IS NOT NULL AND (s.venta_flash = 0 OR s.venta_flash = 1 AND NOW() >= s.start_date), s.specials_new_products_price, p.products_price) as products_price, IF(s.specials_new_products_price IS NOT NULL AND (s.venta_flash = 0 OR s.venta_flash = 1 AND NOW() >= s.start_date), p.products_price, NULL) as products_price_anterior, IF(s.status, s.specials_new_products_price, NULL) as specials_new_products_price, IF(s.status, s.specials_new_products_price, p.products_price) as final_price
			 from products p
			 inner join products_description pd on (p.products_id = pd.products_id)
			 inner join specials s on (s.products_id = p.products_id)
			 INNER JOIN products_to_categories ptc on (p.products_id = ptc.products_id)' . SQL_FROM . '
			 where p.products_status = 1 and pd.language_id = ' . (int)$languages_id . ' and s.status = 1 
			 and s.customers_group_id = ' . (int)$customer_group_id . $sWhere . '
			 AND s.start_date = (SELECT MAX(s2.start_date) FROM specials s2 WHERE s2.products_id = s.products_id AND s2.status = 1 AND DATE( NOW() ) >= DATE( s2.start_date ))
			 group by p.products_id
			 order by s.specials_date_added DESC';

	// Cambiamos el SQL si existe un filtro
	changeFilter( $sSql );	

	// Obtenemos el paginador y los productos
	$aAux = changePriceCustomer( $sSql );
	$aProductos = $aAux['PRODUCTOS'];
	$aPaginador = $aAux['PAGE_PRODUCTOS'];
	$nProductosTotal = $aAux['TOTAL'];

	// Si no es ajax mostramos todo. Esto se usa para la paginaci�n mediante ajax
	if( ! isAjax() || ! isset( $_GET['type'] ) || $_GET['type'] != 'json'  )
	{
		// Incluimos el archivo de lenguaje
		require( DIR_WS_LANGUAGES . $language . '/' . FILENAME_SPECIALS );

		// Titulo
		$sTitular = HEADING_TITLE;
		
		// Breadcrumb
		$breadcrumb->add( NAVBAR_TITLE, tep_href_link( FILENAME_SPECIALS ) );

		require(DIR_THEME. 'html/header.php');
		require(DIR_THEME. 'html/column_left.php');
	}

	// Paginaci�n por scroll //
	$sPrevUrl = '';
	$sNextUrl = '';
	if ($aPaginador)
	{
		if ($aPaginador->number_of_pages > 1)
		{
			$nPage = intval($_GET['page']) > 0 ? intval($_GET['page']) : 1;
			$sUrlNext = tep_href_link(basename($_SERVER['SCRIPT_FILENAME']), http_build_query(array_merge($_GET, array("page" => $nPage + 1))));
			$sUrlPrevious = tep_href_link(basename($_SERVER['SCRIPT_FILENAME']), http_build_query(array_merge($_GET, array("page" => $nPage - 1))));
			
			if ( $nPage > 1 && $nPage <= $aPaginador->number_of_pages )
				$sPrevUrl = html_entity_decode( $sUrlPrevious );
			
			if ( $nPage < $aPaginador->number_of_pages )
				$sNextUrl = html_entity_decode( $sUrlNext );
		}
	}
	// Paginaci�n por scroll //
	
	// Theme
	include( DIR_THEME_ROOT . 'html/templates/' . basename(__FILE__) );

	// Liberamos
	unset( $aAux, $aProductos, $aPaginador, $sSql );
	
	// Si no es ajax mostramos todo. Esto se usa para la paginaci�n mediante ajax
	if( ! isAjax() || ! isset( $_GET['type'] ) || $_GET['type'] != 'json' )
	{
		include( DIR_THEME. 'html/column_right.php' );
		include( DIR_THEME. 'html/footer.php' );
		include( DIR_WS_INCLUDES . 'application_bottom.php' );
	}
?>