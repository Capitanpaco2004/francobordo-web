<?php
	require('includes/application_top.php');

	//fast easy checkout start
	tep_session_unregister('payment');
	//fast easy checkout end

	// if the customer is not logged on, redirect them to the login page
	if( !tep_session_is_registered('customer_id') )
	{
		$navigation->set_snapshot();
		tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
	}

	// if there is nothing in the customers cart, redirect them to the shopping cart page
  	if( $cart->count_contents() < 1 )
	{
		tep_redirect(tep_href_link(FILENAME_SHOPPING_CART));
	}

	// El checkout esta integrado envio/pago
	tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));

	// Comprobamos si tenemos ZONE ID //
  	// Comprobamos el ZONE ID
  	$aZoneID = tep_db_query( 'SELECT * FROM address_book WHERE address_book_id = ' . (int)$customer_default_address_id . ';' );
	$aZoneID = tep_db_fetch_array( $aZoneID );

  	// Si no tenemos ZONE ID redireccionamos a select zone
  	if( $aZoneID['entry_zone_id'] == 0 || $aZoneID['entry_zone_id'] == '' )
		tep_redirect( FILENAME_CHECKOUT_SELECT_ZONE );
	// FIN Comprobamos si tenemos ZONE ID //

	// if no shipping method has been selected, redirect the customer to the shipping method selection page
	if (!tep_session_is_registered('shipping') || empty($_SESSION['shipping']))
	{
		$messageStack->add_session('no_shipping', TEXT_ERROR_SHIPPING, 'success');
		tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
	}

	// avoid hack attempts during the checkout procedure by checking the internal cartID
	if (isset($cart->cartID) && tep_session_is_registered('cartID'))
	{
		if ($cart->cartID != $cartID)
		{
			tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
		}
	}

	// Stock Check
	//++++ QT Pro: Begin Changed code
  	if( (STOCK_CHECK == 'true') && (STOCK_ALLOW_CHECKOUT != 'true') )
	{
    	$products = $cart->get_products();
    	$any_out_of_stock = 0;
    	for ($i=0, $n=sizeof($products); $i<$n; $i++)
		{
     		if (isset($products[$i]['attributes']) && is_array($products[$i]['attributes']))
			{
       			$stock_check = tep_check_stock($products[$i]['id'], $products[$i]['quantity'], $products[$i]['attributes']);
     		}
			else
			{
       			$stock_check = tep_check_stock($products[$i]['id'], $products[$i]['quantity']);
			}

			if ($stock_check) $any_out_of_stock = 1;
		}

		if( $any_out_of_stock == 1 )
		{
      		tep_redirect(tep_href_link(FILENAME_SHOPPING_CART));
		}
	}
	//++++ QT Pro: End Changed Code

	// if no billing destination address was selected, use the customers own address as default
	if (!tep_session_is_registered('billto'))
	{
    	tep_session_register('billto');
    	$billto = $customer_default_address_id;
  	}
	else
	{
		// verify the selected billing address
    	if ( (is_array($billto) && empty($billto)) || is_numeric($billto) )
		{
      		$check_address_query = tep_db_query("select count(*) as total from " . TABLE_ADDRESS_BOOK . " where customers_id = '" . (int)$customer_id . "' and address_book_id = '" . (int)$billto . "'");
      		$check_address = tep_db_fetch_array($check_address_query);

      		if($check_address['total'] != '1')
			{
        		$billto = $customer_default_address_id;
        		if (tep_session_is_registered('payment')) tep_session_unregister('payment');
      		}
    	}
  	}

	require(DIR_WS_CLASSES . 'order.php');
	$order = new order;

	if( !tep_session_is_registered('comments') ) tep_session_register('comments');

	if( isset($_POST['comments']) && tep_not_null($_POST['comments']) )
	{
    	$comments = tep_db_prepare_input($_POST['comments']);
  	}

	$total_weight = $cart->show_weight();
	$total_count = $cart->count_contents();

	// load all enabled payment modules
	require(DIR_WS_CLASSES . 'payment.php');
	$payment_modules = new payment;

	require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CHECKOUT_PAYMENT);

	$breadcrumb->add(NAVBAR_TITLE_1, tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
	$breadcrumb->add(NAVBAR_TITLE_2, tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));

	require(DIR_THEME. 'html/header.php');
?>

<script type="text/javascript"><!--
	/* Points/Rewards Module V2.1rc2a bof*/
	var submitter = null;
	function submitFunction()
	{
		submitter = 1;
	}
	/* Points/Rewards Module V2.1rc2a eof*/
	var selected;

	function selectRowEffect(object, buttonSelect)
	{
		if (!selected)
		{
    		if (document.getElementById)
			{
      			selected = document.getElementById('defaultSelected');
    		}
			else
			{
      			selected = document.all['defaultSelected'];
    		}
		}

		if(selected)
			selected.className = 'moduleRow';

		object.className = 'moduleRowSelected';
  		selected = object;

		// one button is not an array
  		if (document.checkout_payment.payment[0])
		{
    		document.checkout_payment.payment[buttonSelect].checked=true;
  		}
		else
		{
			document.checkout_payment.payment.checked=true;
		}
	}

	function rowOverEffect(object)
	{
  		if (object.className == 'moduleRow') object.className = 'moduleRowOver';
	}

	function rowOutEffect(object)
	{
		if (object.className == 'moduleRowOver') object.className = 'moduleRow';
	}
</script>

<?php echo $payment_modules->javascript_validation(); ?>

<table border="0" width="100%" cellspacing="0" cellpadding="0" class="maincont_tb">
  <tr>
    <td width="100%" valign="top" class="maincont_mid_td">
    <?php echo tep_draw_form('checkout_payment', tep_href_link(FILENAME_CHECKOUT_CONFIRMATION, '', 'SSL'), 'post', 'onsubmit="return check_form();" class="checkFormWaiting" ', true); ?><table border="0" width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading" style="padding-top: 40px;"><?php echo HEADING_TITLE; ?></td>
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
        <td><table border="0" width="100%" cellspacing="1" cellpadding="2" class="infoBoxNotice">
          <tr class="infoBoxNoticeContents">
            <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                <td class="main" width="100%" valign="top"><?php echo tep_output_string_protected($error['error']); ?></td>
                <td><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
              </tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>
      </tr>
<?php
  }
?>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
          <tr>
            <td class="main"><b><?php echo TABLE_HEADING_BILLING_ADDRESS; ?></b></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="1" cellpadding="2" class="infoBox">
          <tr class="infoBoxContents">
            <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                <td class="main" width="50%" valign="top"><?php echo TEXT_SELECTED_BILLING_DESTINATION; ?><br><br><?php echo '<a href="' . tep_href_link(FILENAME_CHECKOUT_PAYMENT_ADDRESS, '', 'SSL') . '">' . tep_image_button('button_change_address.gif', IMAGE_BUTTON_CHANGE_ADDRESS) . '</a>'; ?></td>
                <td align="right" width="50%" valign="top"><table border="0" cellspacing="0" cellpadding="2">
                  <tr>
                    <td class="main" align="center" valign="top"><b><?php echo TITLE_BILLING_ADDRESS; ?></b><br><?php echo tep_image(DIR_WS_IMAGES . 'arrow_south_east.gif'); ?></td>
                    <td><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                    <td class="main" valign="top"><?php echo tep_address_label($customer_id, $billto, true, ' ', '<br>'); ?></td>
                    <td><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                  </tr>
                </table></td>
              </tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
          <tr>
            <td class="main"><b><?php echo TABLE_HEADING_PAYMENT_METHOD; ?></b></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="1" cellpadding="2" class="infoBox">
          <tr class="infoBoxContents">
            <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
<?php
  $selection = $payment_modules->selection();

  if (sizeof($selection) > 1) {
?>
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                <td class="main" width="50%" valign="top"><?php echo TEXT_SELECT_PAYMENT_METHOD; ?></td>
                <td class="main" width="50%" valign="top" align="right"><b><?php echo TITLE_PLEASE_SELECT; ?></b><br><?php echo tep_image(DIR_WS_IMAGES . 'arrow_east_south.gif'); ?></td>
                <td><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
              </tr>
<?php
  } else {
?>
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                <td class="main" width="100%" colspan="2"><?php echo TEXT_ENTER_PAYMENT_INFORMATION; ?></td>
                <td><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
              </tr>
<?php
  }

  $radio_buttons = 0;
  for ($i=0, $n=sizeof($selection); $i<$n; $i++) {
?>
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                <td colspan="2"><table border="0" width="100%" cellspacing="0" cellpadding="2">
<?php
    if ( ($selection[$i]['id'] == $payment) || ($n == 1) ) {
      echo '                  <tr id="defaultSelected" class="moduleRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="selectRowEffect(this, ' . $radio_buttons . ')">' . "\n";
    } else {
      echo '                  <tr class="moduleRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="selectRowEffect(this, ' . $radio_buttons . ')">' . "\n";
    }
?>
                    <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                    <td class="main" colspan="3"><b><?php echo $selection[$i]['module']; ?></b></td>
                    <td class="main" align="right">
<?php

    if (sizeof($selection) > 1) {
      echo tep_draw_radio_field('payment', $selection[$i]['id']);
    } else {
      echo tep_draw_hidden_field('payment', $selection[$i]['id']);
    }
?>
                    </td>
                    <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                  </tr>
<?php
    if (isset($selection[$i]['error'])) {
?>
                  <tr>
                    <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                    <td class="main" colspan="4"><?php echo $selection[$i]['error']; ?></td>
                    <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                  </tr>
<?php
    } elseif (isset($selection[$i]['fields']) && is_array($selection[$i]['fields'])) {
?>
                  <tr>
                    <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                    <td colspan="4"><table border="0" cellspacing="0" cellpadding="2">
<?php
      for ($j=0, $n2=sizeof($selection[$i]['fields']); $j<$n2; $j++) {
?>
                      <tr>
                        <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                        <td class="main"><?php echo $selection[$i]['fields'][$j]['title']; ?></td>
                        <td><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                        <td class="main"><?php echo $selection[$i]['fields'][$j]['field']; ?></td>
                        <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                      </tr>
<?php
      }
?>
                    </table></td>
                    <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                  </tr>
<?php
    }
?>
                </table></td>
                <td><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
              </tr>
<?php
    $radio_buttons++;
  }
?>
            </table></td>
          </tr>
        </table></td>
      </tr>
<!-- Points/Rewards Module V2.1rc2a Redeemption box bof -->
<?php
  if ((USE_POINTS_SYSTEM == 'true') && (USE_REDEEM_SYSTEM == 'true')) {
	  //echo points_selection();
	  $cart_show_total= $cart->show_total();
	  echo points_selection($cart_show_total);
	  if (tep_not_null(USE_REFERRAL_SYSTEM) && (tep_count_customer_orders() == 0)) {
		  echo referral_input();
	  }
  }
?>
<!-- Points/Rewards Module V2.1rc2a Redeemption box eof -->
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
          <tr>
            <td class="main"><b><?php echo TABLE_HEADING_COMMENTS; ?></b></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="1" cellpadding="2" class="infoBox">
          <tr class="infoBoxContents">
            <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr>
                <td><?php echo tep_draw_textarea_field('comments', 'soft', '60', '5'); ?></td>
              </tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>
      </tr>
<?php
/* kgt - discount coupons */
	if( MODULE_ORDER_TOTAL_DISCOUNT_COUPON_STATUS == 'true' ) {
?>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
          <tr>
            <td class="main"><b><?php echo TABLE_HEADING_COUPON; ?></b></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="1" cellpadding="2" class="infoBox">
          <tr class="infoBoxContents">
            <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr>
                <td class="main"><?php echo ENTRY_DISCOUNT_COUPON.' '.tep_draw_input_field('coupon', '', 'size="32"'); ?></td>
              </tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>
      </tr>
<?php
	}
/* end kgt - discount coupons */
?>

      <tr>
        <td><table border="0" width="100%" cellspacing="1" cellpadding="2" class="infoBox">
          <tr class="infoBoxContents">
            <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr>
                <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                <td class="main"><b><?php echo TITLE_CONTINUE_CHECKOUT_PROCEDURE . '</b><br>' . TEXT_CONTINUE_CHECKOUT_PROCEDURE; ?></td>
                <td class="main" align="right">
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
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td width="25%"><table border="0" width="100%" cellspacing="0" cellpadding="0">
              <tr>
                <td width="50%" align="right"><?php echo tep_draw_separator('pixel_silver.gif', '1', '5'); ?></td>
                <td width="50%"><?php echo tep_draw_separator('pixel_silver.gif', '100%', '1'); ?></td>
              </tr>
            </table></td>
            <td width="25%"><table border="0" width="100%" cellspacing="0" cellpadding="0">
              <tr>
                <td width="50%"><?php echo tep_draw_separator('pixel_silver.gif', '100%', '1'); ?></td>
                <td><?php echo tep_image(DIR_WS_IMAGES . 'checkout_bullet.gif'); ?></td>
                <td width="50%"><?php echo tep_draw_separator('pixel_silver.gif', '100%', '1'); ?></td>
              </tr>
            </table></td>
            <td width="25%"><?php echo tep_draw_separator('pixel_silver.gif', '100%', '1'); ?></td>
            <td width="25%"><table border="0" width="100%" cellspacing="0" cellpadding="0">
              <tr>
                <td width="50%"><?php echo tep_draw_separator('pixel_silver.gif', '100%', '1'); ?></td>
                <td width="50%"><?php echo tep_draw_separator('pixel_silver.gif', '1', '5'); ?></td>
              </tr>
            </table></td>
          </tr>
          <tr>
            <td align="center" width="25%" class="checkoutBarFrom"><?php echo '<a href="' . tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL') . '" class="checkoutBarFrom">' . CHECKOUT_BAR_DELIVERY . '</a>'; ?></td>
            <td align="center" width="25%" class="checkoutBarCurrent"><?php echo CHECKOUT_BAR_PAYMENT; ?></td>
            <td align="center" width="25%" class="checkoutBarTo"><?php echo CHECKOUT_BAR_CONFIRMATION; ?></td>
            <td align="center" width="25%" class="checkoutBarTo"><?php echo CHECKOUT_BAR_FINISHED; ?></td>
          </tr>
        </table></td>
      </tr>
    </table></form>
	 </td>
  </tr>
</table>

<?php require(DIR_THEME. 'html/column_right.php'); ?>
<?php require(DIR_THEME. 'html/footer.php'); ?>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
