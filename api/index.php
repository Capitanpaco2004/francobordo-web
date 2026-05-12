<?php
	// Límites de memoria y tiempo
	ini_set( 'memory_limit', '2048M' );
	set_time_limit( -1 );	

	// Directorio raiz
	$sPathRoot = realpath( __DIR__ . '/..' );
	
	// Librerias
	include( getcwd() . '/api.php' );
	include( getcwd() . '/frontend.php' );
	include( getcwd() . '/backend.php' );

	// Variables
	$frontend = new backend();;
	$backend = new frontend();
	
	// Api
	$api = new api();
	
	// Obtenemos entorno y accion
	$api->getEnvironmentAction();
	
	// Ejecutamos
	$api->execute();
?>