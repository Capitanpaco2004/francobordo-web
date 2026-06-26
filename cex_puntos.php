<?php
/**
 * AJAX del checkout: puntos de conveniencia (Paq Punto) de Correos Express para un CP dado.
 * Lo usa el buscador "recoger en otro CP" del módulo de envío cexpunto.
 *
 * GET ?cp=NNNNN  →  JSON {ok:true, puntos:[{id,name,address,cp,city,lat,lng,hours}]}
 *
 * Mismo gate de pruebas que el módulo (MODULE_SHIPPING_CEXPUNTO_TEST_IP).
 * Cache 6h por CP en fichero (cexpunto::puntos). Gemelo de seur_puntos.php.
 * Ver memoria francobordo_correos_express_api.
 */
require 'includes/application_top.php';

/* JSON limpio: sin avisos PHP en la salida y descartando cualquier buffer previo. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
ob_start();

function cexPudoOut($arr) {
    $json = json_encode($arr, JSON_UNESCAPED_UNICODE);
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo $json;
    exit;
}

/* Gate FASE PRUEBAS: solo IPs permitidas (vacío = abierto). Default = IP de oficina
 * mientras el módulo aún no está instalado (la constante no existe todavía). */
$gate = defined('MODULE_SHIPPING_CEXPUNTO_TEST_IP') ? trim(MODULE_SHIPPING_CEXPUNTO_TEST_IP) : '217.127.199.171';
if ($gate !== '') {
    $aIPs = array_map('trim', explode(',', $gate));
    if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $aIPs, true)) {
        http_response_code(403);
        cexPudoOut(array('ok' => false, 'error' => 'forbidden'));
    }
}

$cp = preg_replace('/\D/', '', (string) ($_GET['cp'] ?? ''));
if (!preg_match('/^\d{5}$/', $cp) || preg_match('/^(35|38|51|52)/', $cp)) {
    cexPudoOut(array('ok' => false, 'error' => 'cp'));
}

require_once DIR_WS_MODULES . 'shipping/cexpunto.php';
$puntos = cexpunto::puntos($cp, '', 'ES');

cexPudoOut(array('ok' => !empty($puntos), 'cp' => $cp, 'puntos' => $puntos));
