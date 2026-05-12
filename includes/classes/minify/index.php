<?php
// Librerias
use util\minify\Minify;

// Directorio raiz
chdir('../../..');

// Librerias
include 'includes/vendor/autoload.php';
include_once 'includes/define.php';
include 'includes/configure.php';
include 'includes/filenames.php';
include'includes/configuration_cache_read.php';

// Directorio actual
chdir(dirname(__FILE__));

// Minify
$minify = Minify::getInstance();

// Minify APP
$minifiApp = new \Minify\App(__DIR__);

// Obtenemos la salida
$request = $minifiApp->minify->serve($minifiApp->controller, $minifiApp->serveOptions);

// Comprobamos si tenemos activo static
if ($minify->hasStaticFile()) {
	$request = $minify->staticFile($request, $_GET['g']);
}

// Cabeceras
foreach ($request['headers'] as $name => $val) {
	header($name . ': ' . $val);
}

// Contenido
echo $request['content'];
