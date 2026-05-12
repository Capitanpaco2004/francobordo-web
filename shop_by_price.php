<?php
	include( 'includes/application_top.php' );
	
	// Si no es ajax mostramos todo. Esto se usa para la paginación mediante ajax
	if( ! isAjax() )
	{
		// Incluimos el archivo de lenguaje
		require( DIR_WS_LANGUAGES . $language . '/' . FILENAME_SHOP_BY_PRICE );

		// Titulo
		$sTitular = HEADING_TITLE;

		// Breadcrumb
		$breadcrumb->add( NAVBAR_TITLE, tep_href_link( FILENAME_SHOP_BY_PRICE ) );

		require(DIR_THEME. 'html/header.php');
		require(DIR_THEME. 'html/column_left.php');
	}
	
	// Initialize the varaible '$range' from param here because it's used in FILENAME_SHOP_BY_PRICE
	$range = 0;

	if( isset($_GET['range']) )
		$range = $_GET['range'];

	$pfrom = isset($price_min[$range]) ? $price_min[$range] : 0;
	$pto = isset($price_max[$range]) ? $price_max[$range] : 0;
	
	// Creamos la consulta SQL
	$sSql = 'select distinct p.products_id, ' . SQL_SELECT . ' p.products_model, m.manufacturers_name, p.products_quantity, p.products_image, p.products_weight, pd.products_name, p.products_price, p.products_tax_class_id, IF(s.status, s.specials_new_products_price, NULL) as specials_new_products_price, IF(s.status, s.specials_new_products_price, p.products_price) as final_price ';

	if( DISPLAY_PRICE_WITH_TAX == 'true' && ( tep_not_null( $pfrom ) || tep_not_null( $pto ) ) )
		$sSql .= ', SUM(tr.tax_rate) as tax_rate ';

	$sSql .= 'from ' . TABLE_PRODUCTS . ' p left join ' . TABLE_MANUFACTURERS . ' m using(manufacturers_id) left join ' . TABLE_SPECIALS . ' s on p.products_id = s.products_id';

	if( DISPLAY_PRICE_WITH_TAX == 'true' && ( tep_not_null( $pfrom ) || tep_not_null( $pto ) ) )
	{
		if( !tep_session_is_registered( 'customer_country_id') )
		{
			$customer_country_id = STORE_COUNTRY;
			$customer_zone_id = STORE_ZONE;
		}

		$sSql .= ' left join ' . TABLE_TAX_RATES . ' tr on p.products_tax_class_id = tr.tax_class_id left join ' . TABLE_ZONES_TO_GEO_ZONES . ' gz on tr.tax_zone_id = gz.geo_zone_id and (gz.zone_country_id is null or gz.zone_country_id = 0 or gz.zone_country_id = ' . (int)$customer_country_id . ') and (gz.zone_id is null or gz.zone_id = 0 or gz.zone_id = ' . (int)$customer_zone_id . ')';
	}

	$sSql .= ', ' . TABLE_PRODUCTS_DESCRIPTION . ' pd';
	
	 $sSql .= SQL_FROM;

	$sSql .= ' where p.products_status = 1 and p.products_id = pd.products_id and pd.language_id = ' . (int)$languages_id;

	if( DISPLAY_PRICE_WITH_TAX == 'true' )
	{
		if( $pfrom > 0 )
			$sSql .= " and (IF(s.status, s.specials_new_products_price, p.products_price) * if(gz.geo_zone_id is null, 1, 1 + (tr.tax_rate / 100) ) >= " . (double)$pfrom . ")";

		if( $pto > 0 )
			$sSql .= " and (IF(s.status, s.specials_new_products_price, p.products_price) * if(gz.geo_zone_id is null, 1, 1 + (tr.tax_rate / 100) ) <= " . (double)$pto . ")";
	}
	else 
	{
		if( $pfrom > 0 )
			$sSql .= " and (IF(s.status, s.specials_new_products_price, p.products_price) >= " . (double)$pfrom . ")";

		if( $pto > 0 )
			$sSql .= " and (IF(s.status, s.specials_new_products_price, p.products_price) <= " . (double)$pto . ")";
	}

	if( DISPLAY_PRICE_WITH_TAX == 'true' && (tep_not_null($pfrom) || tep_not_null($pto)) )
		$sSql .= " group by p.products_id, tr.tax_priority";

	$sSql .= " order by final_price, pd.products_name";

	// Cambiamos el SQL si existe un filtro
	changeFilter( $sSql );	
	
	// Obtenemos el paginador y los productos
	$aAux = changePriceCustomer( $sSql, array( 'COUNT_KEY' => 'p.products_id' ) );
	$aProductos = $aAux['PRODUCTOS'];
	$aPaginador = $aAux['PAGE_PRODUCTOS'];
	$nProductosTotal = count( $aProductos );
		
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