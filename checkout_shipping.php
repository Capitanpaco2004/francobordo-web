<?php
	require('includes/application_top.php');
	require('includes/classes/http_client.php');

	require(DIR_WS_LANGUAGES . $language . '/' . 'fast_account.php');

	// Errores PHP //

	// Reportamos todos los errores PHP
	error_reporting( 0 );
	ini_set( 'display_errors', 'Off' );

	// if the customer is not logged on, redirect them to the login page
	if (!tep_session_is_registered('customer_id'))
	{
		$navigation->set_snapshot();
		tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
	}

	// if there is nothing in the customers cart, redirect them to the shopping cart page
	if ($cart->count_contents() < 1)
	{
		tep_redirect(tep_href_link(FILENAME_SHOPPING_CART));
	}


	// Comprobamos si tenemos ZONE ID //
	// Comprobamos el ZONE ID
	$aZoneID = tep_db_query( 'SELECT * FROM address_book WHERE address_book_id = ' . $customer_default_address_id . ';' );
	$aZoneID = tep_db_fetch_array( $aZoneID );

	// Si no tenemos ZONE ID redireccionamos a select zone
	if( !array_key_exists( 'curl_oe', $_GET ) )
	{
		if( $aZoneID['entry_zone_id'] == 0 || $aZoneID['entry_zone_id'] == '' || ($aZoneID['entry_city_id'] == 0 && $aZoneID['entry_country_id'] == 195) )
			tep_redirect( FILENAME_CHECKOUT_SELECT_ZONE );
	}
	// FIN Comprobamos si tenemos ZONE ID //

	// BOF Separate Pricing Per Customer
	//  global variable (session): $sppc_customers_group_id -> local variable $customer_group_id

	if( isset($_SESSION['sppc_customer_group_id']) && $_SESSION['sppc_customer_group_id'] != '0' )
	{
    	$customer_group_id = $_SESSION['sppc_customer_group_id'];
  	}
	else
	{
    	$customer_group_id = '0';
  	}

	// if no shipping destination address was selected, use the customers own address as default
	if (!tep_session_is_registered('sendto'))
	{
		tep_session_register('sendto');
		$sendto = $customer_default_address_id;
	}
	else
	{
		if ( (is_array($sendto) && empty($sendto)) || is_numeric($sendto) )
		{
			// verify the selected shipping address
			$check_address_query = tep_db_query("select count(*) as total from " . TABLE_ADDRESS_BOOK . " where customers_id = '" . (int)$customer_id . "' and address_book_id = '" . (int)$sendto . "'");
			$check_address = tep_db_fetch_array($check_address_query);

			if ($check_address['total'] != '1')
			{
				$sendto = $customer_default_address_id;
				if (tep_session_is_registered('shipping')) tep_session_unregister('shipping');
			}
		}
	}

	// if no billing destination address was selected, use the customers own address as default
	if (!tep_session_is_registered('billto'))
	{
		tep_session_register('billto');
		$billto = $customer_default_address_id;
	}
	else
	{
		// verify the selected billing address
		$check_address_query = tep_db_query("select count(*) as total from " . TABLE_ADDRESS_BOOK . " where customers_id = '" . (int)$customer_id . "' and address_book_id = '" . (int)$billto . "'");
		$check_address = tep_db_fetch_array($check_address_query);

		if ($check_address['total'] != '1')
		{
			$billto = $customer_default_address_id;
if (tep_session_is_registered('payment')) tep_session_unregister('payment');
}
}

  //session to use for shipping insurance
  $_SESSION['choose_insurance'] = $_POST[choose_insurance];

//the next 4 lines are for ccgv
 require(DIR_WS_CLASSES . 'order_total.php');

$order_total_modules = new order_total;
/*$order_total_modules->collect_posts();
$order_total_modules->pre_confirmation_check(); */
// if the customer is not logged on, redirect them to the login page
if (!tep_session_is_registered('customer_id')) {
$navigation->set_snapshot();
tep_redirect(tep_href_link('create_account1.php', '', 'SSL'));
//tep_redirect(tep_href_link('create_account2.php', '', 'SSL'));
//tep_redirect(tep_href_link('create_account3.php', '', 'SSL'));
tep_redirect(tep_href_link(FILENAME_CREATE_ACCOUNT, '', 'SSL'));
}



require(DIR_WS_CLASSES . 'order.php');
$order = new order;
require(DIR_WS_CLASSES . 'payment.php');
$payment_modules = new payment;

$total_weight = $cart->show_weight();
$total_count = $cart->count_contents();
$total_ship_count = $cart->count_ship_contents(); // Free shipping per product 1.0

require(DIR_WS_CLASSES . 'shipping.php');
$shipping_modules = new shipping;

// if there is nothing in the customers cart, redirect them to the shopping cart page
if ($cart->count_contents() < 1) {
tep_redirect(tep_href_link(FILENAME_SHOPPING_CART));
}

// register a random ID in the session to check throughout the checkout procedure
// against alterations in the shopping cart contents
if (!tep_session_is_registered('cartID')) tep_session_register('cartID');
$cartID = $cart->cartID;


// if the order contains only virtual products, forward the customer to the billing page as
// a shipping address is not needed
if ($order->content_type == 'virtual') {
if (!tep_session_is_registered('shipping')) tep_session_register('shipping');
$shipping = false;
$sendto = false;
tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
}
tep_session_unregister('billing');
tep_session_unregister('payment');

if (!tep_session_is_registered('payment'))
	tep_session_register('payment');

if( isset($_POST['payment']) )
{
	$payment = $_POST['payment'];

	if( isset( $_POST['redsysc'] ) && $_POST['redsysc'] != -1 )
		$_SESSION['redsysc'] = $_POST['redsysc'];
	elseif( isset( $_POST['redsysc'] ) && $_POST['redsysc'] == -1 )
		$payment = 'redsys';
}

if($n==1){

if (isset($_POST['save_x'])){
$paynow=3;
}
if (isset($_POST['preview_x'])){
$paynow=5;
}

//i commented this out so payment is not required in this page and total can be accessed
/*if ( ( is_array($payment_modules->modules) && (sizeof($payment_modules->modules) > 1) && !is_object($$payment) ) || (is_object($$payment) && ($$payment->enabled == false)) ) {
tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, 'error_message=' . urlencode(ERROR_NO_PAYMENT_MODULE_SELECTED), 'SSL'));
*/
tep_session_unregister('payment');
$payment_modules->update_status();
}
if (is_array($payment_modules->modules)) {
$payment_modules->pre_confirmation_check();
}
//}

//Puntos por compra
if( isset($_POST['customer_shopping_points_spending']) && is_numeric($_POST['customer_shopping_points_spending']) && ($_POST['customer_shopping_points_spending'] > 0) )
{
	$customer_shopping_points_spending = false;

	if (tep_calc_shopping_pvalue($_POST['customer_shopping_points_spending']) < $order->info['total'] && !is_object($$payment) || (tep_get_shopping_points($customer_id) < $_POST['customer_shopping_points_spending']))
	{
		$customer_shopping_points_spending = false;
		tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, 'error_message=' . urlencode(REDEEM_SYSTEM_ERROR_POINTS_NOT), 'SSL'));
	}else{
		$customer_shopping_points_spending = $_POST['customer_shopping_points_spending'];
		if (!tep_session_is_registered('customer_shopping_points_spending')) tep_session_register('customer_shopping_points_spending');
	}
}

  //kgt - discount coupons
  if (!tep_session_is_registered('coupon')) tep_session_register('coupon');
  //this needs to be set before the order object is created, but we must process it after
  $coupon = tep_db_prepare_input($_POST['coupon']);
  // Si el cupon esta vacio lo eliminamos
  if( $coupon == '' )
  {
	unset( $coupon );
	tep_session_unregister( 'coupon' );
  }

foreach( $_POST as $key => $value )
{
tep_session_register($key);
}

$free_shipping = false;
$products_ship_free = false;
$free_pass = false;
$ship_free_count = 0;
if ((defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING') && MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING == 'true') || (defined('MODULE_ORDER_TOTAL_PRODUCTS_SHIP_FREE') && MODULE_ORDER_TOTAL_PRODUCTS_SHIP_FREE == 'true'))
	{

	switch (MODULE_ORDER_TOTAL_SHIPPING_DESTINATION)
		{
		case 'national': if ($order->delivery['country_id'] == STORE_COUNTRY) $free_pass = true;
		break;
		case 'international': if ($order->delivery['country_id'] != STORE_COUNTRY) $free_pass = true;
		break;
		case 'both': $free_pass = true;
		break;
		}
	if($free_pass == true)
		{
		if (defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING') && MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING == 'true' && $order->info['total'] >= ($customer_group_id == 0 ? MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER : MODULE_SHIPPING_FREEAMOUNT_AMOUNT_DISTRI))
			$free_shipping = true;
		elseif ( defined('MODULE_ORDER_TOTAL_PRODUCTS_SHIP_FREE') && (MODULE_ORDER_TOTAL_PRODUCTS_SHIP_FREE == 'true'))
			{
			$products = $cart->get_products();
			for ($i=0, $n=sizeof($products); $i<$n; $i++)
				{
				if ($products[$i]['ship_free'] == '1')
					{
					$ship_free_count += $products[$i]['quantity'];
					$total_weight -= $products[$i]['weight']*$products[$i]['quantity'];
					$total_count -= $products[$i]['quantity'];
					}
				}
			if ( $customer_group_id == 0 && $total_weight == 0 && $total_count == 0)
				{
				$products_ship_free = true;
				$free_shipping = true;
				}
			}
		if ($free_shipping == true || $products_ship_free == true || $ship_free_count > 0)
			include(DIR_WS_LANGUAGES . $language . '/modules/order_total/ot_shipping.php');
		}
	}

// process the selected shipping method
if ( isset($_POST['action']) && ($_POST['action'] == 'process') ) {
	if (!tep_session_is_registered('comments')) tep_session_register('comments');
	if (tep_not_null($_POST['comments'])) {
	$comments = tep_db_prepare_input($_POST['comments']);
	}

	// Inicio, tiendas
	if( tep_db_prepare_input($_POST['shipping']) == 'retira_retira' )
	{
		if (!tep_session_is_registered('store_id'))
		{
			tep_session_register('store_id');
			$store_id = tep_db_prepare_input($_POST['store_id']);
		}
	}
	else
	{
		unset( $store_id );
		tep_session_unregister('store_id');
	}
	// Fin, tiendas


	if (!tep_session_is_registered('shipping')) tep_session_register('shipping');

	if ( (tep_count_shipping_modules() > 0) || ($free_shipping == true) )
	{
		if ( (isset($_POST['shipping'])) && (strpos($_POST['shipping'], '_')) )
		{
			$shipping = $_POST['shipping'];

			list($module, $method) = explode('_', $shipping);
			if ( is_object($$module) || ($shipping == 'freeamount_freeamount') )
			{
				if ($shipping == 'freeamount_freeamount') {
					$quote[0]['methods'][0]['title'] = FREE_SHIPPING_TITLE;
					$quote[0]['methods'][0]['cost'] = '0';
				}
				else {
					$quote = $shipping_modules->quote($method, $module);
				}

				if (isset($quote['error'])) {
					tep_session_unregister('shipping');
				} else {
					if ( (isset($quote[0]['methods'][0]['title'])) && (isset($quote[0]['methods'][0]['cost'])) ) {

						if( $quote[0]['module'] == "Kiala" )
							$quote[0]['methods'][0]['title'] = 'Entrega en 24/48h horas en el Punto Kiala que elija';

						$shipping = array('id' => $shipping,
						'title' => (($free_shipping == true) ? $quote[0]['methods'][0]['title'] : $quote[0]['module']),
						'cost' => $quote[0]['methods'][0]['cost']);
					}

					tep_redirect(tep_href_link(FILENAME_CHECKOUT_CONFIRMATION,'', 'SSL'));
				}
			} else {
				tep_session_unregister('shipping');
			}
		}
		else
			$messageStack->add('no_shipping', TEXT_ERROR_SHIPPING, 'success');
	} else {
		$shipping = false;
		$messageStack->add_session('no_shipping', TEXT_ERROR_SHIPPING, 'success');
		tep_redirect(tep_href_link(FILENAME_CHECKOUT_CONFIRMATION, 'paynow='.$paynow, 'SSL'));
	}
}

// get all available shipping quotes
$quotes = $shipping_modules->quote();

/**
 * @author Daniel Lucia <daniel.lucia@denox.es>
 * A veces, por algún motivo no llegan transportistas. COn esto muestro al menos un error.
 */
$error_tarifa = false;
if (is_array($quotes) && count($quotes) == 1 && $quotes[0]['error'] != '' ) {
	$error_tarifa = $quotes[0]['error'];
	$messageStack->add_session('no_shipping', $quotes[0]['error'], 'error');
}

// Migracion preseleccion v2: sesiones existentes pueden tener shipping=retira_retira
// heredado de la antigua preseleccion automatica (cheapest = "Recoger en tienda").
// Si el cliente no esta enviando ahora mismo el formulario de envio, reseteamos una vez
// para que la nueva preseleccion (TIPSA por mensajeria) se aplique.
if (
	!isset($_SESSION['shipping_preselect_v2'])
	&& tep_session_is_registered('shipping')
	&& is_array($shipping)
	&& isset($shipping['id'])
	&& $shipping['id'] === 'retira_retira'
	&& !(isset($_POST['action']) && $_POST['action'] === 'process')
) {
	tep_session_unregister('shipping');
	$shipping = false;
}
$_SESSION['shipping_preselect_v2'] = 1;

// if no shipping method has been selected, automatically select the cheapest method.
// if the modules status was changed when none were available, to save on implementing
// a javascript force-selection method, also automatically select the cheapest shipping
// method if more than one module is now enabled
 if ( !tep_session_is_registered('shipping') || ( tep_session_is_registered('shipping') && ($shipping == false) && (tep_count_shipping_modules() > 1) ) ) $shipping = $shipping_modules->preselected();
require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CHECKOUT_SHIPPING);
require(DIR_WS_LANGUAGES . $language . '/' . 'checkout_payment.php');


$breadcrumb->add(NAVBAR_TITLE_1, tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
$breadcrumb->add(NAVBAR_TITLE_2, tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
?>
<?php
	require(DIR_THEME. 'html/header.php');
	echo '<script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.1/jquery.min.js"></script>';
	echo '<script>jQuery.noConflict();</script>';
?>
<script language="javascript"><!--

function ajaxLoader(url,id) {

  if (document.getElementById) {
    var x = (window.ActiveXObject) ? new ActiveXObject("Microsoft.XMLHTTP") : new XMLHttpRequest();
  }
  if (x) {

    x.onreadystatechange = function() {
	document.getElementById("contentLYR").innerHTML ='<img style="vertical-align:middle" src="images/loading.gif">Cargando, por favor espere...' ;
      if (x.readyState == 4 && x.status == 200) {
        el = document.getElementById(id);
   el.innerHTML ="";
        el.innerHTML = x.responseText;

      }
    }
    x.open("GET", url, true);

    x.send(null);

  }
}
var selected;


var Csid='<?php echo $osCsid; ?>';
var zprice='<?php echo $shipping['cost']; ?>';
var selected2;
function selectRowEffect2(object, buttonSelect) {
  if (!selected2) {
    if (document.getElementById) {
      selected2 = document.getElementById('defaultSelected');
    } else {
      selected2 = document.all['defaultSelected'];
    }
  }

  if (selected2) selected2.className = 'moduleRow';
  object.className = 'moduleRowSelected';
  selected2 = object;

// one button is not an array
  if (document.checkout_payment.shipping[0]) {
    document.checkout_payment.shipping[buttonSelect].checked=true;
  } else {
    document.checkout_payment.shipping.checked=true;
  }
}

function rowOverEffect(object) {
  if (object.className == 'moduleRow') object.className = 'moduleRowOver';
}

function rowOutEffect(object) {
  if (object.className == 'moduleRowOver') object.className = 'moduleRow';
}
//--></script>
<script language="javascript"><!--
/* Points/Rewards Module V2.1rc2a bof*/
var submitter = null;
function submitFunction() {
   submitter = 1;
   }
/* Points/Rewards Module V2.1rc2a eof*/
var selected;
function selectRowEffect(object, buttonSelect) {
  if (!selected) {
    if (document.getElementById) {
      selected = document.getElementById('defaultSelected');
    } else {
      selected = document.all['defaultSelected'];
    }
  }

  if (selected) selected.className = 'moduleRow';
  object.className = 'moduleRowSelected';
  selected = object;

// one button is not an array
  if (document.checkout_payment.payment[0]) {
    document.checkout_payment.payment[buttonSelect].checked=true;
  } else {
    document.checkout_payment.payment.checked=true;
  }

	// Vault de Paypal VZero
	if( document.getElementById("ppvz-cnt") )
		document.getElementById("ppvz-cnt").style.display = (document.checkout_payment.payment[buttonSelect].value == "paypal_vzero" ? "block" : "none");
}

function rowOverEffect(object) {
  if (object.className == 'moduleRow') object.className = 'moduleRowOver';
}

function rowOutEffect(object) {
  if (object.className == 'moduleRowOver') object.className = 'moduleRow';
}
//--></script>

<?php require(DIR_THEME. 'html/column_left.php'); ?>
<?php

 if ($messageStack->size('no_shipping') > 0) echo '<div class="mensaje">' . $messageStack->output('no_shipping'). '</div>'; ?>
<table border="0" width="100%" cellspacing="3" cellpadding="3">
  <tr>
    <td width="100%" valign="top"><?php echo tep_draw_form('checkout_payment', tep_href_link(FILENAME_CHECKOUT_SHIPPING, 'n=1', 'SSL'), 'post', 'class="fwgt checkFormWaiting"') . tep_draw_hidden_field('action', 'process'); ?><table border="0" width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
			<td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>
      </tr>
<?php
  if (isset($_GET['payment_error']) && is_object(${$_GET['payment_error']}) && ($error = ${$_GET['payment_error']}->get_error())) {
?>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
          <tr>
            <td class="main"><b><?php echo tep_output_string_protected($error['title']); ?></b></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td>
        <table border="0" width="100%" cellspacing="1" cellpadding="2" class="infoBoxNotice">
          <tr class="infoBoxNoticeContents">
            <td>
            <table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                <td class="main" width="100%" valign="top"><?php echo tep_output_string_protected($error['error']); ?></td>
                <td><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
              </tr>
            </table></td>
          </tr>
        </table>
       </td>
          </tr>


        </table>
<?php
  }
?>

	<tr>
		<td>
<?php
	//require('includes/fec/products_box.php');
	require('includes/fec/ajax_shipping.php');
	//require('includes/fec/ajax_shipping_prueba.php');

	$show_total = tep_db_prepare_input($_GET['show_total']);
//	if ($show_total ==1)          require('includes/fec/total_box.php');
?>
	</td>
</tr>
<?php
// BEGIN Shipping Insurance 2.0 with customer choice
if (($order->info['total'] >= MODULE_ORDER_TOTAL_INSURANCE_OVER) && (MODULE_ORDER_TOTAL_INSURANCE_STATUS == 'true') && (MODULE_ORDER_TOTAL_INSURANCE_USE == 'true')) {
?>
		<table width="100%" border="0" cellspacing="0" cellpadding="0">	<tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>
      </tr>
      <tr>
         <td class="main"><div class="pghd"><?php echo TEXT_SHIPPING_INSURANCE_TITLE; ?></div></td>
      </tr>
			<tr>
				<td><table border="0" width="100%" cellspacing="1" cellpadding="2" >
					<tr class="infoBoxContents">
						<td style="font-size: 13px;" class="main" width="100%" align="left"><input type="checkbox" name="choose_insurance" value="1" checked>&nbsp;&nbsp;&nbsp;<? echo TEXT_SHIPPING_INSURANCE_CHOICE; ?>&nbsp;&nbsp;<span class="smallText"><? echo TEXT_SHIPPING_INSURANCE_DISCLAIMER; ?></span></td>
					</tr>
				</table></td>
			</tr><tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>
      </tr>

<?php
}
// END Shipping Insurance 2.0 with customer choice
?>
<tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>
      </tr><noscript>
<?php	//  if ($show_total ==1)   require('total_box.php');?></noscript>
<?php	//require('includes/fec/address_box.php');?>
  <?php	require('includes/fec/payment_box.php');?>
<tr>
<?php


/* kgt - discount coupons */
	if( MODULE_ORDER_TOTAL_DISCOUNT_COUPON_STATUS == 'true' ) {
?>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
          <tr>
            <td class="main"></td>
          </tr>
        </table></td>
      </tr>

      <tr>
        <td><table border="0" width="100%" cellspacing="1" cellpadding="2" class="">
          <tr class="infoBoxContents">
            <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr>
                <td id="cupon" class="main"><?php echo ENTRY_DISCOUNT_COUPON.' '.tep_draw_input_field('coupon', '', 'size="32"'); ?></td>
              </tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>
      </tr>
	  <tr>
        <td>
		<?php //echo $messageStack->show( array( 'text' => MENSAJE_VACACIONES, 'class' => 'eror' ) ); ?>
		</td>
      </tr>
<?php
	}
/* end kgt - discount coupons */
?>
<?php

//Limitar esto para distribuidores
  if ((USE_POINTS_SYSTEM == 'true') && (USE_REDEEM_SYSTEM == 'true') && ($customer_group_id == '0')) {
	 //echo points_selection();
          $cart_show_total= $cart->show_total();
          echo points_selection($cart_show_total);
	  /*if (tep_not_null(USE_REFERRAL_SYSTEM) && (tep_count_customer_orders() == 0)) {
		  echo referral_input();
	  }*/
  }
?>


      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>

      <tr>

        <td><table border="0" width="100%" cellspacing="1" cellpadding="2" class="">
          <tr class="infoBoxContents">
            <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr>
                <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                <td class="main"><b><?php echo TITLE_CONTINUE_CHECKOUT_PROCEDURE . '</b><br>' . TEXT_CONTINUE_CHECKOUT_PROCEDURE; ?></td>
                <td class="main" align="right" id="boton">
					<!--<?php echo tep_image_submit('button_continue.gif', IMAGE_BUTTON_CONTINUE,'name="preview" value="preview data" style="border: none;"');  ?>-->
					<button type="submit" class="Button buttonFirst buttonBig Transition" id="checkoutShippingButton" data-text="<?php echo IMAGE_BUTTON_WAIT; ?>"><?php echo IMAGE_BUTTON_CONTINUE; ?></button>
				</td>
                <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td> 
              </tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>
      </tr>
      <tr>
        <td><?php include( 'theme/web/html/breadcrumb_checkout.php' ); ?></td>
      </tr>
    </table></form></td>
  </tr>
</table>

<?php // el siguiente script es para que solo aparezca envio gratis si lo hay ?>
<!--
<script language="javascript">

jQuery('document').ready(function() {
	var dmInput = jQuery('input[value="freeamount_freeamount"]');

	if( dmInput.length > 0 )
	{
		jQuery('input[name=shipping]').each(function(i)
		{
			if( jQuery(this).val() != "freeamount_freeamount" )
				jQuery(this).parent().parent().parent().parent().css("display", "none");
		});
	}
});

</script> //-->

<!-- footer //-->
<?php require(DIR_THEME. 'html/footer.php'); ?>
<!-- footer_eof //-->

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
