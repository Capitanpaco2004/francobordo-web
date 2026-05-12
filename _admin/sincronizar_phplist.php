<?php
	include( 'includes/application_top.php' );

	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
	
	include( THEME . 'html/header.php' );

	echo '<h1 class="pageHeading" style="padding: 18px 0px">Sincronizar PHPlist</h1>';

	$crCurl = curl_init( 'https://www.francobordo.com/cron_sincronizar_phplist.php' );
	curl_setopt( $crCurl, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $crCurl, CURLOPT_ENCODING , '');
	$sHtml = curl_exec( $crCurl );
	curl_close( $crCurl );
	
	echo $sHtml;

	include( THEME . 'html/footer.php' );
		
	include(DIR_WS_INCLUDES . 'application_bottom.php' );
?>