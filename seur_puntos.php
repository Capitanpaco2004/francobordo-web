<?php
/**
 * AJAX del checkout: puntos de recogida SEUR para un código postal dado.
 * Lo usa el buscador "recoger en otro CP" del módulo de envío seurpunto.
 *
 * GET ?cp=NNNNN  →  JSON {ok:true, puntos:[{id,name,address,cp,city,lat,lng,hours}]}
 *
 * Mismo gate de pruebas que el módulo (MODULE_SHIPPING_SEURPUNTO_TEST_IP).
 * Cache 6h por CP en fichero (seurpunto::puntos). Ver memoria francobordo_seur_api.
 */
require 'includes/application_top.php';

/* JSON limpio: sin avisos PHP en la salida y descartando cualquier buffer previo. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
ob_start();

function pudoOut($arr) {
    $json = json_encode($arr, JSON_UNESCAPED_UNICODE);
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo $json;
    exit;
}

/* Gate de FASE PRUEBAS: mismas IPs que el módulo (campo vacío = abierto). */
if (defined('MODULE_SHIPPING_SEURPUNTO_TEST_IP') && trim(MODULE_SHIPPING_SEURPUNTO_TEST_IP) !== '') {
    $aIPs = array_map('trim', explode(',', MODULE_SHIPPING_SEURPUNTO_TEST_IP));
    if (!in_array($_SERVER['REMOTE_ADDR'], $aIPs, true)) {
        http_response_code(403);
        pudoOut(array('ok' => false, 'error' => 'forbidden'));
    }
}

$cp = preg_replace('/\D/', '', (string) ($_GET['cp'] ?? ''));
if (!preg_match('/^\d{5}$/', $cp) || preg_match('/^(35|38|51|52)/', $cp)) {
    pudoOut(array('ok' => false, 'error' => 'cp'));
}

require_once DIR_WS_MODULES . 'shipping/seurpunto.php';
$puntos = seurpunto::puntos($cp, '', 'ES');

pudoOut(array('ok' => true, 'cp' => $cp, 'puntos' => $puntos));
