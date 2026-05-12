<?php

// Librerias
use Checkout\Box;
use Checkout\classes\Router;
use util\tools;
use util\event;

// Pasos de la barra de progreso
define('STEP_BAR_CART', 1);
define('STEP_BAR_SHIPPING', 2);
define('STEP_BAR_PAYMENT', 3);
define('STEP_BAR_CONFIRMATION', 4);
define('STEP_BAR_SUCCESS', 5);

// Incluimos
include 'class/router.php';

// Rutas
$routes = array(
	// Carrito
    'checkout/cart' => array('cart@index', 'shopping_cart.php'),
    'checkout/cart/coupon' => array('cart@coupon', 'checkout_cart_coupon.php'),
    'checkout/cart/change_quantity' => array('cart@changeQuantity', 'checkout_cart_change_quantity.php'),
    'checkout/cart/clean' => array('cart@clean', 'checkout_cart_clean.php'),
    'checkout/cart/repeat_order' => array('cart@repeatOrder', 'checkout_cart_repeat_order.php'),
	'checkout/cart/modified' => array('cart@modified', 'checkout_cart_modified.php'),

    // Shipping
    'checkout/shipping' => array('shipping@index', 'checkout_shipping.php'),
    'checkout/shipping/process' => array('shipping@process', 'checkout_shipping_process.php'),
    'checkout/shipping/select_zone' => array('shipping@selectZone', 'checkout_shipping_select_zone.php'),
    'checkout/shipping/select_address' => array('shipping@selectAddressShipping', 'checkout_shipping_address.php'),
    'checkout/shipping/get-address-text' => array('shipping@getAddressText', 'checkout_shipping_store_text.php'),
    // Payment
	'checkout/payment' => array('payment@index', 'checkout_payment.php'),
    'checkout/payment/process' => array('payment@process', 'checkout_payment_process.php'),
    'checkout/payment/select_address' => array('payment@selectAddressPayment', 'checkout_payment_address.php'),
    // Confirmation
	'checkout/confirmation' => array('confirmation@index', 'checkout_confirmation.php'),
    'checkout/confirmation/payment_ext' => array('confirmation@paymentExt', 'checkout_payment_ext.php'),
    // Process
	'checkout/process' => array('process@index', 'checkout_process.php'),
    // Success
	'checkout/success' => array('success@index', 'checkout_success.php'),
);

// Router
$router = new Router($routes);

// Cambiamos el directorio hacia el directorio principal
chdir('../../..');

// Inicio de la aplicación
include 'includes/application_top.php';

// Eror 404
if ($router->error404) {
    include 'includes/modules/404/index.php';
    exit();
}

saveShippingEstimator();

// Idioma
include DIR_WS_LANGUAGES . $language . '/checkout.php';

// Variables
$sPathModule = DIR_WS_MODULES . 'checkout';
$sPathTemplate = $sPathModule . '/template';
$checkoutDifferentPage = CHECKOUT_DIFFERENT_PAGE == 'true' ? true : false;

// Boxes
include $sPathModule . '/box.php';
$boxCheckout = new Box();

// Añadimos css
event::getInstance()->add('header_add_meta', function () {global $sPathModule;echo '<link rel="stylesheet" type="text/css" href="' . $sPathModule . '/css/style.css">';});

// Añadimos js
event::getInstance()->add('front_office_footer_after_scripts', function () {global $sPathModule;return '<script src="' . $sPathModule . '/js/javascript.js" type="text/javascript"></script>';});

// Llamamos
$htmlCheckout = $router->execute();

// Si es ajax pintamos directamente el módulo
if (isAjax()) {
    echo $htmlCheckout;
} else {
    // Obtenemos step
    $nStep = constant('STEP_BAR_' . strtoupper($router->controller));

    // Pintamos
    echo tools::includeTemplate($sPathTemplate . '/base.php');
}

// Fin de aplicación
include DIR_WS_INCLUDES . 'application_bottom.php';
