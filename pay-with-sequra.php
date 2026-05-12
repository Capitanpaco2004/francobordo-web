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
    die();
}  
//Get the order and recreate cart from it
$order = new order($oID);
if (!$order ) {
    die();
}

if (!in_array($order->info['orders_status'], $allowed_statuses)) {
    die('El pedido no está en el estado esperado');
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

if ( isset($_POST['signature'])) {
    require('ipn-pay-with-sequra.php');
}

// if the customer is not logged on, redirect them to the login page
if (!tep_session_is_registered('customer_id')) {
    $navigation->set_snapshot(array('mode' => 'SSL', 'page' => 'pay-with-sequra.php?oID='.$_REQUEST['oID']));
    tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
}
if($customer_id != $order->customer['id']) {
    ?><html>
        <body>
    No corresponde con el usuario del prespuesto <a ref="<?php echo tep_href_link(FILENAME_LOGIN, '', 'SSL');?>">volver</a>
        </body>
    </html>
    <?php
}
$data = $builder->build();

// Fix data built in Builder
$data['merchant']['proactive'] = '1';
$data['merchant']['notification_parameters']['oID'] = (string)$oID;
$data['merchant']['notify_url'] = str_replace('ipn-sequra.php','pay-with-sequra.php',$data['merchant']['notify_url']);

$client->startSolicitation($data);
if ($client->succeeded()) {
    $uri                   = $client->getOrderUri();
    $paymentdata = array(
        'amount' => (int)$amount,
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

