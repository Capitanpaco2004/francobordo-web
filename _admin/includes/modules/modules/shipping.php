<?php

	$sTitle = HEADING_TITLE_SHIPPING;
	$sModuleDirectory = DIR_FS_CATALOG_MODULES . 'shipping/';
	$sModuleType = 'shipping';
	$sTableHeading = HEADING_TABLE_SHIPPING;
    // Acciones
    $aModules = getInstalledModules($sModuleDirectory, $sModuleType);
    // Modulo
    $sHtmlModule = includeTemplate( $sPathTemplate . '/index.php' );

?>
