<?php
	// Variables
	$aDatos = $cart->get_products();
	$nTotal = $currencies->format( $cart->show_total() );
	$nCantidad = $cart->count_contents();

	if( !tep_session_is_registered('customer_id') && ENABLE_PAGE_CACHE == 'true' && class_exists('page_cache') )
		echo "<%CART_CACHE%>";
	else
		include( DIR_THEME. 'html/components/' . basename(__FILE__) );
?>