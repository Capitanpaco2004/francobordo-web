<?php
/**Taken from checkout_confirmation.php */

require('includes/application_top.php');
require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CHECKOUT_CONFIRMATION);
require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CHECKOUT);
// if the customer is not logged on, redirect them to the login page
if (!tep_session_is_registered('customer_id')) {
	$navigation->set_snapshot(array('mode' => 'SSL', 'page' => FILENAME_CHECKOUT_PAYMENT));
	tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
}

// if there is nothing in the customers cart, redirect them to the shopping cart page
if ($cart->count_contents() < 1) {
	tep_redirect(tep_href_link(FILENAME_SHOPPING_CART));
}

// avoid hack attempts during the checkout procedure by checking the internal cartID
if (isset($cart->cartID) && tep_session_is_registered('cartID')) {
	if ($cart->cartID != $cartID) {
		tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
	}
}

// if no shipping method has been selected, redirect the customer to the shipping method selection page
if (!tep_session_is_registered('shipping')) {
	tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
}

if (!tep_session_is_registered('payment')) tep_session_register('payment');
if (isset($_POST['payment'])) $payment = $_POST['payment'];

if (!tep_session_is_registered('comments')) tep_session_register('comments');
if (tep_not_null($_POST['comments'])) {
	$comments = tep_db_prepare_input($_POST['comments']);
}

//kgt - discount coupons
if (!tep_session_is_registered('coupon')) tep_session_register('coupon');
//this needs to be set before the order object is created, but we must process it after
$coupon = tep_db_prepare_input($_POST['coupon']);
//end kgt - discount coupons

//travelclub
if(isset($_POST['travelc'])){
	if (!tep_session_is_registered('travelc')) tep_session_register('travelc');
	if (!tep_session_is_registered('travelc2')) tep_session_register('travelc2');
	//this needs to be set before the order object is created, but we must process it after
	$travelc = tep_db_prepare_input($_POST['travelc']);
	if((strlen($travelc)>0)&&(strlen($travelc)<9)) tep_redirect( tep_href_link( FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode( TRAVEL_ERROR ), 'SSL' ) ); //redirect to the shipping page to reselect the shipping method
}
//end travelclub

// load the selected payment module
require(DIR_WS_CLASSES . 'payment.php');
$payment_modules = new payment($payment);

require(DIR_WS_CLASSES . 'order.php');
$order = new order;

//// CHOQUE DE EXTENSIONES!!!! SACAMOS ESTO
//
//  require(DIR_WS_CLASSES . 'shipping.php');
//  $shipping_modules = new shipping($shipping);

$payment_modules->update_status();

if ( ( is_array($payment_modules->modules) && (sizeof($payment_modules->modules) > 1) && !is_object($$payment) ) || (is_object($$payment) && ($$payment->enabled == false)) ) {
	tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode(ERROR_NO_PAYMENT_MODULE_SELECTED), 'SSL'));
}

if (is_array($payment_modules->modules)) {
	$payment_modules->pre_confirmation_check();
}

//kgt - discount coupons
if( tep_not_null( $coupon ) && is_object( $order->coupon ) ) { //if they have entered something in the coupon field
	$order->coupon->verify_code();
	if( MODULE_ORDER_TOTAL_DISCOUNT_COUPON_DEBUG != 'true' ) {
		if( !$order->coupon->is_errors() ) { //if we have passed all tests (no error message), make sure we still meet free shipping requirements, if any
			if( $order->coupon->is_recalc_shipping() ) tep_redirect( tep_href_link( FILENAME_CHECKOUT_SHIPPING, 'error_message=' . urlencode( ENTRY_DISCOUNT_COUPON_SHIPPING_CALC_ERROR ), 'SSL' ) ); //redirect to the shipping page to reselect the shipping method
		} else {
			if( tep_session_is_registered('coupon') ) tep_session_unregister('coupon'); //remove the coupon from the session
			tep_redirect( tep_href_link( FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode( implode( ' ', $order->coupon->get_messages() ) ), 'SSL' ) ); //redirect to the payment page
		}
	}
} else { //if the coupon field is empty, unregister the coupon from the session
	if( tep_session_is_registered('coupon') ) { //we had a coupon entered before, so we need to unregister it
		tep_session_unregister('coupon');
		//now check to see if we need to recalculate shipping:
		require_once( DIR_WS_CLASSES.'discount_coupon.php' );
		if( discount_coupon::is_recalc_shipping() ) tep_redirect( tep_href_link( FILENAME_CHECKOUT_SHIPPING, 'error_message=' . urlencode( ENTRY_DISCOUNT_COUPON_SHIPPING_CALC_ERROR ), 'SSL' ) ); //redirect to the shipping page to reselect the shipping method
	}
}
//end kgt - discount coupons

// load the selected shipping module
require(DIR_WS_CLASSES . 'shipping.php');
$shipping_modules = new shipping($shipping);

require(DIR_WS_CLASSES . 'order_total.php');
$order_total_modules = new order_total;

// Stock Check
$any_out_of_stock = false;
if (STOCK_CHECK == 'true') {
//++++ QT Pro: Begin Changed code
	$check_stock='';
	for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
		if (isset($order->products[$i]['attributes']) && is_array($order->products[$i]['attributes'])) {
			$attributes=array();
			foreach ($order->products[$i]['attributes'] as $attribute) {
				$attributes[$attribute['option_id']]=$attribute['value_id'];
			}
			$check_stock[$i] = tep_check_stock($order->products[$i]['id'], $order->products[$i]['qty'], $attributes);
		} else {
			$check_stock[$i] = tep_check_stock($order->products[$i]['id'], $order->products[$i]['qty']);
		}
		if ($check_stock[$i]) {
			$any_out_of_stock = true;
		}
//++++ QT Pro: End Changed Code
	}
	// Out of Stock
	if ( (STOCK_ALLOW_CHECKOUT != 'true') && ($any_out_of_stock == true) ) {
		tep_redirect(tep_href_link(FILENAME_SHOPPING_CART));
	}
}

$breadcrumb->add(NAVBAR_TITLE_1, tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
$breadcrumb->add(NAVBAR_TITLE_2);

//Compruebo si se ha seleccionado el pago con tarjeta como metodo de pago y si es asi lo guardo en la tabla unicaja_intentos
$payment_method = $order->info['payment_method'];

if ($payment_method == 'Pagar con tarjeta'){
	tep_db_query("insert into unicaja_intentos (customers_id, importe, fecha) values (".$_SESSION['customer_id'].",".$order->info['total'].",now()) ");
}

if ($_GET['action'] == "checkAllProducts"){
	$success = "true";
	$errMsg = "";
	for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
		$sql = "SELECT * FROM " . TABLE_SPECIALS . " WHERE products_id = ". (int) tep_get_prid($order->products[$i]['id']) . " AND max_ofertas IS NOT NULL";
		$query_ofertas = tep_db_query($sql);
		if (tep_db_num_rows($query_ofertas)>0) {
			$datos_oferta = tep_db_fetch_array($query_ofertas);
			if ($datos_oferta['status'] != 0) {
				if ($datos_oferta['max_ofertas'] < $order->products[$i]['qty']) {
					$success = "false";
					$errMsg .= "-".sprintf(MAX_OFFER_ERROR, $datos_oferta['max_ofertas']);
				}
			}
		}

		$sql = "SELECT *
                FROM ". TABLE_PRODUCTS_TO_CATEGORIES ." p2c
                JOIN ". TABLE_PRODUCTS ." p
                    ON p2c.products_id = p.products_id
                WHERE p.products_id = ". (int) tep_get_prid($order->products[$i]['id']) . "
                    AND categories_id = '". CATEGORIA_OUTLET_PIENSOS ."'";
		$query_outlet = tep_db_query($sql);

		if (tep_db_num_rows($query_outlet)>0) {
			$datos_outlet = tep_db_fetch_array($query_outlet);
			if ($datos_outlet['status'] != 0) {
				if ($datos_outlet['products_quantity'] < $order->products[$i]['qty']) {
					$success = "false";
					$errMsg .= "-".sprintf(MAX_OFFER_ERROR, $datos_outlet['products_quantity']);
				}
			}
		}
		$stock_out = tep_db_query("SELECT * FROM products_out_stock pos JOIN products_description pd ON pos.products_id=pd.products_id WHERE pos.products_id = '". (int)tep_get_prid($order->products[$i]['id']) ."' AND language_id = ".$languages_id);
		if ($stock_out_data = tep_db_fetch_array($stock_out)) {
			$success = "false";
			$errMsg .= "-".sprintf(OUT_STOCK_ERROR, $stock_out_data['products_name']);
			$cart->remove($order->products[$i]['id']);
		}

		$sql = "SELECT * FROM ". TABLE_PRODUCTS ." p JOIN ". TABLE_PRODUCTS_DESCRIPTION ." pd ON p.products_id = pd.products_id WHERE p.products_id = '". (int) tep_get_prid($order->products[$i]['id'])."' AND language_id = ".$languages_id;
		$query_status  = tep_db_query($sql);
		if ($datos = tep_db_fetch_array($query_status)) {
			if ($datos['products_status'] == 0){
				$success = "false";
				$errMsg .= "-".sprintf(NOT_AVAIBLE_PRODUCT, $datos['products_name']);
				$cart->remove($order->products[$i]['id']);
			}
		}
	}
//    $mensaje = '{
//                    "success": "' . $success . '",
//                    "errMsg": "' . $errMsg . '"
//            }';
	$mensaje = $success ."||". $errMsg;
	echo $mensaje;
	die();
}
/**EOF Taken */
$name = 'sequra'.(isset($_SESSION['sequra_sufix'])?$_SESSION['sequra_sufix']:'');
require_once(DIR_WS_MODULES . 'payment/'.$name.'.php');
$sequra=new $name();
$content = $sequra->process_button();
?><html>
<head>
	<script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
</head>
<body>
	<?php echo $content;?>
</body>
</html>
