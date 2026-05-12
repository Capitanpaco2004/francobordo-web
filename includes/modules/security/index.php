<?php
	// Cambiamos de directorio principal
	chdir( getcwd() . '/../../../' );
	$_SERVER['SCRIPT_NAME'] = str_replace( 'includes/modules/security/index.php', 'security.php', $_SERVER['SCRIPT_NAME'] );

	// Incluimos el application_top
	require( 'includes/application_top.php' );

	// Mostrar errores
	// ini_set('display_errors', 1);
	// error_reporting(1);
	// error_reporting(E_ERROR | E_WARNING | E_PARSE);
	// error_reporting(E_ALL);
	
	// Variables
	$sUrlPage =  'security.php';
	$sPostAction = array_key_exists( 'action', $_POST ) ? tep_db_input( $_POST['action'] ) : (array_key_exists( 'action', $_GET ) ? tep_db_input( $_GET['action'] ) : false);
	
	// Acciones
	switch( $sPostAction )
	{
		case 'lockouts_blacklist':
			include( 'includes/modules/security/lockouts_blacklist.php' );
			exit();
		break;
		
		default:
			tep_redirect( 'index.php' );
		break;
	}
?>