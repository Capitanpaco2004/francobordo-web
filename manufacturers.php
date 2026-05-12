<?php
    include( 'includes/application_top.php' );

	// Variables
	$aFabricante = null;
	$aProductos = null;
	$sTitular = TITLE;
	$nProductosTotal = 0;
	$sSql = null;
	$aPaginador = null;
	$aDatos = null;
	$manufacturers_id = (int)tep_db_prepare_input( $_GET['manufacturers_id'] );

	// Comprobamos que nos esten enviando un fabricante si no redireccionamos a todos
    if( ! isset( $manufacturers_id ) && ! tep_not_null( $manufacturers_id ) && $manufacturers_id == 0 )
		tep_redirect( FILENAME_ALLMANUFACTURERS );

	// Obtenemos los datos del fabricante
	$aDatos = tep_db_query( 'select manufacturers_name, manufacturers_image
							 from manufacturers
							 where manufacturers_id = "' . $manufacturers_id . '"' );
	$aFabricante = tep_db_fetch_array( $aDatos );

	// Si existe el fabricante
	if( !empty($aFabricante) )
	{
		// Añade fabricantes al breadcrumb
		$breadcrumb->add(BOX_HEADING_MANUFACTURERS, tep_href_link(FILENAME_ALLMANUFACTURERS))->add($aFabricante['manufacturers_name'], tep_href_link(FILENAME_MANUFACTURERS, 'manufacturers_id=' . $manufacturers_id));

		// Si la categoria tiene nombre la obtenemos
		if( $aFabricante['manufacturers_name'] != '' )
			$sTitular = $aFabricante['manufacturers_name'];

		$sSql = 'select ' . SQL_SELECT . 'p.products_model, p.products_status, pd.products_description, GROUP_CONCAT( CONCAT( p2c.categories_id, ", ") ORDER BY p2c.categories_id ASC SEPARATOR " ") grcats, pd.products_name, m.manufacturers_name, p.products_quantity, p.products_image, p.products_weight, p.products_id, p.manufacturers_id, p.products_price, p.products_tax_class_id, p.products_ship_free
				 from products p
				 inner join products_description pd on (p.products_id = pd.products_id)
				 inner join manufacturers m on (p.manufacturers_id = m.manufacturers_id)
				 left join products_to_categories p2c on (p.products_id = p2c.products_id)'
				 . SQL_FROM . '
				where p.products_status = 1 and m.manufacturers_id = "' . $manufacturers_id . '" and pd.language_id = ' . $languages_id .
				' group by p.products_id';

		// Cambiamos el SQL si existe un filtro
		changeFilter( $sSql );

		// Obtenemos el paginador y los productos
		$aAux = changePriceCustomer( $sSql );
		$aProductos = $aAux['PRODUCTOS'];
		$aPaginador = $aAux['PAGE_PRODUCTOS'];
		$nProductosTotal = $aAux['TOTAL'];
	}
	else
	{
		header('HTTP/1.1 410 Not Found');
		header('Status: 410 Not Found');
	}

	if( !isAjax() || ! isset( $_GET['type'] ) || $_GET['type'] != 'json' )
	{
		// Cargamos la cabecera y la columna izquierda
		include( DIR_THEME. 'html/header.php' );
		include( DIR_THEME. 'html/column_left.php' );
	}

	// Paginación por scroll //
	$sPrevUrl = '';
	$sNextUrl = '';
	if ($aPaginador)
	{
		if ($aPaginador->number_of_pages > 1)
		{
			$nPage = intval($_GET['page'] ?? 0) > 0 ? intval($_GET['page'] ?? 0) : 1;
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