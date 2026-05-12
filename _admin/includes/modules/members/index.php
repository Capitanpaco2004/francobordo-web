<?php
	// Tools
	use util\tools as tools;
	use util\date as date;

	// Incluimos el application_top
	require( 'includes/application_top.php' );
	include 'includes/modules/members/includes/functions/functions.php';

	// Variables
	$sUrlPage =  'admin_members.php';
	$sPathModule = 'includes/modules/members';
	$sPathTemplate = $sPathModule . '/template';
	$sTitle = ADMIN_MEMBERS_HEADING_TITLE_INDEX;
	$sSubtitle = '';
	$aButtons = [];
	$sPostAction = array_key_exists( 'action', $_POST ) ? tep_db_input( $_POST['action'] ) : (array_key_exists( 'action', $_GET ) ? tep_db_input( $_GET['action'] ) : false);
	$sGetPage = tep_db_prepare_input( $_GET['page'] ?? '' );
	$sGetOrderby = tep_db_prepare_input( $_GET['orderby'] ?? '' );
	$sGetSort = tep_db_prepare_input( $_GET['sort'] ?? '' );

	# Messagestack estilo
	$messageStack->style = 'solenopsis';

	// Acciones
	switch(true) {
		case preg_match('/^groups/', $sPostAction):
			include( $sPathModule . '/groups.php' );
			break;
		case preg_match('/^submodules/', $sPostAction):
			if(defined("SUBMODULES_FILES_SECURITY") && SUBMODULES_FILES_SECURITY != "true"){
				$messageStack->addSession("error","Los submodulos no estan activos, por favor activelos");
				tep_redirect(tep_href_link($sUrlPage,"action=groups"));
			}
			$classes = glob($sPathModule.'/class' . '/*.php');

			foreach ($classes as $file) {
				include_once $file;
			}
			include( $sPathModule . '/submodules.php' );
			break;

		default:
			include( $sPathModule . '/members.php' );
			break;
	}

	// Pintamos
	echo includeTemplate( $sPathTemplate . '/base.php' );
?>
