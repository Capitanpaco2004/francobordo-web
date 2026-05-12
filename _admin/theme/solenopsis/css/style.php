<?php
	// Obtenemos el css del admin y le quitamos css que den conflicto
	$sCss = preg_replace("/[\r\n\t]+/", "", file_get_contents( getcwd() . '/../../web/css/style.css' ) );
	$sCss = str_replace( '../images/', '../../web/images/', $sCss );
	
	// Css a eliminar
	$aDelete = [ 'A.button', 'A, A:visited, A:active, A:link', 'A:hover' ];

	// Recorremos
	foreach( $aDelete as $sDelete )
	{
		// Buscamos coincidencia
		$nInit = stripos( $sCss, $sDelete );
		
		// Si encontramos algo
		if( $nInit !== false )
		{
			$nEnd = stripos( substr( $sCss, $nInit, $nInit ), '}' ) + 1;
			$sReplace = substr( $sCss, $nInit, $nEnd );
				
			// Remplazamos
			$sCss = str_replace( $sReplace, '', $sCss );
		}
	}
	
	// Header CSS
	header("Content-Type: text/css; X-Content-Type-Options: nosniff;");

	// Pintamos
	echo $sCss;
?>