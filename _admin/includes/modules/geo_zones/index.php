<?php
// Tools
use util\tools;

// Incluimos el application_top
require_once( 'includes/application_top.php' );

// Mostrar errores
// ini_set('display_errors', 1); // OFF 2026-07-14: no pisar display_errors=Off del admin (avisos impresos rompen AJAX/redirects)
error_reporting(E_ERROR | E_WARNING | E_PARSE);

// Variables
$sUrlPage =  'geo_zones.php';
$sPathModule = 'includes/modules/geo_zones';
$sPathTemplate = $sPathModule . '/template';
$sTitle = GEO_ZONES_TITLE;
$sSubtitle = '';
$aButtons = array();
$sPostAction = array_key_exists( 'action', $_POST ) ? tep_db_input( $_POST['action'] ) : (array_key_exists( 'action', $_GET ) ? tep_db_input( $_GET['action'] ) : false);

# Messagestack estilo
$messageStack->style = 'solenopsis';

// Acciones
switch(true)
{
	case preg_match('/^zones_to_geo_zones/', $sPostAction):
		include( $sPathModule . '/zones_to_geo_zones.php' );
		break;

	default:
		include $sPathModule . '/default.php';
		break;
}

// Pintamos
echo includeTemplate( $sPathTemplate . '/base.php' );
