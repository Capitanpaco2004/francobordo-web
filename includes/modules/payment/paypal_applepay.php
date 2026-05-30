<?php
/**
 * paypal_applepay.php
 *
 * Modulo de pago: Apple Pay via PayPal Checkout v2.
 *
 * Comparte credenciales con paypal_rest (Client ID, Secret, Environment).
 *
 * REQUISITO PREVIO: verificacion de dominio.
 * Apple Pay requiere que el dominio (www.francobordo.com) este verificado en
 * la cuenta PayPal Business. El flujo es:
 *   1. En PayPal Dashboard → Apple Pay → Add domain → introduces www.francobordo.com
 *   2. PayPal te genera un archivo `apple-developer-merchantid-domain-association`
 *   3. Subir ese archivo a `/.well-known/apple-developer-merchantid-domain-association`
 *      del docroot publico (servirlo como text/plain, NO redirigir)
 *   4. Pulsar "Verify" en PayPal Dashboard
 *
 * Sin esta verificacion, el SDK rechaza la inicializacion del boton Apple Pay
 * con el error "Domain not registered for Apple Pay".
 *
 * Funciona solo en Safari (macOS/iOS) — otros navegadores no muestran el boton.
 *
 * Comparte endpoints create-order.php / capture-order.php con paypal_rest.
 */

class paypal_applepay {
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

        $this->signature    = 'paypal|paypal_applepay|1.0|2.0';
        $this->code         = 'paypal_applepay';
        $this->title        = defined('MODULE_PAYMENT_PAYPAL_APPLEPAY_TEXT_TITLE')        ? MODULE_PAYMENT_PAYPAL_APPLEPAY_TEXT_TITLE        : '';
        $this->public_title = defined('MODULE_PAYMENT_PAYPAL_APPLEPAY_TEXT_PUBLIC_TITLE') ? MODULE_PAYMENT_PAYPAL_APPLEPAY_TEXT_PUBLIC_TITLE : '';
        $this->description  = defined('MODULE_PAYMENT_PAYPAL_APPLEPAY_TEXT_DESCRIPTION')  ? MODULE_PAYMENT_PAYPAL_APPLEPAY_TEXT_DESCRIPTION  : '';
        $this->sort_order   = defined('MODULE_PAYMENT_PAYPAL_APPLEPAY_SORT_ORDER')        ? MODULE_PAYMENT_PAYPAL_APPLEPAY_SORT_ORDER        : '';
        $this->enabled      = ( defined('MODULE_PAYMENT_PAYPAL_APPLEPAY_STATUS') && MODULE_PAYMENT_PAYPAL_APPLEPAY_STATUS == 'True' );

        if ( defined('MODULE_PAYMENT_PAYPAL_APPLEPAY_ORDER_STATUS_ID') && (int)MODULE_PAYMENT_PAYPAL_APPLEPAY_ORDER_STATUS_ID > 0 ) {
            $this->order_status = MODULE_PAYMENT_PAYPAL_APPLEPAY_ORDER_STATUS_ID;
        }

        if ( is_object($order) ) {
            $this->update_status();
        }
    }

    public function update_status() {
        global $order;

        if ( $this->enabled === true && defined('MODULE_PAYMENT_PAYPAL_APPLEPAY_ZONE') && (int)MODULE_PAYMENT_PAYPAL_APPLEPAY_ZONE > 0 ) {
            $check_flag = false;
            $check_query = tep_db_query(
                "SELECT zone_id FROM " . TABLE_ZONES_TO_GEO_ZONES
                . " WHERE geo_zone_id = '" . (int)MODULE_PAYMENT_PAYPAL_APPLEPAY_ZONE . "'"
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
        $sClientId = defined('MODULE_PAYMENT_PAYPAL_REST_CLIENT_ID') ? MODULE_PAYMENT_PAYPAL_REST_CLIENT_ID : '';
        $sIntent   = ( defined('MODULE_PAYMENT_PAYPAL_REST_INTENT') && MODULE_PAYMENT_PAYPAL_REST_INTENT === 'AUTHORIZE' ) ? 'authorize' : 'capture';

        $sErrorBanner = '';
        if ( $sClientId === '' ) {
            $sErrorBanner = '<div style="background:#fff5f5;border-left:3px solid #a00;padding:10px 14px;color:#a00;margin:10px 0;">'
                          . '<strong>Apple Pay no esta configurado.</strong> Instala y configura paypal_rest (Client ID + Secret) primero.'
                          . '</div>';
        }

        $aSdkParams = array(
            'client-id'       => $sClientId,
            'currency'        => 'EUR',
            'intent'          => $sIntent,
            'components'      => 'applepay,buttons',
            'disable-funding' => 'card,credit',
        );
        $sSdkUrl = 'https://www.paypal.com/sdk/js?' . http_build_query($aSdkParams);

        ob_start();
        ?>
        <?php echo $sErrorBanner; ?>
        <?php echo tep_draw_hidden_field('paypal_rest_order_id', ''); ?>
        <?php echo tep_draw_hidden_field('paypal_rest_capture_id', ''); ?>
        <?php echo tep_draw_hidden_field('paypal_rest_method', 'applepay'); ?>
        <div id="paypal-applepay-wrap" style="max-width:480px;margin:14px 0;">
            <div id="paypal-applepay-status" style="
                display:flex;align-items:center;gap:10px;
                font-size:14.5px;color:#003087;font-weight:600;
                padding:10px 14px;margin:0 0 12px;
                background:#f0f6fc;border-left:3px solid #009cde;border-radius:4px;
                line-height:1.4;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="#009cde" style="flex-shrink:0;">
                    <path d="M12 2 C 6.48 2 2 6.48 2 12 c 0 5.52 4.48 10 10 10 s 10 -4.48 10 -10 C 22 6.48 17.52 2 12 2 Z m 1 15 h -2 v -6 h 2 v 6 z m 0 -8 h -2 V 7 h 2 v 2 z"/>
                </svg>
                <span>Pulsa el bot&oacute;n de Apple Pay para completar el pago:</span>
            </div>
            <div id="paypal-applepay-button-container"></div>
            <div id="paypal-applepay-error" style="display:none;background:#fff5f5;border-left:3px solid #a00;padding:8px 12px;color:#a00;font-size:13px;margin-top:10px;"></div>
        </div>
        <?php if ( $sClientId !== '' ): ?>
        <style>
            apple-pay-button {
                --apple-pay-button-width: 240px;
                --apple-pay-button-height: 44px;
                --apple-pay-button-border-radius: 6px;
                --apple-pay-button-padding: 5px 0;
            }
        </style>
        <script src="https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js"></script>
        <script src="<?php echo htmlspecialchars($sSdkUrl, ENT_QUOTES, 'UTF-8'); ?>" data-namespace="paypal_applepay_sdk"></script>
        <script>
        (function(){
            function $(id){return document.getElementById(id);}
            function hideDefaultSubmit(){var b=$('checkoutShippingButton');if(b)b.style.display='none';}
            function showError(m){var e=$('paypal-applepay-error');if(e){e.textContent=m;e.style.display='block';}}
            function getForm(){return document.querySelector('form[action*="checkout_process"]')||(document.querySelector('input[name="paypal_rest_order_id"]')||{}).form;}

            // Apple Pay solo funciona en Safari con ApplePaySession
            if (!window.ApplePaySession || !window.ApplePaySession.canMakePayments()) {
                showError('Apple Pay solo esta disponible en Safari (macOS / iOS) con un dispositivo configurado.');
                return;
            }

            function whenReady(cb){
                if(window.paypal_applepay_sdk&&window.paypal_applepay_sdk.Applepay)return cb();
                var n=0,t=setInterval(function(){
                    if(window.paypal_applepay_sdk&&window.paypal_applepay_sdk.Applepay){clearInterval(t);cb();}
                    else if(++n>200){clearInterval(t);showError('No se ha podido cargar el SDK de PayPal/Apple Pay.');}
                },50);
            }

            whenReady(async function(){
                hideDefaultSubmit();
                try {
                    var applepay = window.paypal_applepay_sdk.Applepay();
                    var config   = await applepay.config();
                    if (!config || !config.isEligible) {
                        showError('Apple Pay no esta disponible para esta cuenta PayPal o este dominio no esta verificado.');
                        return;
                    }

                    // Renderizar el boton Apple Pay nativo
                    var container = $('paypal-applepay-button-container');
                    container.innerHTML = '<apple-pay-button buttonstyle="black" type="buy" locale="es-ES"></apple-pay-button>';
                    container.querySelector('apple-pay-button').addEventListener('click', onApplePayClick);

                    async function onApplePayClick(){
                        try {
                            var createResp = await fetch('ext/modules/payment/paypal_rest/create-order.php',{
                                method:'POST',credentials:'same-origin',
                                headers:{'Content-Type':'application/json'},body:'{}'
                            }).then(function(r){return r.json();});
                            if (!createResp || !createResp.id) {
                                showError('Error creando orden: '+(createResp&&createResp.error?createResp.error:'sin id'));return;
                            }
                            var orderId = createResp.id;

                            var paymentRequest = {
                                countryCode: config.countryCode || 'ES',
                                merchantCapabilities: config.merchantCapabilities,
                                supportedNetworks: config.supportedNetworks,
                                requiredBillingContactFields: ['postalAddress','name','phone','email'],
                                requiredShippingContactFields: ['postalAddress','name','phone','email'],
                                total: {label: 'Francobordo', amount: String(createResp.amount || '0.00'), type: 'final'},
                                currencyCode: 'EUR'
                            };

                            var session = new ApplePaySession(4, paymentRequest);

                            session.onvalidatemerchant = async function(evt){
                                try {
                                    var ms = await applepay.validateMerchant({validationUrl: evt.validationURL});
                                    session.completeMerchantValidation(ms.merchantSession);
                                } catch (e) {
                                    showError('Error validando comercio Apple Pay: '+(e&&e.message?e.message:e));
                                    session.abort();
                                }
                            };

                            session.onpaymentauthorized = async function(evt){
                                try {
                                    await applepay.confirmOrder({
                                        orderId: orderId,
                                        token: evt.payment.token,
                                        billingContact: evt.payment.billingContact,
                                        shippingContact: evt.payment.shippingContact
                                    });
                                    var cap = await fetch('ext/modules/payment/paypal_rest/capture-order.php',{
                                        method:'POST',credentials:'same-origin',
                                        headers:{'Content-Type':'application/json'},
                                        body:JSON.stringify({order_id:orderId})
                                    }).then(function(r){return r.json();});
                                    if (!cap || !cap.ok) {
                                        session.completePayment(ApplePaySession.STATUS_FAILURE);
                                        showError('Error capturando: '+(cap&&cap.error?cap.error:''));return;
                                    }
                                    session.completePayment(ApplePaySession.STATUS_SUCCESS);

                                    document.querySelector('input[name="paypal_rest_order_id"]').value   = cap.order_id;
                                    document.querySelector('input[name="paypal_rest_capture_id"]').value = cap.capture_id;
                                    var f = getForm();
                                    if (f) f.submit(); else window.location.href='checkout_process.php';
                                } catch (e) {
                                    session.completePayment(ApplePaySession.STATUS_FAILURE);
                                    showError('Apple Pay onauthorized error: '+(e&&e.message?e.message:e));
                                }
                            };

                            session.oncancel = function(){
                                showError('Has cancelado el pago con Apple Pay.');
                            };

                            session.begin();
                        } catch (err) {
                            showError('Apple Pay click error: '+(err&&err.message?err.message:err));
                        }
                    }
                } catch (err) {
                    showError('No se ha podido inicializar Apple Pay: '+(err&&err.message?err.message:err));
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
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode('No se ha completado el pago con Apple Pay'), 'SSL'));
        }

        require_once DIR_FS_CATALOG . 'includes/modules/payment/PayPalRest/Client.php';
        try {
            $oClient = new \PayPalRest\Client();
            $aOrder  = $oClient->getOrder($sOrderId);
        } catch ( \Throwable $e ) {
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode('Error verificando el pago: ' . $e->getMessage()), 'SSL'));
        }

        $sStatus = $aOrder['status'] ?? '';
        if ( $sStatus !== 'COMPLETED' && $sStatus !== 'APPROVED' ) {
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode('El pago no se ha completado (' . $sStatus . ')'), 'SSL'));
        }

        $this->transaction_id = $sCaptureId;

        tep_db_perform('redsys_payment_movements', array(
            'reference'    => substr($sOrderId, 0, 20),
            'value'        => tep_round($order->info['total'], 2),
            'customer_id'  => (int)$customer_id,
            'module'       => 'paypal_applepay',
            'date_created' => 'now()',
        ));
    }

    public function after_process() {
        global $insert_id, $customer_id;
        tep_db_query("UPDATE orders SET paypal_transaction_id = '" . tep_db_input($this->transaction_id) . "' WHERE orders_id = '" . (int)$insert_id . "'");
        tep_db_query(sprintf(
            'UPDATE redsys_payment_movements SET orders_id = %d WHERE customer_id = %d AND module = "paypal_applepay" AND reference = "%s" AND orders_id = 0',
            (int)$insert_id, (int)$customer_id,
            tep_db_input(substr((string)($_POST['paypal_rest_order_id'] ?? ''), 0, 20))
        ));
    }

    public function get_error() { return false; }

    public function check() {
        if ( ! isset($this->_check) ) {
            $check_query = tep_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_PAYPAL_APPLEPAY_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    public function install() {
        tep_db_query("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ('"
            . "MODULE_PAYMENT_PAYPAL_APPLEPAY_STATUS','MODULE_PAYMENT_PAYPAL_APPLEPAY_ZONE',"
            . "'MODULE_PAYMENT_PAYPAL_APPLEPAY_ORDER_STATUS_ID','MODULE_PAYMENT_PAYPAL_APPLEPAY_SORT_ORDER')");

        tep_db_query("INSERT INTO " . TABLE_CONFIGURATION
            . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)"
            . " VALUES ('Activar Apple Pay (via PayPal)', 'MODULE_PAYMENT_PAYPAL_APPLEPAY_STATUS', 'False',"
            . " '¿Aceptar pagos con Apple Pay? REQUIERE verificacion del dominio www.francobordo.com en PayPal Dashboard (subir archivo a /.well-known/apple-developer-merchantid-domain-association). Usa credenciales de paypal_rest.',"
            . " '6', '0', 'tep_cfg_select_option(array(\\'True\\', \\'False\\'), ', NOW())");

        tep_db_query("INSERT INTO " . TABLE_CONFIGURATION
            . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added)"
            . " VALUES ('Zona de pago', 'MODULE_PAYMENT_PAYPAL_APPLEPAY_ZONE', '0',"
            . " 'Solo ofrecer Apple Pay a clientes de la zona seleccionada. 0 = todos.',"
            . " '6', '0', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', NOW())");

        tep_db_query("INSERT INTO " . TABLE_CONFIGURATION
            . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)"
            . " VALUES ('Estado del pedido tras pagar', 'MODULE_PAYMENT_PAYPAL_APPLEPAY_ORDER_STATUS_ID', '0',"
            . " 'Estado al que pasan los pedidos pagados con Apple Pay.',"
            . " '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', NOW())");

        tep_db_query("INSERT INTO " . TABLE_CONFIGURATION
            . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)"
            . " VALUES ('Orden de visualizacion', 'MODULE_PAYMENT_PAYPAL_APPLEPAY_SORT_ORDER', '7',"
            . " 'Orden de aparicion en checkout (menor = antes).',"
            . " '6', '0', NOW())");
    }

    public function remove() {
        tep_db_query("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ('" . implode("', '", $this->keys()) . "')");
    }

    public function keys() {
        return array(
            'MODULE_PAYMENT_PAYPAL_APPLEPAY_STATUS',
            'MODULE_PAYMENT_PAYPAL_APPLEPAY_ZONE',
            'MODULE_PAYMENT_PAYPAL_APPLEPAY_ORDER_STATUS_ID',
            'MODULE_PAYMENT_PAYPAL_APPLEPAY_SORT_ORDER',
        );
    }
}
