<?php
    include( 'includes/application_top.php' );
	
	// Variables
	$sWhere = '';

	// Si estamos en una categoria
	if( $current_category_id != '' )
	{
		$sCatIn = getIdCategoriasHijasRecursivoByIdCategoriaPadre($current_category_id);
		// La función incluye solo descendientes; añadir la propia categoría y evitar IN() vacío
		$sCatIn = trim( $sCatIn ) !== '' ? $sCatIn . ', ' . (int)$current_category_id : (int)$current_category_id;
		$sWhere = ' and ptc.categories_id IN(' . $sCatIn . ')';
	}

	// Consulta con los productos
    $sSql = 'select ' . SQL_SELECT . ' p.products_quantity, p.products_status, pd.products_description, GROUP_CONCAT( CONCAT( ptc.categories_id, ", ") ORDER BY ptc.categories_id ASC SEPARATOR " ") grcats, p.products_ship_free, p.products_weight, p.products_id, pd.products_name, p.products_image, p.products_price, p.products_tax_class_id, IF(s.status, s.specials_new_products_price, NULL) as specials_new_products_price, IF(s.status, s.specials_new_products_price, p.products_price) as final_price
			 from ' . TABLE_PRODUCTS . ' p
			 inner join ' . TABLE_PRODUCTS_DESCRIPTION . ' pd on (p.products_id = pd.products_id)
			 INNER JOIN products_to_categories ptc on (p.products_id = ptc.products_id)
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
    if( ! isAjax() || ! isset( $_GET['type'] ) || $_GET['type'] != 'json' )
    {
        // Incluimos el archivo de lenguaje
        require( DIR_WS_LANGUAGES . $language . '/' . FILENAME_PRODUCTS_NEW );

        // Titulo
        $sTitular = HEADING_TITLE;

        // Breadcrumb
        $breadcrumb->add( NAVBAR_TITLE, tep_href_link( FILENAME_PRODUCTS_NEW ) );

        require(DIR_THEME. 'html/header.php');
        require(DIR_THEME. 'html/column_left.php');
    }
    
	// Paginación por scroll //
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
	// Paginación por scroll //
	
	// Theme
	include( DIR_THEME_ROOT . 'html/templates/' . basename(__FILE__) );

	// Liberamos
	unset( $aAux, $aProductos, $aPaginador, $sSql );
	
	// Si no es ajax mostramos todo. Esto se usa para la paginación mediante ajax
	if( ! isAjax() || ! isset( $_GET['type'] ) || $_GET['type'] != 'json' )
	{
		include( DIR_THEME. 'html/column_right.php' );
		include( DIR_THEME. 'html/footer.php' );
		include( DIR_WS_INCLUDES . 'application_bottom.php' );
	}
?>