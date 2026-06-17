<?php
/**
 * AJAX del checkout: oficinas de Correos para un código postal dado (entrega en oficina).
 * Lo usa el selector del módulo de envío "Correos – recoger en oficina" (correosoficina).
 *
 * Fuente: localizador PÚBLICO de Correos (sin auth, sin headers — mismo modelo que el
 * canónico de tracking). La lógica vive en correosoficina::oficinas() (suggestions →
 * offices, retry×4, filtro, caché 7d) para que el render inicial del checkout y este
 * AJAX devuelvan EXACTAMENTE la misma forma.
 *
 * GET ?cp=NNNNN[&q=ciudad]  →  JSON {ok:true, cp:"NNNNN", oficinas:[{id,name,address,cp,city,lat,lng,hours}]}
 *
 * ⚠️ FASE PRUEBAS: bloqueado por IP hasta producción
 * (config MODULE_SHIPPING_CORREOSOFICINA_TEST_IP; vacío = abierto a todos).
 * Gemelo de seur_puntos.php. Ver memoria francobordo_correos_api.
 */
require 'includes/application_top.php';

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
ob_start();

function ofiOut($arr) {
    $json = json_encode($arr, JSON_UNESCAPED_UNICODE);
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo $json;
    exit;
}

/* Gate FASE PRUEBAS: solo IPs permitidas (vacío = abierto). Default = IP de oficina
 * mientras el módulo aún no está instalado (la constante no existe todavía). */
$gate = defined('MODULE_SHIPPING_CORREOSOFICINA_TEST_IP') ? trim(MODULE_SHIPPING_CORREOSOFICINA_TEST_IP) : '217.127.199.171';
if ($gate !== '') {
    $aIPs = array_map('trim', explode(',', $gate));
    if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $aIPs, true)) {
        http_response_code(403);
        ofiOut(array('ok' => false, 'error' => 'forbidden'));
    }
}

$cp = preg_replace('/\D/', '', (string) ($_GET['cp'] ?? ''));
if (!preg_match('/^\d{5}$/', $cp)) ofiOut(array('ok' => false, 'error' => 'cp'));

/* Texto opcional (ciudad/población) para geocodificar con CONTEXTO: el localizador a
 * veces casa un CP suelto con el municipio equivocado (p.ej. "03001"→l'Atzúbia en vez
 * de Alicante). El selector del checkout pasa la ciudad del pedido para desambiguar. */
$q = trim((string) ($_GET['q'] ?? ''));

require_once DIR_FS_CATALOG . 'includes/modules/shipping/correosoficina.php';
$oficinas = correosoficina::oficinas($cp, $q, 15);

$resp = array('ok' => !empty($oficinas), 'cp' => $cp, 'oficinas' => $oficinas);
if (!$oficinas) $resp['error'] = 'sin_oficinas';
ofiOut($resp);
