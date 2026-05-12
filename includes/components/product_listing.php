<?php
	// Cambiamos el SQL si existe un filtro
	changeFilter( $listing_sql );	

	// Obtenemos el paginador y los productos
	$aAux = changePriceCustomer( $listing_sql, array( 'COUNT_KEY' => 'p.products_id' ) );
	$aProductos = $aAux['PRODUCTOS'];
	$aPaginador = $aAux['PAGE_PRODUCTOS'];
	$nProductosTotal = count( $aProductos );
	
	// Pintamos los productos
	include( DIR_THEME_ROOT . 'html/partial/_product_listing.php' );
?>