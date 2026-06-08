<script language="JavaScript">
  function shipincart_submit(sid){

      $('body').addClass('loading');

        if(sid){
          document.estimator.sid.value=sid;
        }

        document.estimator.submit();

        return false;
  }
</script>

<style>
.shipping-estimator-collapse { margin: 10px 0; border: 1px solid #d3e1ef; border-radius: 4px; background: #fff; }
.shipping-estimator-collapse > summary.shipping-estimator-summary { list-style: none; cursor: pointer; padding: 12px 40px 12px 16px; font-weight: 600; color: #1c8bc3; text-transform: uppercase; background: #f3f4f8; border-bottom: 1px solid #d3e1ef; position: relative; user-select: none; }
.shipping-estimator-collapse > summary.shipping-estimator-summary::-webkit-details-marker { display: none; }
.shipping-estimator-collapse > summary.shipping-estimator-summary::after { content: ""; position: absolute; right: 18px; top: 50%; width: 8px; height: 8px; border-right: 2px solid #1c8bc3; border-bottom: 2px solid #1c8bc3; transform: translateY(-70%) rotate(45deg); transition: transform .2s; }
.shipping-estimator-collapse[open] > summary.shipping-estimator-summary::after { transform: translateY(-30%) rotate(-135deg); }
.shipping-estimator-collapse[open] > summary.shipping-estimator-summary { border-bottom: 1px solid #d3e1ef; }
.shipping-estimator-collapse .infoBoxContents,
.shipping-estimator-collapse > div { padding: 0; }
</style>

<div class="shpp-stmt">
	<?php
    $redirect = false;

	require(DIR_WS_LANGUAGES . $language .'/'. FILENAME_SHIPPING_ESTIMATOR);

	if (($cart->count_contents() > 0))
	{
		// shipping cost
		require('includes/classes/http_client.php'); // shipping in basket

		//if($cart->get_content_type() !== 'virtual') {
		if (tep_session_is_registered('customer_id'))
		{
			// user is logged in
			// user changed address
			if (isset($_POST['address_id'])) {
                $sendto = $_POST['address_id'];
                $_SESSION['estimator_sendto'] = $sendto;
                $redirect = true;
            }
			// user once changed address
			elseif (tep_session_is_registered('cart_address_id')) {
                $sendto = $cart_address_id;
            }
			// first timer
			else {
                if (!$sendto) {
                    $sendto = $_POST['customer_default_address_id'];
                }
            }

            if (!$sendto && isset($_SESSION['estimator_sendto'])) {
                $sendto = $_SESSION['estimator_sendto'];
                $_SESSION['sendto'] = $sendto;
            }

			// set session now
			$cart_address_id = $sendto;
			tep_session_register('cart_address_id');
			// set shipping to null ! multipickup changes address to store address...
			$shipping='';
			// include the order class (uses the sendto !)
			require_once(DIR_WS_CLASSES . 'order.php');
			$order = new order;
		}
		else
		{
			// user not logged in !
			if (isset($_POST['country_id']))
			{
				// country is selected
				$country_info = tep_get_countries($_POST['country_id'],true);
				$cache_state_prov_values = tep_db_fetch_array(tep_db_query("select zone_code from " . TABLE_ZONES . " where zone_country_id = '" . (int)$_POST['country_id'] . "' and zone_id = '" . (int)$_POST['state'] . "'"));
				$cache_state_prov_code = $cache_state_prov_values['zone_code'];

				@$order->delivery = array('postcode' => $_POST['zip_code'],
					 'state' => $cache_state_prov_code,
					 'country' => array('id' => $_POST['country_id'], 'title' => $country_info['countries_name'], 'iso_code_2' => $country_info['countries_iso_code_2'], 'iso_code_3' =>  $country_info['countries_iso_code_3']),
					 'country_id' => $_POST['country_id'],
					 'zone_id' => $_POST['state'],
					 'format_id' => tep_get_address_format_id($_POST['country_id']));

				$cart_country_id = $_POST['country_id'];
				$cart_zone = $_POST['zone_id'];
				$cart_zip_code = $_POST['zip_code'];

				tep_session_register('cart_country_id');
				tep_session_register('cart_zone');
				tep_session_register('cart_zip_code');
			}
			elseif (tep_session_is_registered('cart_country_id'))
			{
				// session is available
				$country_info = tep_get_countries($cart_country_id,true);
				@$order->delivery = array('postcode' => $cart_zip_code,
					 'country' => array('id' => $cart_country_id, 'title' => $country_info['countries_name'], 'iso_code_2' => $country_info['countries_iso_code_2'], 'iso_code_3' =>  $country_info['countries_iso_code_3']),
					 'country_id' => $cart_country_id,
					 'format_id' => tep_get_address_format_id($cart_country_id));
			}
			else
			{
				// first timer
				$cart_country_id = STORE_COUNTRY;
				tep_session_register('cart_country_id');
				$country_info = tep_get_countries(STORE_COUNTRY,true);
				tep_session_register('cart_zip_code');
				@$order->delivery = array(//'postcode' => '',
					 'country' => array('id' => STORE_COUNTRY, 'title' => $country_info['countries_name'], 'iso_code_2' => $country_info['countries_iso_code_2'], 'iso_code_3' =>  $country_info['countries_iso_code_3']),
					 'country_id' => STORE_COUNTRY,
					 'format_id' => tep_get_address_format_id($_POST['country_id']));
			}
			// set the cost to be able to calculate free shipping
			/*$order->info = array(
                'total' => $cart->show_total(), // TAX ????
                'currency' => $currency,
			    'currency_value'=> $currencies->currencies[$currency]['value']
            );*/
		}
		// weight and count needed for shipping
		$total_weight = $cart->show_weight();

		$total_count = $cart->count_contents();

        //Forzamos
        if ($sendto > 0) {
            $order->cart($sendto);
        }
        
		$shipping_modules = new shipping;
		$quotes = $shipping_modules->quote();

		//$order->info['subtotal'] = $cart->total;

		// set selections for displaying
		$selected_country = $order->delivery['country']['id'];
		$selected_address = $sendto;

        if (isset($_SESSION['estimator_sendto'])) {
            $selected_address = $_SESSION['estimator_sendto'];
        }

		//}
		// eo shipping cost
        $free_shipping = false;
		// check free shipping based on order total
		if ( defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING') && (MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING == 'true'))
		{
			switch (MODULE_ORDER_TOTAL_SHIPPING_DESTINATION)
			{
				case 'national':
					if ($order->delivery['country_id'] == STORE_COUNTRY) $pass = true; break;
				case 'international':
					if ($order->delivery['country_id'] != STORE_COUNTRY) $pass = true; break;
				case 'both':
					$pass = true; break;
				default:
					$pass = false; break;
			}

			$free_shipping = false;

			if ( ($pass == true) && ($order->info['total'] >= ($customer_group_id == 0 ? MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER : MODULE_SHIPPING_FREEAMOUNT_AMOUNT_DISTRI)))
			{
				$free_shipping = true;
				include(DIR_WS_LANGUAGES . $language . '/modules/order_total/ot_shipping.php');
			}
		}
		else {
            $free_shipping = false;
        }

		if( (intval($order->delivery['country_id']) == intval(STORE_COUNTRY) || $order->delivery['country_id'] == 171) && $order->info['subtotal'] >= ($customer_group_id == 0 ? MODULE_SHIPPING_FREEAMOUNT_AMOUNT : MODULE_SHIPPING_FREEAMOUNT_AMOUNT_DISTRI) && $cart->show_weight() < MODULE_SHIPPING_FREEAMOUNT_WEIGHT_MAX && getProductFreeShippingByGeoZone() )
		{
			if( ($order->delivery['state'] != 'Las Palmas') && ($order->delivery['state'] != 'Ceuta') && ($order->delivery['state'] != 'Melilla') && ($order->delivery['state'] != 'Santa Cruz de Tenerife') )
			{
				$products_ship_free = true;
				$free_shipping = true;
				include(DIR_WS_LANGUAGES . $language . '/modules/order_total/ot_shipping.php');
			} else {
                /*
                @daniel.lucia
                #RSI-703-23357
                @free_shipping viene desde arriba a true, pero no entra en ese if porque la función getProductFreeShippingByGeoZone() da false, pero la variable no se setea a false, con lo que sigue teniendo envio gratis.
                */
				$free_shipping = false;
            }
		} else {
            /*
            @daniel.lucia
            #RSI-703-23357
            @free_shipping viene desde arriba a true, pero no entra en ese if porque la función getProductFreeShippingByGeoZone() da false, pero la variable no se setea a false, con lo que sigue teniendo envio gratis.
            */
            $free_shipping = false;
        }

		if ($free_shipping == false)
		{
			$bInFree = false;
			 $nTotalWeight = 0;
		  $check_free_shipping_basket_query = tep_db_query("select products_id from " . TABLE_CUSTOMERS_BASKET . " where customers_id = '" . (int)$customer_id . "'");
		  while ($check_free_shipping_basket = tep_db_fetch_array($check_free_shipping_basket_query)) {
			$check_free_shipping_query = tep_db_query("select products_ship_free, products_price, products_weight from " . TABLE_PRODUCTS . " where products_id = '" . (int)$check_free_shipping_basket['products_id'] . "'");
			$check_free_shipping = tep_db_fetch_array($check_free_shipping_query);

			$nTotalWeight += $check_free_shipping['products_weight'];

			if( $customer_group_id == 0 )
			{
				if( (array_key_exists( 'products_ship_free', $check_free_shipping ) && $check_free_shipping['products_ship_free'] && getProductFreeShippingByGeoZone()) || ($check_free_shipping['products_price'] >= (MODULE_SHIPPING_FREEAMOUNT_AMOUNT / 1.21) && $check_free_shipping['products_weight'] < MODULE_SHIPPING_FREEAMOUNT_WEIGHT_MAX && getProductFreeShippingByGeoZone()) )
					$bInFree = true;
			}
			else
			{
				if( $check_free_shipping['products_price'] >= (MODULE_SHIPPING_FREEAMOUNT_AMOUNT_DISTRI) && $check_free_shipping['products_weight'] < MODULE_SHIPPING_FREEAMOUNT_WEIGHT_MAX  && getProductFreeShippingByGeoZone() )
					$bInFree = true;
			}
		  }

		  if( $bInFree )
			$free_shipping = true;
		}
        
		if( $cart->get_content_type() !== 'virtual')
		{

			if (tep_not_null($_POST['sid']))
			{
				list($module, $method) = explode('_', $_POST['sid']);
				$cart_sid = $_POST['sid'];
				tep_session_register('cart_sid');

				/**
				 * FIX
				 * UCD-874-74497
				 * @author Daniel Lucia <daniel.lucia@denox.es>
				 */
				if (!tep_session_is_registered('shipping')) {
					tep_session_register('shipping');
				}
				$shipping = $module;
				$_SESSION['module_shipping_estimator'] = $module;

                saveShippingEstimator();

			}
			elseif (tep_session_is_registered('cart_sid'))
				list($module, $method) = explode('_', $cart_sid ?? '');
			else
			{
				$module="";
				$method="";
			}

            if (isset($_SESSION['module_shipping_estimator'])) {
                $module = $_SESSION['module_shipping_estimator'];
            }

			if (tep_not_null($module))
			{
				$selected_quote = $shipping_modules->quote($method, $module);
				if($selected_quote[0]['error'] || !tep_not_null($selected_quote[0]['methods'][0]['cost']))
				{
					// preselected() en lugar de cheapest() para no meter retira_retira en sesion
					$selected_shipping = $shipping_modules->preselected();
					//$order->info['shipping_method'] = $selected_shipping['title'];
					//$order->info['shipping_cost'] = $selected_shipping['cost'];
					//$order->info['total']+= $selected_shipping['cost'];
				}
				else
				{
					//$order->info['shipping_method'] = $selected_quote[0]['module'].' ('.$selected_quote[0]['methods'][0]['title'].')';
					//$order->info['shipping_cost'] = $selected_quote[0]['methods'][0]['cost'];
					//$order->info['total']+= $selected_quote[0]['methods'][0]['cost'];
					$selected_shipping['title'] = $order->info['shipping_method'];
					$selected_shipping['cost'] = $order->info['shipping_cost'];
					$selected_shipping['id'] = $selected_quote[0]['id'].'_'.$selected_quote[0]['methods'][0]['id'];
				}
			}
			else
			{
				// preselected() en lugar de cheapest() para no meter retira_retira en sesion
				$selected_shipping = $shipping_modules->preselected();
				//$order->info['shipping_method'] = $selected_shipping['title'];
				//$order->info['shipping_cost'] = $selected_shipping['cost'];
				//$order->info['total']+= $selected_shipping['cost'];
			}
		}

        if ($redirect == true) {
            tep_redirect(tep_href_link('checkout/cart/#shipping-estimator'));
        }

		// virtual products use free shipping
		if($cart->get_content_type() == 'virtual')
		{
			//$order->info['shipping_method'] = CART_SHIPPING_METHOD_FREE_TEXT . ' ' . CART_SHIPPING_METHOD_ALL_DOWNLOADS;
			//$order->info['shipping_cost'] = 0;
		}
		if($free_shipping)
		{
			//$order->info['shipping_method'] = MODULE_ORDER_TOTAL_SHIPPING_TITLE;
			//$order->info['shipping_cost'] = 0;
		}
		$shipping = $selected_shipping;
		// end of shipping cost
		// end free shipping based on order total

		$_shipEstOpen = (isset($_POST['country_id']) || isset($_POST['state']) || isset($_POST['address_id']) || isset($_POST['sid']) || isset($_POST['zip_code'])) ? ' open' : '';
		echo '<details class="shipping-estimator-collapse"' . $_shipEstOpen . '><summary class="shipping-estimator-summary" id="shipping-estimator">' . CART_SHIPPING_OPTIONS . '</summary>';

		$ShipTxt= tep_draw_form('estimator', tep_href_link(FILENAME_SHOPPING_CART, '#shipping-estimator', 'NONSSL'), 'post'); //'onSubmit="return check_form();"'
		$ShipTxt.=tep_draw_hidden_field('sid', $selected_shipping['id']);
		$ShipTxt.='<table>';
		if(sizeof($quotes))
		{

			if (tep_session_is_registered('customer_id'))
			{
				// logged in

				// if (CARTSHIP_SHOWWT == 'true')
				$showweight = ' (' . $total_weight . ' ' . CARTSHIP_WTUNIT . ')';
				if(CARTSHIP_SHOWIC == 'true'){
					//ishazer remover hard code for version 2.20 : $ShipTxt.='<tr><td class="main">' . ($total_count == 1 ? ' <b>Item:</b></td><td colspan="2" class="main">' : ' <b>' . CART_ITEM . '</b></td><td colspan="2" class="main">') . $total_count . $showweight . '</td></tr>';
					$ShipTxt.='<tr><td class="main width-shipping">' . ($total_count == 1 ? ' <b>' . CART_ITEM . '</b></td><td colspan="2" class="main">' : ' <b>' . CART_ITEM . '</b></td><td colspan="2" class="main">') . $total_count . $showweight . '</td></tr>';
				}
				$addresses_query = tep_db_query("select address_book_id, entry_city as city, entry_postcode as postcode, entry_state as state, entry_zone_id as zone_id, entry_country_id as country_id from " . TABLE_ADDRESS_BOOK . " where customers_id = '" . $customer_id . "'");
				// only display addresses if more than 1
				if (tep_db_num_rows($addresses_query) > 1)
				{
					while ($addresses = tep_db_fetch_array($addresses_query))
						$addresses_array[] = array('id' => $addresses['address_book_id'], 'text' => tep_address_format(tep_get_address_format_id($addresses['country_id']), $addresses, 0, ' ', ' '));

					$ShipTxt.='<tr><td colspan="3" class="main" nowrap>' .
					CART_SHIPPING_METHOD_ADDRESS .' '. tep_draw_pull_down_menu('address_id', $addresses_array, $selected_address, 'onchange="return shipincart_submit(\'\');"').'</td></tr>';
				}

                //Forzamos
                if ($sendto > 0) {
                    $order->cart($sendto);
                }

				$ShipTxt.='<tr valign="top"><td class="main"><b>' . CART_SHIPPING_METHOD_TO .'</b> </td><td colspan="2" class="main">'. tep_address_format($order->delivery['format_id'], $order->delivery, 1, ' ', '<br>') . '</td></tr>';

			}
			else
			{
				// not logged in
				$ShipTxt.=CART_SHIPPING_OPTIONS_LOGIN;

				if(CARTSHIP_SHOWIC == 'true')
					$ShipTxt.='<tr><td class="main width-shipping" nowrap>' . ($total_count == 1 ? ' <b>' . CART_ITEM . '</b></td><td colspan="2" class="main" nowrap>' : ' <b>' . CART_ITEM . '</b></td><td colspan="2" class="main">') . $total_count . $showweight . '</td></tr>';

				if($cart->get_content_type() != 'virtual')
				{
					if(CARTSHIP_SHOWCDD == 'true')
					{
						if ((int)$selected_country == 0) {
							$selected_country = 195;
						}
						$ShipTxt.='<tr><td colspan="4" class="main" nowrap><p class="campo flex"><label>' .
						ENTRY_COUNTRY .'</label>'. tep_get_country_list('country_id', $selected_country, ' class="load-states-shipping-estimator select2" ').'</p>';
					}

					//add state zone_id
					$state_array[] = array('id' => '', 'text' => 'Seleccione provincia de envío');
					$state_query = tep_db_query("select zone_name, zone_id from " . TABLE_ZONES . " where zone_country_id = '$selected_country' order by zone_country_id DESC, zone_name");
					while ($state_values = tep_db_fetch_array($state_query))
					{
						$state_array[] = array('id' => $state_values['zone_id'],
							 'text' => $state_values['zone_name']);
					}

					if(CARTSHIP_SHOWSDD == 'true')
						$ShipTxt.='<p class="campo flex"><label>' . ENTRY_STATE .'</label><span class="states-shipping-estimator">'. tep_draw_pull_down_menu('state', $state_array, '', ' class="select2" ').'</span></p>';

					if(CARTSHIP_SHOWZDD == 'true')
						$ShipTxt.=ENTRY_POST_CODE .' '. tep_draw_input_field('zip_code', $selected_zip, 'size="5"');

					if(CARTSHIP_SHOWUB == 'true')
						$ShipTxt.=' <div class="botonera"><a href="_" onclick="return shipincart_submit(\'\');">'. tep_image_button('button_update_cart.gif', CART_SHIPPING_METHOD_RECALCULATE,'') . ' </a></div></td></tr>';
				}
			}
			if($cart->get_content_type() == 'virtual') {
                $ShipTxt.='<tr><td class="main" colspan="4"> </td></tr><tr><td class="main" colspan="4"><i>' . CART_SHIPPING_METHOD_FREE_TEXT . ' ' . CART_SHIPPING_METHOD_ALL_DOWNLOADS . '</i></td></tr>';
            }
			/*elseif ($free_shipping==1) {
                $ShipTxt.='<tr><td class="main" colspan="4"> </td></tr><tr><td class="main" colspan="3"><i>' . sprintf(FREE_SHIPPING_DESCRIPTION, $currencies->format(MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER)) . '</i></td><td> </td></tr>';
            }*/
			else
			{
				// shipping display
				if ( empty($quotes[0]['error']) || (!empty($quotes[1])&&empty($quotes[1]['error'])) )
				{
					$ShipTxt.='<tr><td colspan="4" class="main"> </td></tr><tr><td class="main" width="40"><b>' . CART_SHIPPING_CARRIER_TEXT . '</b></td><td class="main" align="left" colspan="2"><b>' . CART_SHIPPING_METHOD_TEXT . '</b></td><td class="main" align="right"><b>' . CART_SHIPPING_METHOD_RATES . '</b></td></tr>';
					$ShipTxt.='<tr><td colspan="4" class="main" backgound="#f3f4f8"><div style="background-color: #f3f4f8; height: 1px; width: auto;"></div></td></tr>';

					// BOF added to Display Message when No Shipping Options are Available
					$at_least_one_quote_printed = false;
					// EOF added to Display Message when No Shipping Options are Available
				}
				else
					$ShipTxt.='<tr><td colspan="4" class="main"> </td></tr>';

				for ($i=0, $n=sizeof($quotes); $i<$n; $i++)
				{
					if(!is_array($quotes[$i]['methods'] ?? null)) continue;
					if(sizeof($quotes[$i]['methods'])==1)
					{
						// simple shipping method
						$thisquoteid = $quotes[$i]['id'].'_'.$quotes[$i]['methods'][0]['id'];
						$ShipTxt.= '<tr class="'.$extra.'">';
						$ShipTxt.='<td class="main image" width="40">'.$quotes[$i]['icon'].' </td>';
						if($quotes[$i]['error'])
						{
							$ShipTxt.='<td colspan="2" class="main">'.$quotes[$i]['module'].' ';
							$ShipTxt.= '('.$quotes[$i]['error'].')</td><td> </td></tr>';
						}else
						{
							if($selected_shipping['id'] == $thisquoteid)
							{
								// commented for v2.10 : $ShipTxt.='<td class="main"><a title="Select this method" href="_"  onclick="return shipincart_submit(\''.$thisquoteid.'\');"><b>'.$quotes[$i]['module'].' ';
								$ShipTxt.='<td class="main" colspan="2"><a class="shipping-selected" title="' . CART_SELECT_THIS_METHOD .'" href="_"  onclick="return shipincart_submit(\''.$thisquoteid.'\');"><b>'.$quotes[$i]['module'].' ';

								$ShipTxt.= '('.$quotes[$i]['methods'][0]['title'].')</b></a>   </td><td align="right" class="main"><b>'.$currencies->format(tep_add_tax($quotes[$i]['methods'][0]['cost'], $quotes[$i]['tax'])).'</b></td></tr>';
							}else
							{
								// commented for v2.10 : $ShipTxt.='<td class="main"><a title="Select this method" href="_" onclick="return shipincart_submit(\''.$thisquoteid.'\');">'.$quotes[$i]['module'].' ';
								$ShipTxt.='<td class="main" colspan="2"><a title="' . CART_SELECT_THIS_METHOD .'" href="_" onclick="return shipincart_submit(\''.$thisquoteid.'\');">'.$quotes[$i]['module'].' ';

								if ($quotes[$i]['methods'][0]['title'] == '') {
									$ShipTxt.= '</a>   </td><td align="right" class="main">'.$currencies->format(tep_add_tax($quotes[$i]['methods'][0]['cost'], $quotes[$i]['tax'])).'</td></tr>';
								} else {
									$ShipTxt.= '('.trim($quotes[$i]['methods'][0]['title']).')</a>   </td><td align="right" class="main">'.$currencies->format(tep_add_tax($quotes[$i]['methods'][0]['cost'], $quotes[$i]['tax'])).'</td></tr>';
								}

							}
						}
						// BOF added to Display Message when No Shipping Options are Available
						$at_least_one_quote_printed = true;
						// EOF added to Display Message when No Shipping Options are Available
					}
					elseif(sizeof($quotes[$i]['methods'])>1)
					{
						// shipping method with sub methods (multipickup)
						for ($j=0, $n2=sizeof($quotes[$i]['methods']); $j<$n2; $j++)
						{
							$thisquoteid = $quotes[$i]['id'].'_'.$quotes[$i]['methods'][$j]['id'];
							$ShipTxt.= '<tr class="'.$extra.'">';
							$ShipTxt.='<td class="main">'.$quotes[$i]['icon'].'   </td>';
							if($quotes[$i]['error'])
							{
								$ShipTxt.='<td colspan="2" class="main">'.$quotes[$i]['module'].' ';
								$ShipTxt.= '('.$quotes[$i]['error'].')</td></tr>';
							}else
							{
								if($selected_shipping['id'] == $thisquoteid){
									// commented for v2.10 :  $ShipTxt.='<td class="main"><a title="Select this method" href="_" onclick="return shipincart_submit(\''.$thisquoteid.'\');"><b>'.$quotes[$i]['module'].' ';
									$ShipTxt.='<td class="main"><a title="' . CART_SELECT_THIS_METHOD .'" href="_" onclick="return shipincart_submit(\''.$thisquoteid.'\');"><b>'.$quotes[$i]['module'].' ';

									$ShipTxt.= '('.$quotes[$i]['methods'][$j]['title'].')</b></a>   </td><td align="right" class="main"><b>'.$currencies->format(tep_add_tax($quotes[$i]['methods'][$j]['cost'], $quotes[$i]['tax'])).'</b></td><td class="main">x'.tep_image(DIR_WS_ICONS . 'selected.gif', 'Selected').'</td></tr>';
								}else{
									// commented for v2.10 :   $ShipTxt.='<td class="main"><a title="Select this method" href="_" onclick="return shipincart_submit(\''.$thisquoteid.'\');">'.$quotes[$i]['module'].' ';
									$ShipTxt.='<td class="main"><a title="' . CART_SELECT_THIS_METHOD .'" href="_" onclick="return shipincart_submit(\''.$thisquoteid.'\');">'.$quotes[$i]['module'].' ';

									$ShipTxt.= '('.$quotes[$i]['methods'][$j]['title'].')</a>   </td><td align="right" class="main">'.$currencies->format(tep_add_tax($quotes[$i]['methods'][$j]['cost'], $quotes[$i]['tax'])).'</td><td class="main"> </td></tr>';
								}
							}
						}
						// added to Display Message when No Shipping Options are Available
						$at_least_one_quote_printed = true;
					}
				}
				// added to Display Message when No Shipping Options are Available
				if (!$at_least_one_quote_printed)
					$ShipTxt.= '<tr><td colspan="4" class="main" align="center">'.SHIPPING_ESTIMATOR_NO_OPTIONS_MESSAGE.'</td></tr>';
			}
		}
		$ShipTxt.= '</table></form>';

		$info_box_contents = array();
		$info_box_contents[] = array('text' => $ShipTxt);
		new infoBox($info_box_contents);
		echo '</details>';

		if (CARTSHIP_SHOWOT == 'true')
		{
			// BOF get taxes if not logged in
			if (!tep_session_is_registered('customer_id'))
			{
				$products = $cart->get_products();
				for ($i=0, $n=sizeof($products); $i<$n; $i++)
				{
					$products_tax = tep_get_tax_rate($products[$i]['tax_class_id'], $order->delivery['country_id'],$order->delivery['zone_id']);
					$products_tax_description = tep_get_tax_description($products[$i]['tax_class_id'], $order->delivery['country_id'], $order->delivery['zone_id']);
					if (DISPLAY_PRICE_WITH_TAX == 'true') {
						//Modified by Strider 42 to correct the tax calculation when a customer is not logged in
						// $tax_val = ($products[$i]['final_price']-(($products[$i]['final_price']*100)/(100+$products_tax)))*$products[$i]['quantity'];
						$tax_val = (($products[$i]['final_price']/100)*$products_tax)*$products[$i]['quantity'];
					}
					else
						$tax_val = (($products[$i]['final_price']*$products_tax)/100)*$products[$i]['quantity'];

					//$order->info['tax'] += $tax_val;
					//$order->info['tax_groups']["$products_tax_description"] += $tax_val;
					// Modified by Strider 42 to correct the order total figure when shop displays prices with tax
					//if (DISPLAY_PRICE_WITH_TAX == 'true')
					//	$order->info['total'];
					//else
					//	$order->info['total']+=$tax_val;

				}
			}
			// EOF get taxes if not logged in (seems like less code than in order class)

			//echo '</td><td align="right">';
			// order total code


			//$info_box_contents = array();
			//$info_box_contents[] = array('text' => '<div class="titu1">' . CART_OT . '</div>'); //azer version 2.20

			//new infoBoxHeading($info_box_contents, false, false);
            /**
             * FIX
             * UCD-874-74497
             * @author Daniel Lucia <daniel.lucia@denox.es>
             */
            //saveShippingStimator();

            //$order_total_modules = new order_total;
            //$order_total_modules->process();

			//echo '<div id="total-shipping-stimator"><table align="right">' . $order_total_modules->output(true, true) . '</table></div>';
		}
	} // Use only when cart_contents > 0

	?>
</div>
