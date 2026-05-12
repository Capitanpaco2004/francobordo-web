<?php
	// Cambiamos el directorio
	chdir( getcwd() . '/../' );
	
	// Incluimos el application_top
	require( 'includes/application_top.php' );

	// Comprobamos la session si la cuenta no esta eliminada
	if( !array_key_exists( 'delete_confirm_account', $_GET ) )
	{
		if (!$customerCore->hasLogin()) {
			$navigation->set_snapshot();
			tep_redirect( tep_href_link( FILENAME_LOGIN, '', 'SSL' ) );
		}
	}

	// Incluimos el archivo de idioma
	require( DIR_WS_LANGUAGES . $language . '/account.php' );
	require( DIR_WS_LANGUAGES . $language . '/' . basename( $_SERVER["SCRIPT_FILENAME"] ) );
	
	// Breadcrumb
	$breadcrumb->add( NAVBAR_TITLE_1, tep_href_link( FILENAME_ACCOUNT, '', 'SSL' ) );
?>