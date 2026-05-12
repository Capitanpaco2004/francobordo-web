<?php
	// Variables
	$sEmail = 'info@prueba.com';

	// Si tenemos iniciada sesion
	if( tep_session_is_registered('customer_id') )
		$sEmail = $sCustomersEmailAddress;

	// Incluimos el html
	include( DIR_THEME_ROOT . 'html/boxes/' . basename(__FILE__) );

	// Liberamos
	unset( $sEmail );
?>