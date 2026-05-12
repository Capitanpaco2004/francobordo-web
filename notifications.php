<?php
require('includes/application_top.php');
require('includes/classes/notificaciones.php');

$notificaciones = new Notificaciones();
//$notificaciones->sandbox = true;
$notificaciones->prepare();

header('Content-Type: application/json; charset=utf-8');

$notificaciones->execute();

// paramos aquí: nada más se imprime
exit;
