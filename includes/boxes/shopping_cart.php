<?php 
	// Variables
	$aDatos = $cart->get_products();

	// Incluimos el html
	include( DIR_THEME_ROOT . 'html/boxes/' . basename(__FILE__) );
?>