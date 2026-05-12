<?php
	// Variables
	$aAux = pathinfo( $_SERVER['REQUEST_URI'] );
	$sUrl = 'http://www.' . $_SERVER['HTTP_HOST'] . '/';
	
	// Comprobamos que contenga directorio para concatenarlo
	if( $aAux['dirname'] != '' && $aAux['dirname'] != '.' )
		$sUrl .= $aAux['dirname'] . '/';
		
	// Incluimos el html
	include( DIR_THEME_ROOT . 'html/boxes/' . basename(__FILE__) );
?>