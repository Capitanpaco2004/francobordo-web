<?php
/**
 * ext/modules/payment/paypal_rest/create-order.php
 *
 * Endpoint POST llamado por el JS SDK (createOrder).
 * Calcula el total server-side, crea la orden en PayPal y devuelve { id: "..." }.
 *
 * Garantiza respuesta JSON aunque PHP escupa warnings/fatales — captura todo en un
 * buffer, lo loguea a /home/francobordo/public_html/paypal_rest.log para debug, y
 * emite SOLO el JSON en el body.
 */

// 1) Aislamiento de output: nada se filtra antes del JSON
ob_start();
@ini_set('display_errors', '0');
@ini_set('html_errors',    '0');
header('Content-Type: application/json; charset=utf-8');

$LOG = '/home/francobordo/public_html/paypal_rest.log';
function pp_log($s) { global $LOG; @file_put_contents($LOG, '['.date('Y-m-d H:i:s').'] [create-order] '.$s."\n", FILE_APPEND); }

function pp_emit($payload, $http = 200) {
    if (ob_get_level() > 0) {
        $stray = ob_get_clean();
        if (trim($stray) !== '') pp_log('STRAY OUTPUT: ' . substr($stray, 0, 800));
    }
    http_response_code($http);
    echo json_encode($payload);
    exit;
}

// 2) Shutdown handler para fatales
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
    return true; // suprimir output al cliente
});

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') pp_emit(['error'=>'method_not_allowed'], 405);

    chdir(__DIR__ . '/../../../..');
    require 'includes/application_top.php';

    if (!tep_session_is_registered('customer_id')) pp_emit(['error'=>'not_authenticated'], 401);

    if (!defined('MODULE_PAYMENT_PAYPAL_REST_STATUS') || MODULE_PAYMENT_PAYPAL_REST_STATUS !== 'True') {
        pp_emit(['error'=>'module_disabled'], 503);
    }

    pp_log('start: customer_id='.((int)($customer_id ?? 0)));

    // Rehidratar shipping desde sesion (sino $order->info['shipping_method']='' y los modulos ot_* no aplican bien)
    if (tep_session_is_registered('sendto'))    $sendto    = $_SESSION['sendto'] ?? null;
    if (tep_session_is_registered('billto'))    $billto    = $_SESSION['billto'] ?? null;
    if (tep_session_is_registered('shipping'))  $shipping  = $_SESSION['shipping'] ?? null;
    if (tep_session_is_registered('payment'))   $payment   = $_SESSION['payment']  ?? null;

    require_once DIR_FS_CATALOG . 'includes/classes/order.php';
    $order = new order();

    // CRITICO: cargar shipping module para que los ot_* puedan leer $tipsa
    // (y similares) como globales. Sin esto, ot_insurance/ot_shipping leen
    // null y el IVA sobre el seguro sale a 0 → mismatch de ~2-3 EUR.
    require_once DIR_FS_CATALOG . 'includes/classes/shipping.php';
    $shipping_modules = new shipping($shipping ?? null);

    // Y ejecutar el motor de totalizadores para que info['total'] incluya
    // ot_shipping, ot_insurance, ot_tax, ot_discount, etc.
    require_once DIR_FS_CATALOG . 'includes/classes/order_total.php';
    $order_total_modules = new order_total();
    $order_total_modules->process();

    $fTotal = isset($order->info['total']) ? (float)$order->info['total'] : 0.0;
    if ($fTotal <= 0 && isset($cart) && is_object($cart)) {
        $fTotal = (float)$cart->show_total();
    }
    pp_log('total='.number_format($fTotal,2,'.','').' EUR (con ot_* aplicados)');

    if ($fTotal <= 0) pp_emit(['error'=>'empty_cart'], 400);

    $sIntent = (defined('MODULE_PAYMENT_PAYPAL_REST_INTENT') && MODULE_PAYMENT_PAYPAL_REST_INTENT === 'AUTHORIZE') ? 'AUTHORIZE' : 'CAPTURE';

    // No mandamos el desglose de items a PayPal: con IVA + envio + seguro
    // separados, mapear cada concepto en breakdown.{item_total,shipping,tax_total,insurance}
    // y que cuadre al centimo es fragil. PayPal acepta el order solo con amount.value
    // y a efectos del cliente la experiencia es identica. Si en el futuro se quiere
    // desglose (para tracking analitico de PayPal), hacer un mapeo exacto.
    require_once DIR_FS_CATALOG . 'includes/modules/payment/PayPalRest/Client.php';
    $client = new \PayPalRest\Client();
    $resp   = $client->createOrder($fTotal, $sIntent, 'cust-'.(int)$customer_id.'-'.date('YmdHis'), []);

    if (empty($resp['id'])) {
        pp_log('PayPal no devolvio id: '.json_encode($resp));
        pp_emit(['error'=>'no_order_id','resp'=>$resp], 502);
    }

    pp_log('OK order_id='.$resp['id'].' status='.($resp['status']??'?'));
    pp_emit([
        'id'     => $resp['id'],
        'status' => $resp['status'] ?? 'CREATED',
        'amount' => number_format($fTotal, 2, '.', ''),
    ]);

} catch (\Throwable $e) {
    pp_log('EXCEPTION: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());
    pp_emit(['error'=>'exception','detail'=>$e->getMessage()], 502);
}
