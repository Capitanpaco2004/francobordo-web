<?php

	$sTitle = HEADING_TITLE_PAYMENTS;
	$sModuleDirectory = DIR_FS_CATALOG_MODULES . 'payment/';
	$sModuleType = 'payment';
	$sTableHeading = HEADING_TABLE_PAYMENTS;

	// Acciones
	switch( $sPostAction ) {
		case 'edit':
			$sModule = array_key_exists( 'module', $_POST ) ? tep_db_input( $_POST['module'] ) : (array_key_exists( 'module', $_GET ) ? tep_db_input( $_GET['module'] ) : false);

			$aButtons = [
				[ 'title' => TEXT_BACK, 'href' => tep_href_link( $sUrlPage, tep_get_all_get_params(['action', 'module']) ), 'icon' => 'fa-arrow-left' ],
				[ 'title' => TEXT_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' ]
			];

			// Obtener la clase del módulo
			$cModule = getModuleById($sModuleDirectory, $sModuleType, $sModule);
			$aModuleConfigurations = getModuleConfigurations($cModule);

			$aStyle = [ $sPathModule . '/css/admin_modules.css' ];

			// Modulo
			$sHtmlModule = includeTemplate( $sPathTemplate . '/edit.php' );
			break;

		default:
			//Cargar array con módulos instalados
			$aModules = getInstalledModules($sModuleDirectory, $sModuleType);

			// Modulo
			$sHtmlModule = includeTemplate( $sPathTemplate . '/index.php' );
			break;
	}

?>
