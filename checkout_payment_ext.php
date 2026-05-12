<?php
/*
  $Id: checkout_payment_ext.php,v 1.00 2006/09/19 23:26:23 gnidhal Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/



  require('includes/application_top.php');
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

// load the selected payment module
  require(DIR_WS_CLASSES . 'payment.php');
  $payment_modules = new payment($payment);

  require(DIR_WS_CLASSES . 'order.php');
  $order = new order;

  $payment_modules->update_status();

  if ( ( is_array($payment_modules->modules) && (sizeof($payment_modules->modules) > 1) && !is_object($$payment) ) || (is_object($$payment) && ($$payment->enabled == false)) ) {
    tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode(ERROR_NO_PAYMENT_MODULE_SELECTED), 'SSL'));
  }

  if (is_array($payment_modules->modules)) {
    $payment_modules->pre_confirmation_check();
  }

// load the selected shipping module
  require(DIR_WS_CLASSES . 'shipping.php');
  $shipping_modules = new shipping($shipping);

  require(DIR_WS_CLASSES . 'order_total.php');
  $order_total_modules = new order_total;

// OrderCheck function register in order_check
  require(DIR_WS_INCLUDES . FILENAME_ORDERCHECK_FUNCTIONS);

  require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CHECKOUT_PAYMENT_EXT);

	// Politica de seguridad
	$termsAgree = $rgpd->postFormCheckTermsGeneral();

	// Politica de privacidad
	if( $termsAgree == '' )
	{
		$messageStack->addSession( 'error_politica', ERROR_POLITICA, 'error' );
		tep_redirect(tep_href_link( 'checkout_confirmation.php' ));
	}

  
	// if( $payment == 'paypal_ipn' )
		// $GLOBALS[$payment]->changePostTest();  
?>


<?php require(DIR_THEME. 'html/header.php'); ?>


<!-- body //-->
<?php require(DIR_THEME. 'html/column_left.php'); ?>

<h1 class="pageHeading"><?php echo HEADING_TITLE; ?></h1>
            <p><?php echo  TEXT_MAIN ."<strong>". $order->info['payment_method'] ."</strong>"; 
              $link_out = (isset($_POST['module_link']) && !empty($_POST['module_link'])) ? $_POST['module_link']: tep_href_link(FILENAME_LOGOFF);
              unset($_POST['module_link']);
              echo tep_draw_form('checkout_confirmation', $link_out, 'post');
                 foreach ($_POST as $key=>$value) {
                   echo tep_draw_hidden_field ($key, $value);
                 }
            ?></p>
            <p> <?php echo TEXT_WARNING_PAYMENT ;?></p>

<script language="JavaScript">//setTimeout("document.checkout_confirmation.submit() ", 10000)</script>             
</form>
<noscript>
<?php echo NO_JAVASCRIPT;?>
<p align="right"><?php echo tep_image_submit('button_confirm_order.gif', IMAGE_BUTTON_CONFIRM_ORDER) . '' . "\n"; ?></p>
 </noscript>                                

<?php require(DIR_THEME. 'html/column_right.php'); ?>
<!-- right_navigation_eof //-->
    </table></td>
  </tr>
</table>

<!-- body_eof //-->

<!-- footer //-->
<?php require(DIR_THEME. 'html/footer.php'); ?>
<!-- footer_eof //-->

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>