<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2010 osCommerce

  Released under the GNU General Public License
*/
// Allow only orders in status
$allowed_statuses = array(
    'Pending',
    'Pendiente',
);

require('includes/application_top.php');
require(DIR_WS_CLASSES . 'order.php');
require_once(DIR_FS_CATALOG . DIR_WS_CLASSES . 'payment.php');
require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/sequra.php');
if (!defined('DIR_FS_SEQURA')) {
	define('DIR_FS_SEQURA', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/SeQura/');
}
$charset = strtolower(CHARSET);
define('ISUTF8', $charset == 'utf8' || $charset == 'utf-8');

include_once(DIR_FS_CATALOG . 'includes/compat/compatibility_functions.php');
require_once(DIR_FS_SEQURA . 'SequraHelper.php');

$oID = isset($_REQUEST['oID'])?(int)$_REQUEST['oID']:false;

// if there is no order id
if (!$oID) {
    SequraHelper::forbid();
}

/* #FB-SEQURA-SIG
   Cualquier POST a este endpoint es una notificacion de pago. La firma es
   OBLIGATORIA y se comprueba AQUI: antes de cargar el pedido y antes del
   include. Si no valida no se toca la base de datos ni se revela nada.
   La firma ata sid|oID|importe|ts (SequraHelper::verifyPay), asi que un par
   valido ya no sirve para confirmar otro pedido ni otro importe: antes solo
   cubria el sid, que es el de la sesion del propio atacante. */
$fb_is_notification = (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST')
    || isset($_POST['signature']);
if ($fb_is_notification && !SequraHelper::verifyPay($oID)) {
    SequraHelper::forbid();
}

//Get the order and recreate cart from it
$order = new order($oID);
if (!$order ) {
    SequraHelper::forbid();
}

if (!in_array($order->info['orders_status'], $allowed_statuses)) {
    SequraHelper::forbid();
}
$payment = 'sequra_pp';
if (isset($_GET['payment'])) $payment = $_GET['payment'];
// load the selected payment module
$payment_modules = new payment($payment);

$builder = SequraHelper::getBuilder();
$client = SequraHelper::getClient();
$_SESSION['cartID'] = $oID;

function split_name(&$addr){
    $i = strpos($addr['name'],' ',3);
    $addr['firstname'] = substr($addr['name'],0,$i++);
    $addr['lastname'] = substr($addr['name'],$i);
}

split_name($order->delivery);
split_name($order->billing);
split_name($order->customer);

// Add shipping_costs
foreach($order->totals as $ot){
    if(strpos($ot['title'],$order->info['shipping_method'])!==false){
        $order->info['shipping_cost'] = preg_replace("/[^0-9]/", "", $ot['text'])/100;
    }
}

// if the customer is not logged on, redirect them to the login page
if (!$fb_is_notification) {
    if (!tep_session_is_registered('customer_id')) {
        $navigation->set_snapshot(array('mode' => 'SSL', 'page' => 'pay-with-sequra.php?oID='.$oID));
        tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
    }
    /* Antes esto imprimia un aviso y SEGUIA ejecutando: sin exit no era una
       comprobacion, era un cartel. */
    if ($customer_id != $order->customer['id']) {
        SequraHelper::forbid();
    }
}

/* #FB-SEQURA-SIG El include va DESPUES del login y de la comprobacion de
   propiedad. En la notificacion firmada no hay sesion de cliente, asi que
   alli la propiedad se comprueba dentro del include: el order_ref tiene que
   pertenecer a una solicitud creada por el dueno del pedido. */
if ($fb_is_notification) {
    require('ipn-pay-with-sequra.php');
    /* Antes se seguia hasta el final del fichero y se lanzaba una solicitud
       NUEVA a SeQura (mas una fila en `sequra`) en cada notificacion. */
    header('Content-Type: text/plain; charset=utf-8');
    echo 'ok';
    require(DIR_WS_INCLUDES . 'application_bottom.php');
    exit;
}

$data = $builder->build();

// Fix data built in Builder
$fb_ts  = time();
/* Importe real en centimos (orders_total.value, NO el texto formateado que
   order.php mete en info['total'] al cargar de la BD). */
$fb_amt = SequraHelper::orderAmountCents($order);
if ($fb_amt < 0) {
    SequraHelper::forbid();
}
$data['merchant']['proactive'] = '1';
$data['merchant']['notification_parameters']['oID'] = (string)$oID;
$data['merchant']['notification_parameters']['amt'] = (string)$fb_amt;
$data['merchant']['notification_parameters']['ts'] = (string)$fb_ts;
$data['merchant']['notification_parameters']['signature'] = SequraHelper::signPay(tep_session_id(), $oID, $fb_amt, $fb_ts);
$data['merchant']['notify_url'] = str_replace('ipn-sequra.php','pay-with-sequra.php',$data['merchant']['notify_url']);

$client->startSolicitation($data);
if ($client->succeeded()) {
    $uri                   = $client->getOrderUri();
    $paymentdata = array(
        /* $amount no existia en este fichero: la fila se grababa con 0. */
        'amount' => $fb_amt,
        'serialized_order' => urlencode(serialize($order)),
        'uri' => $uri,
        'customer_id' => $customer_id
    );
    tep_db_perform('sequra', $paymentdata);
    $options               = array('product' => 'pp3');
    $vars['identity_form'] = $client->getIdentificationForm($uri, $options);
    if (!ISUTF8) {
    $vars['identity_form'] = mb_convert_encoding($vars['identity_form'] ?? '', 'ISO-8859-1', 'UTF-8');
    }
    $_SESSION['SeQuraURI'] = $uri;
    $process_button_string = SequraHelper::render('form', $vars);
}?>
<html>
  <body>
    <?php echo $vars['identity_form'];?>
    <script type="text/javascript">
    (function(){
        var sequraCallbackFunction = function() {
          history.back()
        };
        window.SequraFormInstance.setCloseCallback(sequraCallbackFunction);
        window.SequraFormInstance.show();
    })();
</script>
  </body>
</html>
<?php
require(DIR_WS_INCLUDES . 'application_bottom.php');
