<?php
/**
 * includes/languages/espanol/modules/payment/paypal_rest.php
 *
 * NOTA: public_title se mantiene como TEXTO PLANO porque LC/osCommerce persiste
 * este valor en orders.payment_method (ver classes/order.php). Si metemos HTML
 * (iconos inline) el admin de pedidos muestra el markup literal en la columna.
 * El icono ya esta en includes/modules/checkout/images/payment_paypal_rest.png
 * y puede inyectarse en el selector del checkout via theme CSS si se desea.
 */

define('MODULE_PAYMENT_PAYPAL_REST_TEXT_TITLE',         'PayPal + Pay Later');
define('MODULE_PAYMENT_PAYPAL_REST_TEXT_PUBLIC_TITLE',  'PayPal o paga en 3 plazos sin intereses');
define('MODULE_PAYMENT_PAYPAL_REST_TEXT_DESCRIPTION',   'Paga con tu cuenta PayPal o en 3 plazos sin intereses con Pay Later. Procesado por PayPal Checkout v2 (REST).');
define('MODULE_PAYMENT_PAYPAL_REST_TEXT_COMMENTS',      'Comentarios');
define('MODULE_PAYMENT_PAYPAL_REST_TEXT_ERROR_GENERIC', 'No se ha podido completar el pago con PayPal. Vuelve a intentarlo o elige otro método de pago.');
define('MODULE_PAYMENT_PAYPAL_REST_TEXT_ERROR_CANCEL',  'Has cancelado el pago con PayPal.');
define('MODULE_PAYMENT_PAYPAL_REST_TEXT_BUTTON_HINT',   'Pulsa uno de los botones para completar el pago con PayPal:');
