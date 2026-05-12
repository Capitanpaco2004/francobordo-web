<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// Incluye tu archivo de configuración (donde defines las constantes)
require_once dirname(__FILE__) . '/../../../../includes/define.php';

// Carpeta de destino (en el filesystem y en URL)
$targetSubDir = 'images/upload/images/';
$targetDir = rtrim(DIR_FS_CATALOG, '/') . '/' . $targetSubDir;
$targetUrl = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? HTTPS_SERVER : HTTP_SERVER), '/') . '/' . $targetSubDir;

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
	echo json_encode(['error' => 'No se subió ningún archivo o error en la subida']);
	exit;
}

$filename = uniqid() . '_' . basename($_FILES['file']['name']);
$targetFilePath = $targetDir . $filename;

// Asegura que la carpeta existe
if (!is_dir($targetDir)) {
	mkdir($targetDir, 0777, true);
}

if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFilePath)) {
	echo json_encode(['location' => $targetUrl . $filename]);
} else {
	echo json_encode(['error' => 'Error al mover el archivo']);
}
