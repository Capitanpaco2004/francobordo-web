<?php
/**
 * Descarga del manifiesto de entrega SEUR (PDF del día) — para el PC del almacén.
 *
 * GET /seur_manifiesto.php?token=...&date=YYYY-MM-DD
 *   token  (obligatorio)
 *   date   opcional, def. hoy (Europe/Madrid)
 *
 * Respuesta: PDF (attachment) o JSON {ok:false,error} si no hay manifiesto/fallo.
 * Entorno SEUR según seur_config (pre/pro), como el resto de piezas.
 * Ver memoria francobordo_seur_api.
 */
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');
date_default_timezone_set('Europe/Madrid');

define('SEUR_MAN_TOKEN', 'seurman_4b8e2d17c9');

$in = array_merge($_GET, $_POST);
if (($in['token'] ?? '') !== SEUR_MAN_TOKEN) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(array('ok' => false, 'error' => 'forbidden'));
    exit;
}

chdir(__DIR__);
include 'includes/configure.php';
require_once 'includes/classes/seur.php';

$date = trim((string) ($in['date'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

/* Entorno desde seur_config */
$env = 'pre';
$db = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if (!$db->connect_errno) {
    if ($r = $db->query("SELECT config_value FROM seur_config WHERE config_key='env'")) {
        if ($row = $r->fetch_assoc()) $env = ($row['config_value'] === 'pro') ? 'pro' : 'pre';
    }
}

$s = new seur($env);
$s->setTimeout(60);
$r = $s->deliveryManifest($date, $date);

if (!empty($r['pdf_bin'])) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Manifiesto_SEUR_' . $date . ($env === 'pre' ? '_PRUEBAS' : '') . '.pdf"');
    header('Content-Length: ' . strlen($r['pdf_bin']));
    header('Cache-Control: private, no-store');
    echo $r['pdf_bin'];
    exit;
}

header('Content-Type: application/json');
if ((int) $r['http'] === 204) {
    echo json_encode(array('ok' => false, 'error' => 'Sin envios SEUR registrados el ' . $date . ' (entorno ' . $env . ').'), JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(array('ok' => false, 'error' => 'No se pudo generar el manifiesto (HTTP ' . $r['http'] . '): ' . seur::primerError($r)), JSON_UNESCAPED_UNICODE);
}
