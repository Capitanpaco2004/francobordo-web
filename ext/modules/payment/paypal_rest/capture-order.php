<?php
/**
 * ext/modules/payment/paypal_rest/capture-order.php
 *
 * Endpoint POST llamado por el JS SDK tras la aprobacion del usuario (onApprove).
 * Captura la orden via REST y devuelve { ok:true, order_id, capture_id, status }.
 *
 * Garantiza respuesta JSON (igual que create-order.php).
 */

ob_start();
@ini_set('display_errors', '0');
@ini_set('html_errors',    '0');
header('Content-Type: application/json; charset=utf-8');

$LOG = '/home/francobordo/public_html/paypal_rest.log';
function pp_log($s) { global $LOG; @file_put_contents($LOG, '['.date('Y-m-d H:i:s').'] [capture-order] '.$s."\n", FILE_APPEND); }

function pp_emit($payload, $http = 200) {
    if (ob_get_level() > 0) {
        $stray = ob_get_clean();
        if (trim($stray) !== '') pp_log('STRAY OUTPUT: ' . substr($stray, 0, 800));
    }
    http_response_code($http);
    echo json_encode($payload);
    exit;
}

register_shutdown_function(function(){
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_level() > 0) ob_end_clean();
        pp_log('FATAL: '.$err['message'].' at '.$err['file'].':'.$err['line']);
        http_response_code(500);
        echo json_encode(['error'=>'fatal','detail'=>$err['message']]);
    }
});

set_error_handler(function($severity, $message, $file, $line){
    pp_log("PHP $severity: $message at $file:$line");
    return true;
});

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') pp_emit(['error'=>'method_not_allowed'], 405);

    $sRaw = file_get_contents('php://input');
    $aIn  = json_decode((string)$sRaw, true);
    if (!is_array($aIn)) $aIn = $_POST;
    $sOrderId = isset($aIn['order_id']) ? trim((string)$aIn['order_id']) : '';
    if ($sOrderId === '' || !preg_match('/^[A-Z0-9]{6,32}$/', $sOrderId)) pp_emit(['error'=>'bad_order_id'], 400);

    chdir(__DIR__ . '/../../../..');
    require 'includes/application_top.php';

    if (!tep_session_is_registered('customer_id')) pp_emit(['error'=>'not_authenticated'], 401);
    if (!defined('MODULE_PAYMENT_PAYPAL_REST_STATUS') || MODULE_PAYMENT_PAYPAL_REST_STATUS !== 'True') pp_emit(['error'=>'module_disabled'], 503);

    pp_log('start: customer_id='.((int)($customer_id ?? 0)).' order_id='.$sOrderId);

    $sIntent = (defined('MODULE_PAYMENT_PAYPAL_REST_INTENT') && MODULE_PAYMENT_PAYPAL_REST_INTENT === 'AUTHORIZE') ? 'AUTHORIZE' : 'CAPTURE';

    require_once DIR_FS_CATALOG . 'includes/modules/payment/PayPalRest/Client.php';
    $client = new \PayPalRest\Client();
    $resp = ($sIntent === 'AUTHORIZE') ? $client->authorizeOrder($sOrderId) : $client->captureOrder($sOrderId);

    $sCaptureId = '';
    $sStatus    = $resp['status'] ?? '';
    $aPU        = $resp['purchase_units'][0] ?? [];
    if (isset($aPU['payments']['captures'][0]['id'])) {
        $sCaptureId = (string)$aPU['payments']['captures'][0]['id'];
        $sStatus    = $aPU['payments']['captures'][0]['status'] ?? $sStatus;
    } elseif (isset($aPU['payments']['authorizations'][0]['id'])) {
        $sCaptureId = (string)$aPU['payments']['authorizations'][0]['id'];
        $sStatus    = $aPU['payments']['authorizations'][0]['status'] ?? $sStatus;
    }

    if ($sCaptureId === '' || !in_array($sStatus, ['COMPLETED','CREATED','APPROVED'], true)) {
        pp_log('Unexpected status='.$sStatus.' capture_id='.$sCaptureId.' resp='.json_encode($resp));
        pp_emit(['error'=>'unexpected_paypal_status','status'=>$sStatus,'resp'=>$resp], 502);
    }

    pp_log('OK order_id='.$sOrderId.' capture_id='.$sCaptureId.' status='.$sStatus);
    pp_emit(['ok'=>true,'order_id'=>$sOrderId,'capture_id'=>$sCaptureId,'status'=>$sStatus]);

} catch (\Throwable $e) {
    pp_log('EXCEPTION: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());
    pp_emit(['error'=>'exception','detail'=>$e->getMessage()], 502);
}
