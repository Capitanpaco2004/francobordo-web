<?php
/**
 * paypal_rest.php
 *
 * Modulo de pago: PayPal + Pay Later (Pago a plazos).
 *
 * PayPal Smart Buttons renderiza dos botones bajo la misma cuenta:
 *   - "Pay with PayPal" (boton amarillo clasico)
 *   - "Pay Later" (azul, 3 plazos sin intereses) — si esta activado y elegible
 *
 * Este modulo aloja las credenciales REST compartidas con paypal_googlepay
 * y paypal_applepay (todos usan la misma app PayPal Business).
 *
 * Coexiste con paypal_express.php (legacy NVP). Activar uno u otro desde admin.
 *
 * Flujo:
 *   1. Cliente elige "PayPal o Pay Later" en checkout_payment.
 *   2. process_button() renderiza el SDK y los Smart Buttons.
 *   3. Clic → SDK abre popup, cliente aprueba → JS llama create/capture-order.php.
 *   4. JS rellena hidden y submitea form → before_process() re-verifica.
 */

class paypal_rest {
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

        $this->signature    = 'paypal|paypal_rest|1.1|2.0';
        $this->code         = 'paypal_rest';
        $this->title        = defined('MODULE_PAYMENT_PAYPAL_REST_TEXT_TITLE')        ? MODULE_PAYMENT_PAYPAL_REST_TEXT_TITLE        : '';
        $this->public_title = defined('MODULE_PAYMENT_PAYPAL_REST_TEXT_PUBLIC_TITLE') ? MODULE_PAYMENT_PAYPAL_REST_TEXT_PUBLIC_TITLE : '';
        $this->description  = defined('MODULE_PAYMENT_PAYPAL_REST_TEXT_DESCRIPTION')  ? MODULE_PAYMENT_PAYPAL_REST_TEXT_DESCRIPTION  : '';
        $this->sort_order   = defined('MODULE_PAYMENT_PAYPAL_REST_SORT_ORDER')        ? MODULE_PAYMENT_PAYPAL_REST_SORT_ORDER        : '';
        $this->enabled      = ( defined('MODULE_PAYMENT_PAYPAL_REST_STATUS') && MODULE_PAYMENT_PAYPAL_REST_STATUS == 'True' );

        if ( defined('MODULE_PAYMENT_PAYPAL_REST_ORDER_STATUS_ID') && (int)MODULE_PAYMENT_PAYPAL_REST_ORDER_STATUS_ID > 0 ) {
            $this->order_status = MODULE_PAYMENT_PAYPAL_REST_ORDER_STATUS_ID;
        }

        if ( is_object($order) ) {
            $this->update_status();
        }
    }

    public function update_status() {
        global $order;

        if ( $this->enabled === true && defined('MODULE_PAYMENT_PAYPAL_REST_ZONE') && (int)MODULE_PAYMENT_PAYPAL_REST_ZONE > 0 ) {
            $check_flag = false;
            $check_query = tep_db_query(
                "SELECT zone_id FROM " . TABLE_ZONES_TO_GEO_ZONES
                . " WHERE geo_zone_id = '" . (int)MODULE_PAYMENT_PAYPAL_REST_ZONE . "'"
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

        $aEnable  = array();
        $aDisable = array('card'); // tarjeta via PayPal no (lo cobra Redsys). 'credit' quitado a peticion de PayPal (bloqueaba Pay Later)
        if ( defined('MODULE_PAYMENT_PAYPAL_REST_ENABLE_PAYLATER') && MODULE_PAYMENT_PAYPAL_REST_ENABLE_PAYLATER === 'True' ) $aEnable[] = 'paylater';

        $aSdkParams = array(
            'client-id'  => $sClientId,
            'currency'   => 'EUR',
            'intent'     => $sIntent,
            'components' => 'buttons',
        );
        if ( ! empty($aEnable) )  $aSdkParams['enable-funding']  = implode(',', $aEnable);
        if ( ! empty($aDisable) ) $aSdkParams['disable-funding'] = implode(',', $aDisable);
        $sSdkUrl = 'https://www.paypal.com/sdk/js?' . http_build_query($aSdkParams);

        $sErrorBanner = '';
        if ( $sClientId === '' ) {
            $sErrorBanner = '<div style="background:#fff5f5;border-left:3px solid #a00;padding:10px 14px;color:#a00;margin:10px 0;">'
                          . '<strong>PayPal Checkout no esta configurado.</strong> Falta Client ID en admin → Modulos → Pago → paypal_rest.'
                          . '</div>';
        }

        ob_start();
        ?>
        <?php echo $sErrorBanner; ?>
        <?php echo tep_draw_hidden_field('paypal_rest_order_id', ''); ?>
        <?php echo tep_draw_hidden_field('paypal_rest_capture_id', ''); ?>
        <?php echo tep_draw_hidden_field('paypal_rest_method', 'paypal'); ?>
        <div id="paypal-rest-buttons-wrap" style="max-width:640px;margin:14px 0;">
            <div id="paypal-rest-status" style="
                display:flex;align-items:center;gap:10px;
                font-size:14.5px;color:#003087;font-weight:600;
                padding:10px 14px;margin:0 0 12px;
                background:#f0f6fc;border-left:3px solid #009cde;border-radius:4px;
                line-height:1.4;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="#009cde" style="flex-shrink:0;">
                    <path d="M12 2 C 6.48 2 2 6.48 2 12 c 0 5.52 4.48 10 10 10 s 10 -4.48 10 -10 C 22 6.48 17.52 2 12 2 Z m 1 15 h -2 v -6 h 2 v 6 z m 0 -8 h -2 V 7 h 2 v 2 z"/>
                </svg>
                <span><?php echo defined('MODULE_PAYMENT_PAYPAL_REST_TEXT_BUTTON_HINT') ? MODULE_PAYMENT_PAYPAL_REST_TEXT_BUTTON_HINT : 'Pulsa uno de los botones para completar el pago:'; ?></span>
            </div>
            <div id="paypal-rest-buttons" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-start;"></div>
            <style>
                /* Cada boton PayPal (iframe) ocupa mitad del ancho y se apila en movil */
                #paypal-rest-buttons > div { flex:1 1 240px; min-width:240px; }
            </style>
            <div id="paypal-rest-error" style="display:none;background:#fff5f5;border-left:3px solid #a00;padding:8px 12px;color:#a00;font-size:13px;margin-top:10px;"></div>
        </div>
        <?php if ( $sClientId !== '' ): ?>
        <script src="<?php echo htmlspecialchars($sSdkUrl, ENT_QUOTES, 'UTF-8'); ?>" data-namespace="paypal_rest_sdk"></script>
        <script>
        (function(){
            function $(id){return document.getElementById(id);}
            function hideDefaultSubmit(){var b=$('checkoutShippingButton');if(b)b.style.display='none';}
            function showError(m){var e=$('paypal-rest-error');if(e){e.textContent=m;e.style.display='block';}}
            function getForm(){return document.querySelector('form[action*="checkout_process"]')||(document.querySelector('input[name="paypal_rest_order_id"]')||{}).form;}
            function whenReady(cb){
                if(window.paypal_rest_sdk&&window.paypal_rest_sdk.Buttons)return cb();
                var n=0,t=setInterval(function(){
                    if(window.paypal_rest_sdk&&window.paypal_rest_sdk.Buttons){clearInterval(t);cb();}
                    else if(++n>200){clearInterval(t);showError('No se ha podido cargar el SDK de PayPal.');}
                },50);
            }
            whenReady(function(){
                hideDefaultSubmit();
                var sdk = window.paypal_rest_sdk;

                // Handlers compartidos por todos los funding sources
                var commonHandlers = {
                    createOrder: function(){
                        return fetch('ext/modules/payment/paypal_rest/create-order.php',{
                            method:'POST',credentials:'same-origin',
                            headers:{'Content-Type':'application/json'},body:'{}'
                        }).then(function(r){return r.json();}).then(function(d){
                            if(!d||!d.id)throw new Error(d&&d.error?d.error+(d.detail?': '+d.detail:''):'create-order sin id');
                            return d.id;
                        });
                    },
                    onApprove: function(data){
                        var f=getForm();
                        return fetch('ext/modules/payment/paypal_rest/capture-order.php',{
                            method:'POST',credentials:'same-origin',
                            headers:{'Content-Type':'application/json'},
                            body:JSON.stringify({order_id:data.orderID})
                        }).then(function(r){return r.json();}).then(function(d){
                            if(!d||!d.ok)throw new Error(d&&d.error?d.error+(d.detail?': '+d.detail:''):'capture-order fallida');
                            var h1=document.querySelector('input[name="paypal_rest_order_id"]');
                            var h2=document.querySelector('input[name="paypal_rest_capture_id"]');
                            if(h1)h1.value=d.order_id;if(h2)h2.value=d.capture_id;
                            if(f)f.submit();else window.location.href='checkout_process.php';
                        });
                    },
                    onError: function(err){showError('Error en el pago: '+(err&&err.message?err.message:err));},
                    onCancel: function(){showError('Has cancelado el pago. Puedes intentarlo de nuevo.');}
                };

                // Renderizamos cada funding source por separado (con isEligible() check)
                // para que se vean apilados PayPal amarillo + Pay Later azul + lo que aplique.
                var fundingSources = [sdk.FUNDING.PAYPAL, sdk.FUNDING.PAYLATER];
                var rendered = 0;
                fundingSources.forEach(function(fundingSource, idx){
                    var button = sdk.Buttons({
                        fundingSource: fundingSource,
                        style: { layout: 'vertical', shape: 'rect', tagline: false, height: 48 },
                        createOrder: commonHandlers.createOrder,
                        onApprove:   commonHandlers.onApprove,
                        onError:     commonHandlers.onError,
                        onCancel:    commonHandlers.onCancel
                    });
                    if (!button.isEligible()) return;
                    rendered++;
                    button.render('#paypal-rest-buttons').catch(function(err){
                        showError('No se ha podido renderizar el boton ' + String(fundingSource) + ': ' + err);
                    });
                });

                if (rendered === 0) {
                    showError('No hay metodos de pago PayPal disponibles para este pedido. Por favor elige otro metodo.');
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
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode('No se ha completado el pago con PayPal'), 'SSL'));
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
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode('El pago con PayPal no se ha completado (estado: ' . $sStatus . ')'), 'SSL'));
        }

        // Reconciliacion de importe: el pago de PayPal debe coincidir con el total
        // del pedido (calculado en servidor). Evita pagar de menos manipulando el carrito.
        if ( ! \PayPalRest\Client::verifyCapturedAmount($aOrder, tep_round($order->info['total'], 2), 'EUR') ) {
            @error_log('[paypal_rest] importe NO coincide order=' . $sOrderId . ' total_esperado=' . tep_round($order->info['total'], 2));
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode('El importe del pago no coincide con el del pedido. La compra no se ha completado.'), 'SSL'));
        }

        $this->transaction_id = $sCaptureId;

        tep_db_perform('redsys_payment_movements', array(
            'reference'    => substr($sOrderId, 0, 20),
            'value'        => tep_round($order->info['total'], 2),
            'customer_id'  => (int)$customer_id,
            'module'       => 'paypal_rest',
            'date_created' => 'now()',
        ));
    }

    public function after_process() {
        global $insert_id, $customer_id;

        tep_db_query(
            "UPDATE orders SET paypal_transaction_id = '" . tep_db_input($this->transaction_id) . "'"
            . " WHERE orders_id = '" . (int)$insert_id . "'"
        );
        tep_db_query(sprintf(
            'UPDATE redsys_payment_movements SET orders_id = %d'
            . ' WHERE customer_id = %d AND module = "paypal_rest" AND reference = "%s" AND orders_id = 0',
            (int)$insert_id,
            (int)$customer_id,
            tep_db_input(substr((string)($_POST['paypal_rest_order_id'] ?? ''), 0, 20))
        ));
    }

    public function get_error() { return false; }

    public function check() {
        if ( ! isset($this->_check) ) {
            $check_query = tep_db_query(
                "SELECT configuration_value FROM " . TABLE_CONFIGURATION
                . " WHERE configuration_key = 'MODULE_PAYMENT_PAYPAL_REST_STATUS'"
            );
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    public function install() {
        // Cleanup defensivo: si ya existian keys de una version anterior, las borramos
        tep_db_query("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ('"
            . "MODULE_PAYMENT_PAYPAL_REST_STATUS','MODULE_PAYMENT_PAYPAL_REST_CLIENT_ID',"
            . "'MODULE_PAYMENT_PAYPAL_REST_SECRET','MODULE_PAYMENT_PAYPAL_REST_ENVIRONMENT',"
            . "'MODULE_PAYMENT_PAYPAL_REST_ENABLE_PAYPAL','MODULE_PAYMENT_PAYPAL_REST_ENABLE_PAYLATER',"
            . "'MODULE_PAYMENT_PAYPAL_REST_ENABLE_CARD','MODULE_PAYMENT_PAYPAL_REST_ENABLE_GOOGLEPAY',"
            . "'MODULE_PAYMENT_PAYPAL_REST_ENABLE_APPLEPAY','MODULE_PAYMENT_PAYPAL_REST_INTENT',"
            . "'MODULE_PAYMENT_PAYPAL_REST_ZONE','MODULE_PAYMENT_PAYPAL_REST_ORDER_STATUS_ID',"
            . "'MODULE_PAYMENT_PAYPAL_REST_SORT_ORDER','MODULE_PAYMENT_PAYPAL_REST_DEBUG')");

        $aKeys = array(
            array("Activar PayPal + Pay Later", "MODULE_PAYMENT_PAYPAL_REST_STATUS", "False",
                "¿Aceptar pagos con PayPal Checkout REST (cuenta PayPal y pago a plazos)? Coexiste con paypal_express (NVP legacy).",
                "tep_cfg_select_option(array('True', 'False'), "),
            array("Client ID (PayPal REST)", "MODULE_PAYMENT_PAYPAL_REST_CLIENT_ID", "",
                "Client ID de la app REST en developer.paypal.com. Compartido con paypal_googlepay y paypal_applepay.", null),
            array("Secret (PayPal REST)", "MODULE_PAYMENT_PAYPAL_REST_SECRET", "",
                "Secret de la app REST. NO se filtra al navegador. Compartido con paypal_googlepay y paypal_applepay.", null),
            array("Entorno", "MODULE_PAYMENT_PAYPAL_REST_ENVIRONMENT", "sandbox",
                "Sandbox para pruebas, Live para produccion. Recordatorio: las credenciales deben coincidir con el entorno.",
                "tep_cfg_select_option(array('sandbox', 'live'), "),
            array("Mostrar boton Pay Later (pago a plazos)", "MODULE_PAYMENT_PAYPAL_REST_ENABLE_PAYLATER", "True",
                "Si esta a True, ademas del boton PayPal amarillo, se muestra el azul 'Paga en 3 plazos sin intereses'. Solo aparece si el importe es elegible.",
                "tep_cfg_select_option(array('True', 'False'), "),
            array("Modo de transaccion", "MODULE_PAYMENT_PAYPAL_REST_INTENT", "CAPTURE",
                "CAPTURE = cobro inmediato. AUTHORIZE = retiene importe, capturas manualmente (max 29 dias).",
                "tep_cfg_select_option(array('CAPTURE', 'AUTHORIZE'), "),
            array("Zona de pago", "MODULE_PAYMENT_PAYPAL_REST_ZONE", "0",
                "Solo ofrecer este metodo a clientes de la zona seleccionada. 0 = todos.",
                null, "tep_get_zone_class_title", "tep_cfg_pull_down_zone_classes("),
            array("Estado del pedido tras pagar", "MODULE_PAYMENT_PAYPAL_REST_ORDER_STATUS_ID", "0",
                "Estado al que pasan los pedidos pagados. 0 = por defecto.",
                "tep_cfg_pull_down_order_statuses(", "tep_get_order_status_name", null),
            array("Orden de visualizacion", "MODULE_PAYMENT_PAYPAL_REST_SORT_ORDER", "5",
                "Orden de aparicion en checkout (menor = antes).", null),
            array("Debug (log API requests)", "MODULE_PAYMENT_PAYPAL_REST_DEBUG", "False",
                "Si esta activo, registra cada llamada REST en error_log. Desactivar en produccion.",
                "tep_cfg_select_option(array('True', 'False'), "),
        );

        foreach ( $aKeys as $a ) {
            $sTitle = $a[0]; $sKey = $a[1]; $sValue = $a[2]; $sDesc = $a[3];
            $sSetFn  = isset($a[4]) ? $a[4] : null;
            $sUseFn  = isset($a[5]) ? $a[5] : null;
            $sSetFn2 = isset($a[6]) ? $a[6] : null;

            if ( $sSetFn2 !== null ) {
                tep_db_query("INSERT INTO " . TABLE_CONFIGURATION
                    . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added)"
                    . " VALUES ('" . tep_db_input($sTitle) . "', '" . tep_db_input($sKey) . "', '" . tep_db_input($sValue) . "', '" . tep_db_input($sDesc) . "', '6', '0',"
                    . " " . ( $sUseFn ? "'" . tep_db_input($sUseFn) . "'" : "''" ) . ","
                    . " '" . tep_db_input($sSetFn2) . "', NOW())");
            } elseif ( $sSetFn ) {
                tep_db_query("INSERT INTO " . TABLE_CONFIGURATION
                    . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)"
                    . " VALUES ('" . tep_db_input($sTitle) . "', '" . tep_db_input($sKey) . "', '" . tep_db_input($sValue) . "', '" . tep_db_input($sDesc) . "', '6', '0',"
                    . " '" . tep_db_input($sSetFn) . "', NOW())");
            } else {
                tep_db_query("INSERT INTO " . TABLE_CONFIGURATION
                    . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)"
                    . " VALUES ('" . tep_db_input($sTitle) . "', '" . tep_db_input($sKey) . "', '" . tep_db_input($sValue) . "', '" . tep_db_input($sDesc) . "', '6', '0', NOW())");
            }
        }
    }

    public function remove() {
        tep_db_query("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ('" . implode("', '", $this->keys()) . "')");
    }

    public function keys() {
        return array(
            'MODULE_PAYMENT_PAYPAL_REST_STATUS',
            'MODULE_PAYMENT_PAYPAL_REST_CLIENT_ID',
            'MODULE_PAYMENT_PAYPAL_REST_SECRET',
            'MODULE_PAYMENT_PAYPAL_REST_ENVIRONMENT',
            'MODULE_PAYMENT_PAYPAL_REST_ENABLE_PAYLATER',
            'MODULE_PAYMENT_PAYPAL_REST_INTENT',
            'MODULE_PAYMENT_PAYPAL_REST_ZONE',
            'MODULE_PAYMENT_PAYPAL_REST_ORDER_STATUS_ID',
            'MODULE_PAYMENT_PAYPAL_REST_SORT_ORDER',
            'MODULE_PAYMENT_PAYPAL_REST_DEBUG',
        );
    }
}
