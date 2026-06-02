<?php
/**
 * ext/modules/payment/paypal_rest/js-log.php
 *
 * Beacon de logging client-side → paypal_rest.log. Permite depurar el flujo de
 * Apple Pay / Google Pay en dispositivos donde no tenemos acceso a la consola
 * (iPhone Safari, etc.). El JS llama a este endpoint con {step, detail} en cada
 * paso y aqui lo volcamos al log con prefijo [js].
 *
 * Sin efectos secundarios — solo escribe linea de log y devuelve 204.
 */

$LOG = '/home/francobordo/public_html/paypal_rest.log';

$sRaw = file_get_contents('php://input');
$aIn  = json_decode((string)$sRaw, true);
if (!is_array($aIn)) $aIn = $_POST;

$sStep   = isset($aIn['step'])   ? substr(preg_replace('/[^\w .:\/\-]/', '', (string)$aIn['step']),   0, 60)  : '?';
$sDetail = isset($aIn['detail']) ? substr(preg_replace('/[\r\n]/',      ' ', (string)$aIn['detail']), 0, 500) : '';
$sUA     = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 80);

@file_put_contents(
    $LOG,
    '[' . date('Y-m-d H:i:s') . '] [js] ' . $sStep . ($sDetail !== '' ? ' :: ' . $sDetail : '') . ' :: UA=' . $sUA . "\n",
    FILE_APPEND
);

http_response_code(204);
