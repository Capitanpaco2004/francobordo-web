<?php
	use util\tools as tools;

	// Si nos mandan a instalar cambiamos el modulo por login para que forbidden no salte y podamos instalarlo
	if( array_key_exists( 'action', $_GET ) && $_GET['action'] == 'install' )
	{
		$_SERVER['PHP_SELF'] = 'login.php';
		$_SERVER['SCRIPT_FILENAME'] = 'login.php';
	}

	// Incluimos el application_top
	require( 'includes/application_top.php' );

	// Variables comunes
	$sUrlPage      = 'delivery_estimate.php';
	$sPathModule   = 'includes/modules/delivery_estimate';
	$sPathClasses  = $sPathModule . '/classes';
	$sPathActions  = $sPathModule . '/actions';
	$sPathTemplate = $sPathModule . '/templates';
	$sTitle        = 'Fecha estimada de entrega';
	$sSubtitle     = '';
	$aButtons      = [];
	$sPostAction   = array_key_exists( 'action', $_POST ) ? tep_db_input( $_POST['action'] ) : ( array_key_exists( 'action', $_GET ) ? tep_db_input( $_GET['action'] ) : false );
	$sHtmlModule   = '';

	# Messagestack estilo
	$messageStack->style = 'solenopsis';

	// Clase admin del modulo (install, email, saveManualUpdate, etc.)
	require_once( $sPathClasses . '/delivery_estimate_admin.php' );
	$module = new delivery_estimate_admin();

	// Instalacion automatica la primera vez (o si falta algo)
	if( ! $module->isInstalled() ) {
		$module->install();
		require( 'includes/configuration_cache.php' );
		tools::createCacheFile();
	}

	// Aseguramos fila de plantilla para cada idioma instalado (por si se ha añadido un idioma nuevo)
	$module->ensureEmailTemplates();

	// Router de acciones
	switch( $sPostAction )
	{
		case 'readme':
			include( $sPathActions . '/readme.php' );
		break;

		case 'update':
			include( $sPathActions . '/update.php' );
		break;

		case 'preview':
			// Devuelve sólo el HTML del email envuelto — sin base.php. La action hace exit.
			include( $sPathActions . '/preview.php' );
		break;

		case 'send_test':
			include( $sPathActions . '/send_test.php' );
		break;

		default:
			include( $sPathActions . '/default.php' );
		break;
	}

	// Pintamos con el template base (header + título + botones + messageStack + contenido + footer)
	echo includeTemplate( $sPathTemplate . '/base.php' );
?>
