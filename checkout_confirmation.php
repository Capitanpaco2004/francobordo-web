<?php
	require('includes/application_top.php');

	if( array_key_exists('data_oe', $_POST) )
	{
		if( array_key_exists( 'choose_insurance', $_POST['data_oe'] ) )
			$_SESSION['choose_insurance'] = 1;
		else
			unset( $_SESSION['choose_insurance'] );
	}
	
	define('ORDERS_IMAGE_HEIGHT', 50);
  	define('ORDERS_IMAGE_WIDTH', 50);

	foreach ($_SESSION as $key => $val)
		$_POST[$key] = $val;

	// if the customer is not logged on, redirect them to the login page
	if (!tep_session_is_registered('customer_id'))
	{
    	$navigation->set_snapshot(array('mode' => 'SSL', 'page' => FILENAME_CHECKOUT_PAYMENT));
    	tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
  	}

// if there is nothing in the customers cart, redirect them to the shopping cart page
  if ($cart->count_contents() < 1) {
    tep_redirect(tep_href_link(FILENAME_SHOPPING_CART));
  }

  // Comprobamos si tenemos ZONE ID //

  // Comprobamos el ZONE ID
  $aZoneID = tep_db_query( 'SELECT * FROM address_book WHERE address_book_id = ' . (int)$customer_default_address_id . ';' );
  $aZoneID = tep_db_fetch_array( $aZoneID );

  // Si no tenemos ZONE ID redireccionamos a select zone
  if( $aZoneID['entry_zone_id'] == 0 || $aZoneID['entry_zone_id'] == '' )
		tep_redirect( FILENAME_CHECKOUT_SELECT_ZONE );

  // FIN Comprobamos si tenemos ZONE ID //

// avoid hack attempts during the checkout procedure by checking the internal cartID
  if (isset($cart->cartID) && tep_session_is_registered('cartID')) {
    if ($cart->cartID != $cartID) {
      tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
    }
  }

// if no shipping method has been selected, redirect the customer to the shipping method selection page
  if (!tep_session_is_registered('shipping') || empty($_SESSION['shipping'])) {
  	$messageStack->add_session('no_shipping', TEXT_ERROR_SHIPPING, 'error');
    tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
  }
  
	if( !tep_session_is_registered('payment') )
		tep_session_register('payment');

			if( array_key_exists('data_oe', $_POST) )
				$_POST['payment'] = $_POST['data_oe']['payment'];
	
	if( isset($_POST['payment']) )
	{		
		$payment = $_POST['payment'];

		if( isset( $_POST['redsysc'] ) && $_POST['redsysc'] != -1 )
			$_SESSION['redsysc'] = $_POST['redsysc'];
		elseif( isset( $_POST['redsysc'] ) && $_POST['redsysc'] == -1 )
			$payment = 'redsys';
	}
	
	if(!tep_session_is_registered('comments'))
		tep_session_register('comments');

	if (isset($_POST['comments']) && tep_not_null($_POST['comments']))
	  $comments = tep_db_prepare_input($_POST['comments']);

  //kgt - discount coupons
  if (!tep_session_is_registered('coupon')) tep_session_register('coupon');
  //this needs to be set before the order object is created, but we must process it after
    if( array_key_exists( 'coupon', $_POST ) )
	$coupon = tep_db_prepare_input($_POST['coupon']);

  // Si el cupon esta vacio lo eliminamos
  if( $coupon == '' )
  {
	unset( $coupon );
	tep_session_unregister( 'coupon' );
  }
  //end kgt - discount coupons
// load the selected payment module
  require(DIR_WS_CLASSES . 'payment.php');
  $payment_modules = new payment($payment);

  require(DIR_WS_CLASSES . 'order.php');
  $order = new order;


	// Si el metodo de envio no es kiala pero el envio si es kiala volvemos al sendto por defecto asi solucionamos el error de seleccionar metodos de envio diferentes y se quede pillado kiala
	if( !preg_match( '/kiala/i', $order->info['shipping_method'] ) && preg_match( '/kiala/i', $order->delivery['firstname'] ) )
	{
		$sendto = $customer_default_address_id;
		tep_redirect(tep_href_link(FILENAME_CHECKOUT_CONFIRMATION));
	}


  $payment_modules->update_status();

	/// fec for get total
 	$paynow = tep_db_prepare_input($_GET['paynow']);
	if ($paynow ==3)
	{
 		tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, 'show_total=1&error_message=' . urlencode(ERROR_TOTAL_NOW), 'SSL'));
  	}

	##### Points/Rewards Module V2.1rc2a check for error BOF #######
	if ((USE_POINTS_SYSTEM == 'true') && (USE_REDEEM_SYSTEM == 'true'))
	{
		if( isset($_POST['customer_shopping_points_spending']) && is_numeric($_POST['customer_shopping_points_spending']) && ($_POST['customer_shopping_points_spending'] > 0) )
		{
			$customer_shopping_points_spending = false;

			if (tep_calc_shopping_pvalue($_POST['customer_shopping_points_spending']) < $order->info['total'] && !is_object($$payment) || (tep_get_shopping_points($customer_id) < $_POST['customer_shopping_points_spending']))
			{
				$customer_shopping_points_spending = false;
				tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode(REDEEM_SYSTEM_ERROR_POINTS_NOT), 'SSL'));
			}
			else
			{
				$customer_shopping_points_spending = $_POST['customer_shopping_points_spending'];
				if (!tep_session_is_registered('customer_shopping_points_spending')) tep_session_register('customer_shopping_points_spending');
			}
		}

		if (tep_not_null(USE_REFERRAL_SYSTEM) && (tep_count_customer_orders() == 0))
		{
			if (isset($_POST['customer_referred']) && tep_not_null($_POST['customer_referred']))
			{
				$customer_referral = false;
				$check_mail = trim($_POST['customer_referred']);

				if(tep_validate_email($check_mail) == false)
				{
					tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode(REFERRAL_ERROR_NOT_VALID), 'SSL'));
				}
				else
				{
					$valid_referral_query = tep_db_query("select customers_id from " . TABLE_CUSTOMERS . " where customers_email_address = '" . $check_mail . "' limit 1");
					$valid_referral = tep_db_fetch_array($valid_referral_query);

					if( !tep_db_num_rows($valid_referral_query) )
					{
						tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode(REFERRAL_ERROR_NOT_FOUND), 'SSL'));
					}

					if( $check_mail == $order->customer['email_address'] )
					{
						tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode(REFERRAL_ERROR_SELF), 'SSL'));
				  	}
					else
					{
						$customer_referral = $valid_referral['customers_id'];

						if (!tep_session_is_registered('customer_referral')) tep_session_register('customer_referral');

						if(KEEP_REFERRER_ID=='true') tep_db_query("update " . TABLE_CUSTOMERS . " set customer_referral = '" . (int)$customer_referral . "' where customer_referral = '0' and customers_id = '" . (int)$customer_id . "'");
					}
				}
		  	}
	  	}
  	}
	
  if( !array_key_exists( 'curl_oe', $_GET ) )
  if ( ($payment_modules->selected_module != $payment) || ( is_array($payment_modules->modules) && (sizeof($payment_modules->modules) > 1) && !is_object($$payment) ) && (!$customer_shopping_points_spending) || (is_object($$payment) && ($$payment->enabled == false)) ) {
    	tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode(ERROR_NO_PAYMENT_MODULE_SELECTED), 'SSL'));
	}
	########  Points/Rewards Module V2.1rc2a EOF #################*/

	// Sampedro: Editor de pedidos, si mandamos por GET dicha variable, pasamos los pre confirmation
	if( !array_key_exists( 'curl_oe', $_GET ) )
	  if (is_array($payment_modules->modules)) {
		$payment_modules->pre_confirmation_check();
	  }

	//kgt - discount coupons
	// Sampedro: Editor de pedidos, si mandamos por GET dicha variable, pasamos de comprobar cupones
	if( !array_key_exists( 'curl_oe', $_GET ) )
	if( tep_not_null( $coupon ) && is_object( $order->coupon ) )
	{
    	$order->coupon->verify_code();
	    if( MODULE_ORDER_TOTAL_DISCOUNT_COUPON_DEBUG != 'true' )
		{
			if( !$order->coupon->is_errors() )
			{
				//if we have passed all tests (no error message), make sure we still meet free shipping requirements, if any
				// ChilliNr1`s Coupon AND Free Shipping Fix START
				/**************if( $order->coupon->is_recalc_shipping() ) tep_redirect( tep_href_link( FILENAME_CHECKOUT_SHIPPING, 'error_message=' . urlencode( ENTRY_DISCOUNT_COUPON_SHIPPING_CALC_ERROR ), 'SSL' ) ); //redirect to the shipping page to reselect the shipping method**************/
				// ChilliNr1`s Coupon AND Free Shipping Fix END
			}
			else
			{
				if( tep_session_is_registered('coupon') ) tep_session_unregister('coupon'); //remove the coupon from the session
				tep_redirect( tep_href_link( FILENAME_CHECKOUT_SHIPPING, 'error_message=' . urlencode( implode( ' ', $order->coupon->get_messages() ) ), 'SSL' ) ); //redirect to the payment page
			}
		}
	}
	else
	{
		//if the coupon field is empty, unregister the coupon from the session
		if( tep_session_is_registered('coupon') )
		{
			//we had a coupon entered before, so we need to unregister it
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

	//$_SESSION['choose_insurance'] = $_POST[choose_insurance];

  	require(DIR_WS_CLASSES . 'order_total.php');
  	$order_total_modules = new order_total;
	$order_totals = $order_total_modules->process();

	// Stock Check, esto en su codigo esta todo comentado....
	$any_out_of_stock = false;
	if( STOCK_CHECK == 'true' )
	{
		//++++ QT Pro: Begin Changed code
  	$check_stock=array();
    	for( $i=0, $n=sizeof($order->products); $i<$n; $i++ )
		{
      		if( isset($order->products[$i]['attributes']) && is_array($order->products[$i]['attributes']) )
			{
        		$attributes=array();
        		foreach ($order->products[$i]['attributes'] as $attribute)
				{
          			$attributes[$attribute['option_id']]=$attribute['value_id'];
        		}

				$check_stock[$i] = tep_check_stock($order->products[$i]['id'], $order->products[$i]['qty'], $attributes);

		} else {
			$check_stock[$i] = tep_check_stock($order->products[$i]['id'], $order->products[$i]['qty']);
		}

		if (is_array($check_stock[$i]) && count($check_stock[$i]) > 0) {
			$any_out_of_stock = true;
		}else{
			$any_out_of_stock = false;
		}
    	}

		// Out of Stock
    if ( (STOCK_ALLOW_CHECKOUT != 'true') && ($any_out_of_stock == true) ) {
      		tep_redirect(tep_href_link(FILENAME_SHOPPING_CART));
    	}
  	}

	//-----   BEGINNING OF ADDITION: MATC   -----//
	if (tep_db_prepare_input($_POST['TermsAgree']) != 'true' and MATC_AT_CHECKOUT != 'false')
	{
		tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'matcerror=true', 'SSL'));
	}

	require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CHECKOUT_CONFIRMATION);

	$breadcrumb->add(NAVBAR_TITLE_1, tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
	$breadcrumb->add(NAVBAR_TITLE_2);

	require(DIR_THEME. 'html/header.php');
?>
<script language="Javascript">
	function showDiv(objectID)
	{
		var theElementStyle = document.getElementById(objectID);

		if(theElementStyle.style.display == "none")
		{
			theElementStyle.style.display = "block";
		}
		else
		{
			theElementStyle.style.display = "none";
		}
	}
</script>

<!-- Agree to Terms and Conditions - added by MC -->
<SCRIPT LANGUAGE="JavaScript">
	function checkCheckBox(f)
	{
		if (f.dxconfianza.checked == false )
		{
			alert('<?php echo CONDITION_AGREEMENT_WARNING; ?>');
			return false;
		}
		else
			return true;
	}
</script>
<!-- End Agree to Terms and Conditions -->

<?php
	require(DIR_THEME. 'html/column_left.php');

	if (isset($$payment->form_action_url))
	{
		$form_action_url = $$payment->form_action_url;
		require(DIR_WS_INCLUDES . FILENAME_ORDERCHECK_FUNCTIONS);
	}
	else
	{
		$form_action_url = tep_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL');
	}

	echo '<form name="checkout_confirmation" method="post" action="' . $form_action_url . '" onsubmit="return checkCheckBox(this)" class="checkFormWaiting">';
	
	if( $messageStack->check( 'error_politica' ) )
		echo $messageStack->show( 'error_politica' );
?>
    <table border="0" width="100%" cellspacing="0" cellpadding="0">
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
	  <tr>
			<td>
				<div class="confirmacion">
				<?php
				  if ($sendto != false) {
				?>
					<div class="confirmacion_interior">
						<p class="titulo_confirmacion"><?php echo '<strong>' . HEADING_DELIVERY_ADDRESS . '</strong> <a href="' . tep_href_link(FILENAME_CHECKOUT_SHIPPING_ADDRESS, '', 'SSL') . '"><span class="orderEdit">(' . TEXT_EDIT . ')</span></a>'; ?></p>
						<p><?php echo tep_address_format($order->delivery['format_id'], $order->delivery, 1, ' ', '<br />'); ?></p>
					</div>
				<?php
				  }
				?>
					<div class="confirmacion_interior">
						<p class="titulo_confirmacion"><?php echo '<strong>' . HEADING_BILLING_ADDRESS . '</strong> <a href="' . tep_href_link(FILENAME_CHECKOUT_PAYMENT_ADDRESS, '', 'SSL') . '"><span class="orderEdit">(' . TEXT_EDIT . ')</span></a>'; ?></p>
						<p><?php echo tep_address_format($order->billing['format_id'], $order->billing, 1, ' ', '<br />'); ?></p>
					</div>
				<?php

				  if ($sendto != false) {
					if ($order->info['shipping_method']) {
				?>
					<div class="confirmacion_interior">
						<p class="titulo_confirmacion"><?php echo '<strong>' . HEADING_SHIPPING_METHOD . '</strong> <a href="' . tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL') . '"><span class="orderEdit">(' . TEXT_EDIT . ')</span></a>'; ?></p>
						<p>
							<?php
								if( is_array( $order->info['shipping_method'] ) )
									echo $order->info['shipping_method'][0];
								else
									echo str_replace( '()', '', $order->info['shipping_method'] );

								// Inicio, tiendas
								if($shipping['id'] == 'retira_retira')
								{
									$aDatos = tep_db_query( 'select store_name, store_address from store where id_store = "' . (int)$store_id . '"' );

									if( tep_db_num_rows( $aDatos ) > 0 )
									{
										$aDato = tep_db_fetch_array( $aDatos );
										echo ' (' . $aDato['store_name'] . ', ' . $aDato['store_address'] . ')';
									}
								}
								// Fin, tiendas

							?>
						</p>
					</div>
				<?php
					}
				  }
				?>
					<div class="confirmacion_interior">
						<p class="titulo_confirmacion"><?php echo '<strong>' . HEADING_PAYMENT_METHOD . '</strong> <a href="' . tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL') . '"><span class="orderEdit">(' . TEXT_EDIT . ')</span></a>'; ?></p>
						<p><?php echo $order->info['payment_method']; ?></p>
					</div>
				</div>

				<div class="pghd"><?php echo CHECKOUT_CESTA_DE_LA_COMPRA ?></div>
				<table border="0" width="100%" cellspacing="0" cellpadding="2" id="tble-crrt">
				<?php
				  if (sizeof($order->info['tax_groups']) > 1) {
				?>
								  <tr>
									<td class="smallText" align="right"></td>
									<td class="main" colspan="2"><?php echo '<strong>' . HEADING_PRODUCTS . '</strong> <a href="' . tep_href_link(FILENAME_SHOPPING_CART) . '"><span class="orderEdit">(' . TEXT_EDIT . ')</span></a>'; ?></td>
									<td class="smallText" align="right"><strong><?php echo HEADING_TAX; ?></strong></td>
									<td class="smallText" align="right"><strong><?php echo HEADING_TOTAL; ?></strong></td>
								  </tr>
				<?php
				  } else {
				?>
								  <tr>
									<td class="main" colspan="3"><?php echo '<strong>' . HEADING_PRODUCTS . '</strong> <a href="' . tep_href_link(FILENAME_SHOPPING_CART) . '"><span class="orderEdit">(' . TEXT_EDIT . ')</span></a>'; ?></td>
								  </tr>
				<?php
				  }

				  for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {

				    $orders_products_pic_query = tep_db_query("select products_model, products_image from " . TABLE_PRODUCTS . " where products_id = '".(int)$order->products[$i]['id']."' ");
   					$orders_products_pic = tep_db_fetch_array($orders_products_pic_query);

					echo '<tr>' . "\n" .
						 '<td class="main" align="right" valign="top" width="30"><a href="javascript:popupWindow(\'' . DIR_WS_IMAGES . 'productos/' . $orders_products_pic['products_image'] . '\'' . ')">' . tep_image(DIR_WS_IMAGES . 'productos/' . $orders_products_pic['products_image'], $order->products[$i]['name'], ORDERS_IMAGE_WIDTH,  ORDERS_IMAGE_HEIGHT) . '</a>&nbsp;</td>' . "\n" .
						 '<td class="main" align="right" valign="top" width="30">' . $order->products[$i]['qty'] . '&nbsp;x</td>' . "\n" .
						 '<td class="main" valign="top" data-product-id="' . $order->products[$i]['id'] . '" data-product-price="' . $order->products[$i]['price'] . '" data-product-tax="' . $order->products[$i]['tax'] . '" data-product-ubicacion="' . $order->products[$i]['ubicacion'] . '" data-product-ean="' . $order->products[$i]['ean'] . '" data-product-model="' . $order->products[$i]['model'] . '" data-product-qty="' . $order->products[$i]['qty'] . '">' . $order->products[$i]['name'];

					if (STOCK_CHECK == 'true') {
					  echo tep_check_stock($order->products[$i]['id'], $order->products[$i]['qty']);
					}

					if ( (isset($order->products[$i]['attributes'])) && (sizeof($order->products[$i]['attributes']) > 0) ) {
					  for ($j=0, $n2=sizeof($order->products[$i]['attributes']); $j<$n2; $j++) {
						echo '<br /><nobr><small>&nbsp;<i> - ' . $order->products[$i]['attributes'][$j]['option'] . ': ' . $order->products[$i]['attributes'][$j]['value'] . '</i></small></nobr>';
					  }
					}


					// Obtenemos la cantidad de productos del carrito y si queremos controlar el stock
					$aStock = tep_db_query( 'SELECT products_quantity, check_stock FROM ' . TABLE_PRODUCTS . ' WHERE products_id = "' . $order->products[$i]['id'] . '";' );
					$aStock = tep_db_fetch_array( $aStock );


					// Control de stock POR VARIANTE (OR con el global)
					if (!(int)$aStock['check_stock'] && isset($order->products[$i]['attributes']) && is_array($order->products[$i]['attributes']) && function_exists('fb_variant_check_stock'))
						$aStock['check_stock'] = fb_variant_check_stock($order->products[$i]['id'], $order->products[$i]['attributes'], 0);

					// Si NO queremos controlar el stock
					if( $aStock['check_stock'] == 0 )
					{
						// Si SI tenemos atributos
						if( isset( $order->products[$i]['attributes'] ) && is_array( $order->products[$i]['attributes'] ) && count( $order->products[$i]['attributes'] ) > 0 )
						{
							// Variables
							$sAttributes = '';

							// Obtenemos los atributos del producto
							foreach( $order->products[$i]['attributes'] as $nAttribute => $aAttribute )
								$sAttributes .= $aAttribute['option_id'] . '-' . $aAttribute['value_id'] . ',';
							$sAttributes = substr( $sAttributes, 0, -1 );

							// Obtenemos el stock del producto
							$aAux = tep_db_query( 'SELECT products_stock_quantity FROM products_stock WHERE products_id = "' . $order->products[$i]['id'] . '" AND products_stock_attributes = "' . $sAttributes . '";' );

							// Si tenemos registro
							if( tep_db_num_rows( $aAux ) > 0 )
							{
								// Registro
								$aAux = tep_db_fetch_array( $aAux );

								// Si superamos el número de stock
								if( $order->products[$i]['qty'] > $aAux['products_stock_quantity'] )
								{
									// Obtenemos la cantidad del stock
									$nQty = $order->products[$i]['qty'] - $aAux['products_stock_quantity'];

									// Añadimos la línea informativa
									if( $aAux['products_stock_quantity'] > 0 )
										echo '<br /><i style="color: #e77200;">- <b>Usted ha solicitado ' . $order->products[$i]['qty']  . ' unidad' . ($order->products[$i]['qty'] == 1 ? '' : 'es') . ' y solo queda' . ($aAux['products_stock_quantity'] == 1 ? '' : 'n') . ' ' . $aAux['products_stock_quantity'] . ' en stock.<br />La' . ($nQty == 1 ? '' : 's') . ' ' . $nQty . ' unidad' . ($nQty == 1 ? '' : 'es') . ' que falta' . ($nQty == 1 ? '' : 'n') . ' estará' . ($nQty == 1 ? '' : 'n') . ' disponible' . ($nQty == 1 ? '' : 's') . '<br />en un plazo de 7 - 10 días laborables.</strong></b><br />';
								}
							}
						}
						// Si NO tenemos atributos
						else
						{
							// Si superamos el número de stock
							if( $order->products[$i]['qty'] > $aStock['products_quantity'] )
							{
								// Obtenemos la cantidad del stock
								$nQty = $order->products[$i]['qty'] - $aStock['products_quantity'];

								// Añadimos la línea informativa
								if( $aStock['products_quantity'] > 0 )
									echo '<br /><i style="color: #e77200;">- <b>Usted ha solicitado ' . $order->products[$i]['qty']  . ' unidad' . ($order->products[$i]['qty'] == 1 ? '' : 'es') . ' y solo queda' . ($aStock['products_quantity'] == 1 ? '' : 'n') . ' ' . $aStock['products_quantity'] . ' en stock.<br />La' . ($nQty == 1 ? '' : 's') . ' ' . $nQty . ' unidad' . ($nQty == 1 ? '' : 'es') . ' que falta' . ($nQty == 1 ? '' : 'n') . ' estará' . ($nQty == 1 ? '' : 'n') . ' disponible' . ($nQty == 1 ? '' : 's') . '<br />en un plazo de 7 - 10 días laborables.</strong></b><br />';
							}
						}
					}


					echo '</td>' . "\n";

					if (sizeof($order->info['tax_groups']) > 1) echo '            <td class="main" valign="top" align="right">' . tep_display_tax_value($order->products[$i]['tax']) . '%</td>' . "\n";

					echo '            <td class="main" align="right" valign="top">' . $currencies->display_price($order->products[$i]['final_price'], $order->products[$i]['tax'], $order->products[$i]['qty']) . '</td>' . "\n" .
						 '          </tr>' . "\n";
				  }
				?>
				</table>
				<div class="totales">
					<?php
					  global $sppc_customer_group_show_tax;
				  
					  if($sppc_customer_group_show_tax=='1')
						$show_tax=true;
					  else
						$show_tax=false;

					  if( array_key_exists( 'curl_oe', $_GET ) )
						$show_tax=false;  
					
					  if (MODULE_ORDER_TOTAL_INSTALLED) {
						echo '<table border="0" width="100%" cellspacing="0" cellpadding="2">' . $order_total_modules->output($show_tax) . '</table>';
					  }
					?>
				</div>
			</td>
	  </tr>
	  <tr>
		<td><table border="0" width="100%" cellspacing="0" cellpadding="2">
				  <tr>
					<td class="main"><div class="pghd"><?php echo CHECKOUT_COMENTARIOS; ?></div></td>
				  </tr>
				</table></td>
			  </tr>
			  <tr>
				<td><table border="0" width="100%" cellspacing="1" cellpadding="2">
				  <tr class="infoBoxContents">
					<td><table border="0" width="100%" cellspacing="0" cellpadding="2">
					  <tr>
						<td><?php echo tep_draw_textarea_field('comments', 'soft', '60', '5', $comments); ?></td>
					  </tr>
					</table></td>
				  </tr>
				</table></td>
	  </tr>
<?php
  if (is_array($payment_modules->modules)) {
	// Sampedro: Editor de pedidos, si mandamos por GET dicha variable, pasamos los pre confirmation
	if( !array_key_exists( 'curl_oe', $_GET ) )
    if ($confirmation = $payment_modules->confirmation()) {
?>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>
      </tr>
      <tr>
        <td class="main"><div class="pghd"><?php echo HEADING_PAYMENT_INFORMATION; ?></div></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="1" cellpadding="2" class="infoBox" id="info-pago">
          <tr class="infoBoxContents">
            <td><table border="0" cellspacing="0" cellpadding="2">
              <tr>
                <td class="main" colspan="4"><?php echo $confirmation['title']; ?></td>
              </tr>
<?php
      for ($i=0, $n=sizeof($confirmation['fields']); $i<$n; $i++) {
?>
              <tr>
                <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                <td class="main"><?php echo $confirmation['fields'][$i]['title']; ?></td>
                <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                <td class="main"><?php echo $confirmation['fields'][$i]['field']; ?></td>
              </tr>
<?php
      }
?>
            </table></td>
          </tr>
        </table></td>
      </tr>
<?php
    }
  }
?>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>
      </tr>
<?php
  if (tep_not_null($order->info['comments'])) {
?>
      <tr>
        <td class="main"><?php echo '<b>' . HEADING_ORDER_COMMENTS . '</b> <a href="' . tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL') . '"><span class="orderEdit">(' . TEXT_EDIT . ')</span></a>'; ?></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="1" cellpadding="2" class="infoBox">
          <tr class="infoBoxContents">
            <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr>
                <td class="main"><?php echo nl2br(tep_output_string_protected($order->info['comments'])) . tep_draw_hidden_field('comments', $order->info['comments']); ?></td>
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
        <td>
		<?php //echo $messageStack->show( array( 'text' => MENSAJE_VACACIONES, 'class' => 'eror' ) ); ?>
		</td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
<?php
//-----   BEGINNING OF ADDITION: MATC   -----//
if(MATC_AT_CHECKOUT != 'false'){
	require(DIR_WS_MODULES . 'matc.php');
}
//-----   END OF ADDITION: MATC   -----//
?>
          <tr>
            <td align="right" class="main">
<?php
  if (is_array($payment_modules->modules)) {
    echo $payment_modules->process_button();
  }
 ?>

<div class="warningdiv">

<?php

	// Obtenemos el tiempo de envío //

	// Globales
	global $cart;

	// Variables
	$aHours = false;
	$nHours1 = 0;
	$nHours2 = 24;
	$sEstimate = '';
	$nAdd1 = 0;
	$nAdd2 = 0;
	$quotes = $shipping_modules->quote();
	$i = 0;
	$j = 0;

	// Obtenemos los productos
	$products = $cart->get_products();

	// Del nombre del módulo
	if( preg_match( '/(hora)/i', $quotes[$i]['module'] ) )
		$sExtract = $quotes[$i]['module'];
	// O del título
	else if( preg_match( '/(hora)/i', $quotes[$i]['methods'][$j]['title'] ) )
		$sExtract = $quotes[$i]['methods'][$j]['title'];
	// Casos especiales
	else if( $quotes[$i]['id'] == 'seurnacional' || $quotes[$i]['id'] == 'tipsa' )
		$sExtract = '24 horas';
	// Si no, no tenemos
	else
		$sExtract = false;

	// Si tenemos horas, las extraemos
	if( $sExtract !== false )
	{
		// Extraemos las horas
		preg_match( '/(?<hour>[0-9]+(\-)?([0-9]+)?)/', $sExtract, $aMatches );

		// Si tenemos horas
		if( isset( $aMatches['hour'] ) )
		{
			// Si tenemos rango horario
			if( preg_match( '/(\-)/i', $aMatches['hour'] ) )
			{
				// Dividimos y guardamos
				$aHours = explode( '-', $aMatches['hour'] );
				$nHours1 = $aHours[0];
				$nHours2 = $aHours[1];
			}
			// Si no, obtenemos las horas
			else
			{
				// Obtenemos y sumamos 24 horas
				$nHours1 = $aMatches['hour'];
				$nHours2 = $aMatches['hour'] + 24;
			}

			// Quitamos 24 horas
			$nHours1 -= 24;
			$nHours2 -= 24;
		}

		// Recorremos los productos del carrito
		for( $nCont = 0, $nQty = sizeof( $products ); $nCont < $nQty; $nCont++ )
		{
			$aProduct = $products[$nCont];

			// Obtenemos el ID del producto
			$nID = (isset( $aProduct['products_id'] ) ? $aProduct['products_id'] : $aProduct['id']);
			
			// Separamos los id atributo
			preg_match( '/(\{)(.*)(\}(.*))/i', $nID, $aMatch );

			$nID = (preg_match( '/(\{)/i', $nID ) ? preg_replace( '/(\{)(.*)/i', '', $nID ) : $nID);

			// Si no tenemos el valor de products_quantity
			if( ! isset ( $aProduct['products_quantity'] ) )
			{
				// Obtenemos la cantidad del producto
				$aAux = tep_db_query( 'SELECT products_quantity FROM ' . TABLE_PRODUCTS . ' WHERE products_id = "' . $nID . '";' );
				$aAux = tep_db_fetch_array( $aAux );
				$aProduct['products_quantity'] = $aAux['products_quantity'];
			}

			if( isset ( $aProduct['attributes'] ) && isset( $aMatch[2] ) )
			{
				$aCheck = tep_db_query( 'SELECT check_stock FROM products WHERE products_id = "' . $nID . '";' );
				$aCheck = tep_db_fetch_array( $aCheck );


				// Control de stock POR VARIANTE (OR con el global)
				if (!(int)$aCheck['check_stock'] && function_exists('fb_variant_check_stock'))
					$aCheck['check_stock'] = fb_variant_check_stock($nID, $aMatch[2] . '-' . $aMatch[4], 0);

				$nStock = stock_en_atributos( $aMatch[2], $aMatch[4], $nID );
				$sClass = claseBotonComprar( $nStock, $aCheck['check_stock'] );
				
				// Entre 2 y 6 días
				if( trim( $sClass ) == 'prdt-4dias' )
				{
					if( $nAdd1 <= ( 24 * 2 ) )
						$nAdd1 = ( 24 * 2 );
					if( $nAdd2 <= ( 24 * 6 ) )
						$nAdd2 = ( 24 * 6 );
				}
				// Entre 8 y 13 días
				else if( trim( $sClass ) == 'prdt-5dias' )
				{
					if( $nAdd1 <= ( 24 * 8 ) )
						$nAdd1 = ( 24 * 8 );
					if( $nAdd2 <= ( 24 * 13 ) )
						$nAdd2 = ( 24 * 13 );
				}
				// Bajo pedido / Agotado
				elseif( trim( $sClass ) == 'prdt-bjpdd' || trim( $sClass ) == 'prdt-agtd' )
				{
					$nAdd1 = false;
					$nAdd2 = false;
					break;
				}
			}
			else
			{
				// Entre 2 y 6 días
				if( $aProduct['products_quantity'] <= -100 && $aProduct['products_quantity'] >= -150 )
				{
					if( $nAdd1 <= ( 24 * 2 ) )
						$nAdd1 = ( 24 * 2 );
					if( $nAdd2 <= ( 24 * 6 ) )
						$nAdd2 = ( 24 * 6 );
				}
				// Entre 8 y 13 días
				else if( $aProduct['products_quantity'] <= 0 && $aProduct['products_quantity'] >= -799 )
				{
					if( $nAdd1 <= ( 24 * 8 ) )
						$nAdd1 = ( 24 * 8 );
					if( $nAdd2 <= ( 24 * 13 ) )
						$nAdd2 = ( 24 * 13 );
				}
				// Bajo pedido
				else if( $aProduct['products_quantity'] <= -800 && $aProduct['products_quantity'] >= -899 )
				{
					$nAdd1 = false;
					$nAdd2 = false;
					break;
				}
				// Agotado
				else if( $aProduct['products_quantity'] <= -900 && $aProduct['products_quantity'] >= -901 )
				{
					$nAdd1 = false;
					$nAdd2 = false;
					break;
				}
			}
		}

		// Si tenemos predicción
		if( $nAdd1 !== false )
		{
			// Obtenemos las dos estimaciones
			$aEstimate1 = getShippingEstimate( true, false, $nAdd1 + $nHours1, $quotes[$i]['id'] );
			$aEstimate2 = getShippingEstimate( true, false, $nAdd2 + ($nHours2 == 24 ? 0 : $nHours2), $quotes[$i]['id'] );

			// Si las fechas son iguales, sumamos un día
			if( $aEstimate1['date'] == $aEstimate2['date'] )
			{
				$aEstimate2 = addHoursToDate( $aEstimate2['year'] . '-' . $aEstimate2['month'] . '-' . $aEstimate2['day'], 24 );

				// Si al sumar la fecha es domingo, sumamos un día mas
				if( date( 'N', mktime( 0, 0, 0, $aEstimate2['month'], $aEstimate2['day'], $aEstimate2['year'] ) ) == 7 )
					$aEstimate2 = addHoursToDate( $aEstimate2['year'] . '-' . $aEstimate2['month'] . '-' . $aEstimate2['day'], 24 );
			}

			$sEstimate = str_replace( array( '%s1', '%s2' ), array( dateToSpanish( date( 'l j ' . SHIPPING_PREDICTION_FROM . ' F', strtotime( $aEstimate1['year'] . '-' . $aEstimate1['month'] . '-' . $aEstimate1['day'] ) ) ), dateToSpanish( date( 'l j ' . SHIPPING_PREDICTION_FROM . ' F', strtotime( $aEstimate2['year'] . '-' . $aEstimate2['month'] . '-' . $aEstimate2['day'] ) ) ) ), SHIPPING_PREDICTION_BUY_NOW ) . ($quotes[$i]['id'] == 'seurnacional' && $nAdd2 <= 0 ? SHIPPING_PREDICTION_BEFORE : '') . '.';
		}
		// Si no podemos hacer predicción
		else
			$sEstimate = SHIPPING_PREDICTION_NONE . '.';

		// Mostramos la estimación
		echo '<p class="campo"><div class="fre-shp" style="text-align: right !important;">' . $sEstimate . ' <span class="btn-info"><a href="shipping_estimate_more_info.php" title="Más información" style="display: inline;">(+ info.)</a></span></div></p>';
	}

?>

<?php
//echo '<p class="campo">' . tep_draw_checkbox_field('dxconfianza', '1', false, 'style="float: none; position: relative; top: 2px;"') . FORM_POLITICA . '</p>';
  echo '<div style="margin: 10px 0px 20px;">' . $rgpd->formCheckTermsGeneral() . '</div>';
  //echo tep_image_submit('button_confirm_order.gif', IMAGE_BUTTON_CONFIRM_ORDER,'id="TheSubmitButton" style="border:none;"') . "\n";

?>
<button type="submit" class="Button buttonFirst buttonBig Transition" id="checkoutShippingButton" data-text="<?php echo IMAGE_BUTTON_WAIT; ?>"><?php echo IMAGE_BUTTON_CONFIRM_ORDER; ?></button>
            </td> 
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>
      </tr>
      <tr>
        <td>
			<?php include( 'theme/web/html/breadcrumb_checkout.php' ); ?>
		</td>
      </tr>
    </table>
</form>

<?php
	require(DIR_THEME. 'html/column_right.php');
	require(DIR_THEME. 'html/footer.php');
	require(DIR_WS_INCLUDES . 'application_bottom.php');
?>
