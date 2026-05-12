<?php
	use util\tools as tools;

	// Action: readme — muestra el readme.txt del módulo renderizado con parsedown.
	//
	// Variables disponibles (inyectadas por index.php):
	//   $sUrlPage

	$sSubtitle = 'Readme de instalación';
	$aButtons = [
		[ 'title' => 'Ver módulo', 'icon' => 'fa-arrow-right', 'href' => $sUrlPage ]
	];

	$sHtmlModule = tools::parsedown( DIR_WS_MODULES . '/delivery_estimate/readme.txt' );
?>
