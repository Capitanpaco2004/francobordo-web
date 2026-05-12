<?php
	// Paramos la salida de php
	ob_start();

	// Incluimos archivo del template
	include( THEME . 'html/header.php' );

	// Contenido obtenido
	$sHtml = ob_get_contents();

	// Continuamos con la salida por donde ibamos
	ob_end_clean();

	// Obtenemos CSS
	$sCss = '';

	if( isset( $aStyle ) )
	{
		foreach( $aStyle as $sStyle )
			$sCss .= '<link rel="stylesheet" type="text/css" href="' . $sStyle . '"/>';
	}

	// Remplazamos
	$sHtml = str_replace(
	[
		'<link rel="stylesheet" type="text/css" href="theme/solenopsis/css/grid.css"/>',
		'<head>',
		'</head>',
		'<link rel="stylesheet" type="text/css" href="theme/web/css/font.css"/>',
        '<link rel="stylesheet" type="text/css" href="theme/web/css/plugins.css"/>',
		'<link rel="stylesheet" type="text/css" href="theme/web/css/style.css"/>'
	],
	[
		'',
		'<head><base href="' . ((ENABLE_SSL == 'true' || ENABLE_SSL === true) ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_ADMIN . '" /><meta http-equiv="X-UA-Compatible" content="IE=edge"><meta name="viewport" content="width=device-width, initial-scale=1">',
		'<link rel="stylesheet" type="text/css" href="theme/solenopsis/css/grid.css"/><link rel="stylesheet" type="text/css" href="theme/solenopsis/css/style.css"/></head>',
		'',
		'',
		'<link rel="stylesheet" type="text/css" href="theme/solenopsis/css/style_base.css"/>' . $sCss
	], $sHtml );

	// Pintamos
	echo $sHtml . '<table id="solenopsis" class="preload" style="width: 100%;"><tbody style="background: transparent;"><tr><td>';
?>
