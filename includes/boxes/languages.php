<?php
	if( !isset($lng) || (isset($lng) && !is_object($lng) ) )
	{
		include_once(DIR_WS_CLASSES . 'language.php');
		$lng = new language;
	}

	reset($lng->catalog_languages);
	$aDatos = $lng->catalog_languages;
	
	// Incluimos el html
	if( count( $aDatos ) > 0 )
		include( DIR_THEME_ROOT . 'html/boxes/' . basename(__FILE__) );
?>