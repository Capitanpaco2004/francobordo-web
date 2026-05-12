<?php
// Incluimos el application_top
require_once( 'includes/application_top.php' );

// Variables
$sUrlPage =  'ship2pay.php';
$sPathModule = 'includes/modules/ship2pay';
$sPathTemplate = $sPathModule . '/template';
$sTitle = SHIP_TO_PAY_HEADING_TITLE;
$sSubtitle = '';
$aButtons = [];
$sPostAction = array_key_exists( 'action', $_POST ) ? tep_db_input( $_POST['action'] ) : (array_key_exists( 'action', $_GET ) ? tep_db_input( $_GET['action'] ) : false);
$sGetPage = tep_db_prepare_input( $_GET['page'] ?? '' );
$sGetOrderby = tep_db_prepare_input( $_GET['orderby'] ?? '' );
$sGetSort = tep_db_prepare_input( $_GET['sort'] ?? '' );

# Messagestack estilo
$messageStack->style = 'solenopsis';

include( $sPathModule . '/list.php' );

// Pintamos
echo includeTemplate( $sPathTemplate . '/base.php' );
