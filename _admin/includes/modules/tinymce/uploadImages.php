<?php
// Subida de imagenes para el editor TinyMCE del admin.
//
// SEGURIDAD (corregido 2026-06-08):
//  - Requiere sesion de administrador valida: incluir application_top.php ejecuta
//    tep_admin_check_login(), que redirige a login si no hay sesion. Antes este
//    endpoint solo incluia define.php y permitia subir ficheros SIN autenticacion.
//  - Valida que el fichero es REALMENTE una imagen de un tipo permitido (getimagesize),
//    sin fiarse de la extension ni del Content-Type que envia el cliente.
//  - El nombre y la extension del fichero guardado los genera el servidor (nunca el
//    nombre del cliente), evitando .php/.phtml/.htaccess/.svg y dobles extensiones.

chdir(dirname(__FILE__) . '/../../../');                     // -> _admin/
$PHP_SELF = 'index.php';                                     // usa permisos de index.php (cualquier admin logueado)
require(dirname(__FILE__) . '/../../application_top.php');   // login + constantes + funciones

// A partir de aqui solo continua un administrador autenticado
// (si no lo esta, application_top ya ha hecho redirect + exit).

header('Content-Type: application/json');

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
	echo json_encode(['error' => 'No se subió ningún archivo o error en la subida']);
	exit;
}

// Limite de tamano (10 MB)
if ($_FILES['file']['size'] > 10 * 1024 * 1024) {
	echo json_encode(['error' => 'El archivo supera el tamaño máximo permitido (10 MB)']);
	exit;
}

// Tipos de imagen permitidos -> extension que asignara el servidor
$aAllowed = array(
	IMAGETYPE_JPEG => 'jpg',
	IMAGETYPE_PNG  => 'png',
	IMAGETYPE_GIF  => 'gif',
	IMAGETYPE_WEBP => 'webp',
);

// Validamos que es REALMENTE una imagen del tipo esperado (no nos fiamos del cliente)
$aInfo = @getimagesize($_FILES['file']['tmp_name']);
if ($aInfo === false || !isset($aInfo[2]) || !isset($aAllowed[$aInfo[2]])) {
	echo json_encode(['error' => 'El archivo no es una imagen válida (solo JPG, PNG, GIF o WebP)']);
	exit;
}
$sExt = $aAllowed[$aInfo[2]];

// Carpeta de destino (en filesystem y en URL)
$targetSubDir = 'images/upload/images/';
$targetDir = rtrim(DIR_FS_CATALOG, '/') . '/' . $targetSubDir;
$targetUrl = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? HTTPS_SERVER : HTTP_SERVER), '/') . '/' . $targetSubDir;

if (!is_dir($targetDir)) {
	mkdir($targetDir, 0755, true);
}

// Nombre generado en el servidor (NUNCA el del cliente) + extension segun el tipo real
$filename = 'img_' . uniqid() . bin2hex(random_bytes(4)) . '.' . $sExt;
$targetFilePath = $targetDir . $filename;

if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFilePath)) {
	@chmod($targetFilePath, 0644);
	echo json_encode(['location' => $targetUrl . $filename]);
} else {
	echo json_encode(['error' => 'Error al mover el archivo']);
}
