<?php
/**
 * paypal_googlepay.php
 *
 * Modulo de pago: Google Pay via PayPal Checkout v2.
 *
 * Usa el SDK de PayPal con el componente `googlepay` para procesar pagos con
 * Google Pay. Las credenciales (Client ID, Secret, Environment) se leen de
 * MODULE_PAYMENT_PAYPAL_REST_* — instalar y configurar paypal_rest primero.
 *
 * En la cuenta PayPal Business hay que tener Google Pay activado en eligibility
 * (PayPal lo activa tras onboarding). En Sandbox suele estar disponible directamente.
 *
 * Comparte endpoints con paypal_rest (create-order.php y capture-order.php):
 * son metodo-agnostico — solo crean/capturan orden en PayPal por importe.
 */

class paypal_googlepay {
    public $code;
    public $signature;
    public $title;
    public $public_title;
    public $description;
    public $sort_order;
    public $enabled;
    public $order_status;
    public $transaction_id;
    public $_check;

    public function __construct() {
        global $order;

        $this->signature    = 'paypal|paypal_googlepay|1.0|2.0';
        $this->code         = 'paypal_googlepay';
        $this->title        = defined('MODULE_PAYMENT_PAYPAL_GOOGLEPAY_TEXT_TITLE')        ? MODULE_PAYMENT_PAYPAL_GOOGLEPAY_TEXT_TITLE        : '';
        $this->public_title = defined('MODULE_PAYMENT_PAYPAL_GOOGLEPAY_TEXT_PUBLIC_TITLE') ? MODULE_PAYMENT_PAYPAL_GOOGLEPAY_TEXT_PUBLIC_TITLE : '';
        $this->description  = defined('MODULE_PAYMENT_PAYPAL_GOOGLEPAY_TEXT_DESCRIPTION')  ? MODULE_PAYMENT_PAYPAL_GOOGLEPAY_TEXT_DESCRIPTION  : '';
        $this->sort_order   = defined('MODULE_PAYMENT_PAYPAL_GOOGLEPAY_SORT_ORDER')        ? MODULE_PAYMENT_PAYPAL_GOOGLEPAY_SORT_ORDER        : '';
        $this->enabled      = ( defined('MODULE_PAYMENT_PAYPAL_GOOGLEPAY_STATUS') && MODULE_PAYMENT_PAYPAL_GOOGLEPAY_STATUS == 'True' );

        if ( defined('MODULE_PAYMENT_PAYPAL_GOOGLEPAY_ORDER_STATUS_ID') && (int)MODULE_PAYMENT_PAYPAL_GOOGLEPAY_ORDER_STATUS_ID > 0 ) {
            $this->order_status = MODULE_PAYMENT_PAYPAL_GOOGLEPAY_ORDER_STATUS_ID;
        }

        if ( is_object($order) ) {
            $this->update_status();
        }
    }

    public function update_status() {
        global $order;

        if ( $this->enabled === true && defined('MODULE_PAYMENT_PAYPAL_GOOGLEPAY_ZONE') && (int)MODULE_PAYMENT_PAYPAL_GOOGLEPAY_ZONE > 0 ) {
            $check_flag = false;
            $check_query = tep_db_query(
                "SELECT zone_id FROM " . TABLE_ZONES_TO_GEO_ZONES
                . " WHERE geo_zone_id = '" . (int)MODULE_PAYMENT_PAYPAL_GOOGLEPAY_ZONE . "'"
                . " AND zone_country_id = '" . (int)$order->delivery['country']['id'] . "' ORDER BY zone_id"
            );
            while ( $check = tep_db_fetch_array($check_query) ) {
                if ( $check['zone_id'] < 1 ) { $check_flag = true; break; }
                if ( $check['zone_id'] == $order->delivery['zone_id'] ) { $check_flag = true; break; }
            }
            if ( ! $check_flag ) $this->enabled = false;
        }
    }

    public function javascript_validation() { return false; }
    public function selection()              { return array('id' => $this->code, 'module' => $this->public_title); }
    public function pre_confirmation_check() { return; }
    public function confirmation()           { return false; }

    public function process_button() {
        $sClientId = defined('MODULE_PAYMENT_PAYPAL_REST_CLIENT_ID')   ? MODULE_PAYMENT_PAYPAL_REST_CLIENT_ID   : '';
        $sIntent   = ( defined('MODULE_PAYMENT_PAYPAL_REST_INTENT') && MODULE_PAYMENT_PAYPAL_REST_INTENT === 'AUTHORIZE' ) ? 'authorize' : 'capture';

        $sErrorBanner = '';
        if ( $sClientId === '' ) {
            $sErrorBanner = '<div style="background:#fff5f5;border-left:3px solid #a00;padding:10px 14px;color:#a00;margin:10px 0;">'
                          . '<strong>Google Pay no esta configurado.</strong> Instala y configura paypal_rest (Client ID + Secret) primero.'
                          . '</div>';
        }

        $aSdkParams = array(
            'client-id'  => $sClientId,
            'currency'   => 'EUR',
            'intent'     => $sIntent,
            'components' => 'googlepay,buttons',
            'disable-funding' => 'card,credit',
        );
        $sSdkUrl = 'https://www.paypal.com/sdk/js?' . http_build_query($aSdkParams);

        ob_start();
        ?>
        <?php echo $sErrorBanner; ?>
        <?php echo tep_draw_hidden_field('paypal_rest_order_id', ''); ?>
        <?php echo tep_draw_hidden_field('paypal_rest_capture_id', ''); ?>
        <?php echo tep_draw_hidden_field('paypal_rest_method', 'googlepay'); ?>
        <div id="paypal-googlepay-wrap" style="max-width:480px;margin:14px 0;">
            <div id="paypal-googlepay-status" style="
                display:flex;align-items:center;gap:10px;
                font-size:14.5px;color:#003087;font-weight:600;
                padding:10px 14px;margin:0 0 12px;
                background:#f0f6fc;border-left:3px solid #009cde;border-radius:4px;
                line-height:1.4;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="#009cde" style="flex-shrink:0;">
                    <path d="M12 2 C 6.48 2 2 6.48 2 12 c 0 5.52 4.48 10 10 10 s 10 -4.48 10 -10 C 22 6.48 17.52 2 12 2 Z m 1 15 h -2 v -6 h 2 v 6 z m 0 -8 h -2 V 7 h 2 v 2 z"/>
                </svg>
                <span>Pulsa el bot&oacute;n de Google Pay para completar el pago:</span>
            </div>
            <div id="paypal-googlepay-button"></div>
            <div id="paypal-googlepay-error" style="display:none;background:#fff5f5;border-left:3px solid #a00;padding:8px 12px;color:#a00;font-size:13px;margin-top:10px;"></div>
        </div>
        <?php if ( $sClientId !== '' ): ?>
        <script src="https://pay.google.com/gp/p/js/pay.js"></script>
        <script src="<?php echo htmlspecialchars($sSdkUrl, ENT_QUOTES, 'UTF-8'); ?>" data-namespace="paypal_googlepay_sdk"></script>
        <script>
        (function(){
            function $(id){return document.getElementById(id);}
            function hideDefaultSubmit(){var b=$('checkoutShippingButton');if(b)b.style.display='none';}
            function showError(m){var e=$('paypal-googlepay-error');if(e){e.textContent=m;e.style.display='block';}}
            function getForm(){return document.querySelector('form[action*="checkout_process"]')||(document.querySelector('input[name="paypal_rest_order_id"]')||{}).form;}

            function whenReady(cb){
                if(window.paypal_googlepay_sdk&&window.paypal_googlepay_sdk.Googlepay&&window.google&&window.google.payments)return cb();
                var n=0,t=setInterval(function(){
                    if(window.paypal_googlepay_sdk&&window.paypal_googlepay_sdk.Googlepay&&window.google&&window.google.payments){clearInterval(t);cb();}
                    else if(++n>200){clearInterval(t);showError('No se han podido cargar los SDKs de Google/PayPal.');}
                },50);
            }

            whenReady(async function(){
                hideDefaultSubmit();
                try {
                    var paypalConfig = await window.paypal_googlepay_sdk.Googlepay().config();
                    if (!paypalConfig || !paypalConfig.isEligible) {
                        showError('Google Pay no esta disponible en esta sesion (cuenta PayPal sin Google Pay habilitado o navegador incompatible).');
                        return;
                    }
                    var paymentsClient = new google.payments.api.PaymentsClient({environment: paypalConfig.environment});
                    var readyToPayReq  = {
                        apiVersion: 2, apiVersionMinor: 0,
                        allowedPaymentMethods: paypalConfig.allowedPaymentMethods
                    };
                    var ready = await paymentsClient.isReadyToPay(readyToPayReq);
                    if (!ready.result) {
                        showError('Google Pay no esta disponible en este dispositivo.');
                        return;
                    }
                    var btn = paymentsClient.createButton({
                        onClick: onGooglePayClick,
                        buttonType: 'pay', buttonColor: 'black', buttonSizeMode: 'fill'
                    });
                    $('paypal-googlepay-button').appendChild(btn);

                    async function onGooglePayClick(){
                        try {
                            // Crear order en PayPal primero (necesitamos importe valido)
                            var createResp = await fetch('ext/modules/payment/paypal_rest/create-order.php',{
                                method:'POST',credentials:'same-origin',
                                headers:{'Content-Type':'application/json'},body:'{}'
                            }).then(function(r){return r.json();});
                            if (!createResp || !createResp.id) {
                                showError('Error creando orden PayPal: '+(createResp&&createResp.error?createResp.error:'sin id'));return;
                            }
                            var orderId = createResp.id;

                            // Pedir paymentData a Google Pay
                            var paymentDataReq = {
                                apiVersion: 2, apiVersionMinor: 0,
                                allowedPaymentMethods: paypalConfig.allowedPaymentMethods,
                                merchantInfo: paypalConfig.merchantInfo,
                                transactionInfo: {
                                    countryCode: 'ES',
                                    currencyCode: 'EUR',
                                    totalPriceStatus: 'FINAL',
                                    totalPrice: String(createResp.amount || '0.00'),
                                    totalPriceLabel: 'Total'
                                }
                            };
                            var paymentData = await paymentsClient.loadPaymentData(paymentDataReq);

                            // Confirmar la orden con PayPal usando paymentMethodData de Google
                            var confirm = await window.paypal_googlepay_sdk.Googlepay().confirmOrder({
                                orderId: orderId,
                                paymentMethodData: paymentData.paymentMethodData
                            });
                            if (confirm.status !== 'APPROVED' && confirm.status !== 'COMPLETED') {
                                showError('PayPal no ha aprobado la orden: '+confirm.status);return;
                            }

                            // Capturar
                            var cap = await fetch('ext/modules/payment/paypal_rest/capture-order.php',{
                                method:'POST',credentials:'same-origin',
                                headers:{'Content-Type':'application/json'},
                                body:JSON.stringify({order_id:orderId})
                            }).then(function(r){return r.json();});
                            if (!cap || !cap.ok) {showError('Error capturando: '+(cap&&cap.error?cap.error:'')+(cap.detail?': '+cap.detail:''));return;}

                            // Rellenar hidden y submit
                            document.querySelector('input[name="paypal_rest_order_id"]').value   = cap.order_id;
                            document.querySelector('input[name="paypal_rest_capture_id"]').value = cap.capture_id;
                            var f = getForm();
                            if (f) f.submit(); else window.location.href='checkout_process.php';
                        } catch (err) {
                            // "User closed the Payment Request UI" / AbortError = el usuario cerro
                            // la hoja de Google Pay sin pagar. No es un error: limpiamos el aviso.
                            var msg = (err && err.message ? err.message : String(err));
                            if (/closed|abort|cancel/i.test(msg)) {
                                var e=$('paypal-googlepay-error'); if(e){e.style.display='none';}
                            } else {
                                showError('Google Pay error: '+msg);
                            }
                        }
                    }
                } catch (err) {
                    showError('No se ha podido inicializar Google Pay: '+(err&&err.message?err.message:err));
                }
            });
        })();
        </script>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    public function before_process() {
        global $order, $customer_id;
        $sOrderId   = isset($_POST['paypal_rest_order_id'])   ? trim((string)$_POST['paypal_rest_order_id'])   : '';
        $sCaptureId = isset($_POST['paypal_rest_capture_id']) ? trim((string)$_POST['paypal_rest_capture_id']) : '';

        if ( $sOrderId === '' || $sCaptureId === '' ) {
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode('No se ha completado el pago con Google Pay'), 'SSL'));
        }

        // Comparte endpoints (y por tanto intent) con paypal_rest.
        // #FB-PAYPAL-REPLAY (2026-08-29): serializa por order_id de PayPal ANTES de validar nada.
        // Los guardias anti-replay de mas abajo leen marcas que solo se escriben en after_process(),
        // y entre before_process() y after_process() se construye el pedido entero y se envian dos
        // correos: la ventana son SEGUNDOS, no milisegundos. Sin este lock, N peticiones simultaneas
        // con el MISMO pago pasan las comprobaciones a la vez y crean N pedidos con un solo cobro.
        // El lock se libera al cerrar la conexion (fin de la peticion), es decir DESPUES de
        // after_process(). Valido porque USE_PCONNECT='false' y tep_db_connect() no usa prefijo 'p:'.
        $rPpLock = tep_db_query("SELECT GET_LOCK('" . tep_db_input('fbpp_' . substr($sOrderId, 0, 40)) . "', 10) AS l");
        $aPpLock = tep_db_fetch_array($rPpLock);
        if ( empty($aPpLock['l']) ) {
            @error_log('[paypal] LOCK no obtenido order=' . $sOrderId . ' customer=' . (int)$customer_id);
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode('Tu pago se esta procesando. Espera unos segundos y revisa tus pedidos antes de volver a intentarlo.'), 'SSL'));
        }

        $sIntent = ( defined('MODULE_PAYMENT_PAYPAL_REST_INTENT') && MODULE_PAYMENT_PAYPAL_REST_INTENT === 'AUTHORIZE' ) ? 'AUTHORIZE' : 'CAPTURE';

        require_once DIR_FS_CATALOG . 'includes/modules/payment/PayPalRest/Client.php';
        try {
            $oClient = new \PayPalRest\Client();
            $aOrder  = $oClient->getOrder($sOrderId);
        } catch ( \Throwable $e ) {
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode('Error verificando el pago: ' . $e->getMessage()), 'SSL'));
        }

        // APPROVED = aprobado por el comprador pero SIN cobrar. Solo COMPLETED.
        $sStatus = $aOrder['status'] ?? '';
        if ( $sStatus !== 'COMPLETED' ) {
            @error_log('[paypal_googlepay] estado no COMPLETED order=' . $sOrderId . ' status=' . $sStatus . ' customer=' . (int)$customer_id);
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode('El pago no se ha completado (' . $sStatus . ')'), 'SSL'));
        }

        // El pedido de PayPal tiene que ser de ESTE cliente: create-order.php graba
        // reference_id = 'cust-<customer_id>-<YmdHis>'.
        $sReference = (string)( $aOrder['purchase_units'][0]['reference_id'] ?? '' );
        if ( strpos($sReference, 'cust-' . (int)$customer_id . '-') !== 0 ) {
            @error_log('[paypal_googlepay] reference_id ajeno order=' . $sOrderId . ' ref=' . $sReference . ' customer=' . (int)$customer_id);
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode('El pago no corresponde a esta sesion. La compra no se ha completado.'), 'SSL'));
        }

        // Cobro REAL segun PayPal. Vacio = no hay dinero (p.ej. captura DECLINED
        // con el pedido en COMPLETED).
        $aPayments = \PayPalRest\Client::listSettledPayments($aOrder, $sIntent);
        if ( empty($aPayments) ) {
            @error_log('[paypal_googlepay] sin cobro registrado order=' . $sOrderId . ' intent=' . $sIntent . ' customer=' . (int)$customer_id);
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode('PayPal no ha registrado el cobro. La compra no se ha completado.'), 'SSL'));
        }

        // El capture_id llega por POST desde el navegador: solo vale si PayPal lo confirma.
        $aPayment = null;
        foreach ( $aPayments as $aTry ) {
            if ( $aTry['id'] !== '' && $aTry['id'] === $sCaptureId ) { $aPayment = $aTry; break; }
        }
        if ( $aPayment === null ) {
            @error_log('[paypal_googlepay] capture_id no confirmado por PayPal order=' . $sOrderId . ' post=' . $sCaptureId . ' customer=' . (int)$customer_id);
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode('No se ha podido verificar el cobro con PayPal. La compra no se ha completado.'), 'SSL'));
        }

        // Reconciliacion de importe: el pago debe coincidir con el total del pedido (servidor).
        if ( ! \PayPalRest\Client::verifyCapturedAmount($aOrder, tep_round($order->info['total'], 2), 'EUR', $sIntent) ) {
            @error_log('[paypal_googlepay] importe NO coincide order=' . $sOrderId . ' total_esperado=' . tep_round($order->info['total'], 2));
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode('El importe del pago no coincide con el del pedido. La compra no se ha completado.'), 'SSL'));
        }

        // Anti-replay: un mismo pago de PayPal no puede generar dos pedidos.
        $rReplay = tep_db_query(
            "SELECT orders_id FROM redsys_payment_movements"
            . " WHERE module IN ('paypal_rest', 'paypal_googlepay', 'paypal_applepay')"
            . " AND reference = '" . tep_db_input(substr($sOrderId, 0, 20)) . "'"
            . " AND orders_id > 0 LIMIT 1"
        );
        if ( tep_db_num_rows($rReplay) > 0 ) {
            $aReplay = tep_db_fetch_array($rReplay);
            @error_log('[paypal_googlepay] REPLAY order=' . $sOrderId . ' ya usado en pedido ' . (int)$aReplay['orders_id'] . ' customer=' . (int)$customer_id);
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode('Este pago de PayPal ya se ha utilizado en un pedido anterior.'), 'SSL'));
        }
        $rReplay2 = tep_db_query(
            "SELECT orders_id FROM orders WHERE paypal_transaction_id = '" . tep_db_input($aPayment['id']) . "' LIMIT 1"
        );
        if ( tep_db_num_rows($rReplay2) > 0 ) {
            $aReplay2 = tep_db_fetch_array($rReplay2);
            @error_log('[paypal_googlepay] REPLAY capture=' . $aPayment['id'] . ' ya usado en pedido ' . (int)$aReplay2['orders_id'] . ' customer=' . (int)$customer_id);
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode('Este pago de PayPal ya se ha utilizado en un pedido anterior.'), 'SSL'));
        }

        $this->transaction_id = $aPayment['id'];

        tep_db_perform('redsys_payment_movements', array(
            'reference'    => substr($sOrderId, 0, 20),
            'value'        => tep_round($order->info['total'], 2),
            'customer_id'  => (int)$customer_id,
            'module'       => 'paypal_googlepay',
            'date_created' => 'now()',
        ));
    }

    public function after_process() {
        global $insert_id, $customer_id;
        tep_db_query("UPDATE orders SET paypal_transaction_id = '" . tep_db_input($this->transaction_id) . "' WHERE orders_id = '" . (int)$insert_id . "'");
        tep_db_query(sprintf(
            'UPDATE redsys_payment_movements SET orders_id = %d WHERE customer_id = %d AND module = "paypal_googlepay" AND reference = "%s" AND orders_id = 0',
            (int)$insert_id, (int)$customer_id,
            tep_db_input(substr((string)($_POST['paypal_rest_order_id'] ?? ''), 0, 20))
        ));
    }

    public function get_error() { return false; }

    public function check() {
        if ( ! isset($this->_check) ) {
            $check_query = tep_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_PAYPAL_GOOGLEPAY_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    public function install() {
        tep_db_query("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ('"
            . "MODULE_PAYMENT_PAYPAL_GOOGLEPAY_STATUS','MODULE_PAYMENT_PAYPAL_GOOGLEPAY_ZONE',"
            . "'MODULE_PAYMENT_PAYPAL_GOOGLEPAY_ORDER_STATUS_ID','MODULE_PAYMENT_PAYPAL_GOOGLEPAY_SORT_ORDER')");

        tep_db_query("INSERT INTO " . TABLE_CONFIGURATION
            . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)"
            . " VALUES ('Activar Google Pay (via PayPal)', 'MODULE_PAYMENT_PAYPAL_GOOGLEPAY_STATUS', 'False',"
            . " '¿Aceptar pagos con Google Pay? Usa credenciales de paypal_rest. Requiere Google Pay habilitado en la cuenta PayPal Business (PayPal lo activa tras onboarding).',"
            . " '6', '0', 'tep_cfg_select_option(array(\\'True\\', \\'False\\'), ', NOW())");

        tep_db_query("INSERT INTO " . TABLE_CONFIGURATION
            . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added)"
            . " VALUES ('Zona de pago', 'MODULE_PAYMENT_PAYPAL_GOOGLEPAY_ZONE', '0',"
            . " 'Solo ofrecer Google Pay a clientes de la zona seleccionada. 0 = todos.',"
            . " '6', '0', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', NOW())");

        tep_db_query("INSERT INTO " . TABLE_CONFIGURATION
            . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)"
            . " VALUES ('Estado del pedido tras pagar', 'MODULE_PAYMENT_PAYPAL_GOOGLEPAY_ORDER_STATUS_ID', '0',"
            . " 'Estado al que pasan los pedidos pagados con Google Pay.',"
            . " '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', NOW())");

        tep_db_query("INSERT INTO " . TABLE_CONFIGURATION
            . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)"
            . " VALUES ('Orden de visualizacion', 'MODULE_PAYMENT_PAYPAL_GOOGLEPAY_SORT_ORDER', '6',"
            . " 'Orden de aparicion en checkout (menor = antes).',"
            . " '6', '0', NOW())");
    }

    public function remove() {
        tep_db_query("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ('" . implode("', '", $this->keys()) . "')");
    }

    public function keys() {
        return array(
            'MODULE_PAYMENT_PAYPAL_GOOGLEPAY_STATUS',
            'MODULE_PAYMENT_PAYPAL_GOOGLEPAY_ZONE',
            'MODULE_PAYMENT_PAYPAL_GOOGLEPAY_ORDER_STATUS_ID',
            'MODULE_PAYMENT_PAYPAL_GOOGLEPAY_SORT_ORDER',
        );
    }
}
