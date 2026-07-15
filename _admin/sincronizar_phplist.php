<?php
	include( 'includes/application_top.php' );

	// ini_set('display_errors', 1); // OFF 2026-07-14: no pisar display_errors=Off del admin (avisos impresos rompen AJAX/redirects)
	// ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
	
	include( THEME . 'html/header.php' );

	echo '<h1 class="pageHeading" style="padding: 18px 0px">Sincronizar PHPlist</h1>';

	$crCurl = curl_init( 'https://www.francobordo.com/cron_sincronizar_phplist.php' );
	curl_setopt( $crCurl, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $crCurl, CURLOPT_ENCODING , '');
	$sHtml = curl_exec( $crCurl );
	// sin curl_close(): deprecado en PHP 8.5 (no-op desde 8.0)
	
	echo $sHtml;

	include( THEME . 'html/footer.php' );
		
	include(DIR_WS_INCLUDES . 'application_bottom.php' );
?>