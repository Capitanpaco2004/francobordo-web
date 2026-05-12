<?php
/*
  $Id: checkout_process.php 1750 2007-12-21 05:20:28Z hpdl $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2012 osCommerce

  Released under the GNU General Public License
*/

  include('includes/application_top.php');

// if the customer is not logged on, redirect them to the login page
if (!tep_session_is_registered('customer_id')) {
    $navigation->set_snapshot(array('mode' => 'SSL', 'page' => FILENAME_CHECKOUT_PAYMENT));

    // Si no es ajax
    if (!isAjax()) {
        tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
    } elseif (isAjax() && preg_match('/checkout\./i', $_SERVER['HTTP_REFERER'])) {
        die('fail:login');
    }

}

// if there is nothing in the customers cart, redirect them to the shopping cart page
if ($cart->count_contents() < 1) {
    // Si no es ajax
    if (!isAjax()) {
        tep_redirect(tep_href_link(FILENAME_SHOPPING_CART));
    } elseif (isAjax() && preg_match('/checkout\./i', $_SERVER['HTTP_REFERER'])) {
        die('fail:shopping_cart_content');
    }

}

// if no shipping method has been selected, redirect the customer to the shipping method selection page
if (!tep_session_is_registered('shipping') || !tep_session_is_registered('sendto')) {
    // Si no es ajax
    if (!isAjax()) {
        tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
    } elseif (isAjax() && preg_match('/checkout\./i', $_SERVER['HTTP_REFERER'])) {
        die('Debes seleccionar un método de envío');
    }

}

if ((tep_not_null(MODULE_PAYMENT_INSTALLED)) && (!tep_session_is_registered('payment'))) {
    // Si no es ajax
    if (!isAjax()) {
        tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
    } elseif (isAjax() && preg_match('/checkout\./i', $_SERVER['HTTP_REFERER'])) {
        die('Debes seleccionar un método de pago');
    }

}

// avoid hack attempts during the checkout procedure by checking the internal cartID
if (isset($cart->cartID) && tep_session_is_registered('cartID')) {
    if ($cart->cartID != $cartID) {
        // Si no es ajax
        if (!isAjax()) {
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
        } elseif (isAjax() && preg_match('/checkout\./i', $_SERVER['HTTP_REFERER'])) {
            die('fail:cardID');
        }

    }
}

if (!tep_session_is_registered('comments')) {
    tep_session_register('comments');
}

$comments = '';
if (tep_not_null($_POST['comments'])) {
    $comments = tep_db_prepare_input($_POST['comments']);
}

  include(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CHECKOUT_PROCESS);
/*
// Politica de seguridad
if (isset($_SERVER['HTTP_REFERER']) && preg_match('/' . preg_replace('/\..*$/i', '', $_SERVER['HTTP_HOST']) . '/', $_SERVER['HTTP_REFERER'])) {
    $termsAgree = $rgpd->postFormCheckTermsGeneral();

    // Politica de privacidad
    if ($termsAgree == '') {
        // Si no es ajax
        if (!isAjax()) {
            $messageStack->addSession('error_politica', ERROR_POLITICA, 'error');
            tep_redirect(tep_href_link('checkout_confirmation.php'));
        } elseif (isAjax() && preg_match('/checkout\./i', $_SERVER['HTTP_REFERER'])) // Si es ajax es checkout_one_page
        {
            // Mostramos error
            echo ERROR_POLITICA;
            exit();
        }
    }
}
*/
// load selected payment module
  require(DIR_WS_CLASSES . 'payment.php');
  $payment_modules = new payment($payment);

// load the selected shipping module
  require(DIR_WS_CLASSES . 'shipping.php');
  $shipping_modules = new shipping($shipping);

  require(DIR_WS_CLASSES . 'order.php');
  $order = new order;

// Stock Check
$any_out_of_stock = false;
if (STOCK_CHECK == 'true') {
    for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
        if (tep_check_stock($order->products[$i]['id'], $order->products[$i]['qty'])) {
            $any_out_of_stock = true;
        }
    }
    // Out of Stock
    if ((STOCK_ALLOW_CHECKOUT != 'true') && ($any_out_of_stock == true)) {
        tep_redirect(tep_href_link(FILENAME_SHOPPING_CART));
    }
}

$payment_modules->update_status();

  if ( ($payment_modules->selected_module != $payment) || ( is_array($payment_modules->modules) && (sizeof($payment_modules->modules) > 1) && !is_object($$payment) ) && (!$customer_shopping_points_spending) || (is_object($$payment) && ($$payment->enabled == false)) ) {
    tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode(ERROR_NO_PAYMENT_MODULE_SELECTED), 'SSL'));
}

  require(DIR_WS_CLASSES . 'order_total.php');
  $order_total_modules = new order_total;

$order_totals = $order_total_modules->process();

// load the before_process function from the payment modules
$payment_modules->before_process();

//  ------------------------------------------------------------------------------------------
// test last orders_id
$_oders_max_query = tep_db_query("select max(orders_id) as max_id from " . TABLE_ORDERS . "");
$_oders_max = tep_db_fetch_array($_oders_max_query);
$_orders_id = $_oders_max["max_id"];

// test last holding_orders_id
$holding_oders_max_query = tep_db_query("select max(orders_id) as max_id from " . TABLE_HOLDING_ORDERS . "");
$holding_oders_max = tep_db_fetch_array($holding_oders_max_query);
$holding_insert_id = $holding_oders_max["max_id"];

// assign last orders_in to prevent duplicate entry
$insert_id = ($_orders_id >= $holding_insert_id) ? $_orders_id + 1 : $holding_insert_id + 1;

$saveLog = false;

if ($saveLog) {
    $txt = json_encode($order_totals);
    $txt .= "\n----------------\n";
    $txt = json_encode($order);
    $txt .= "\n----------------\n";
    $txt .= json_encode($_SERVER);
    $txt .= "\n----------------\n";
    $txt .= json_encode($_SESSION);
    file_put_contents(sprintf('temp/log-orders/%d.log', $insert_id), $txt);
}

if (isset($_SERVER['HTTP_REFERER']) && preg_match('/' . preg_replace('/\..*$/i', '', $_SERVER['HTTP_HOST']) . '/', $_SERVER['HTTP_REFERER'])) {

    /**
     * @author Daniel Lucia <daniel.lucia@denox.es>
     * Si no tenemos ot_shipping.
     */
    /*$aEmailsError = array(
        'daniel.lucia@denox.es',
        //'info@francobordo.com'
    );
    $bOrderTotalShipping = false;
    foreach ($order_totals as $order_total) {
        if ($order_total['code'] == 'ot_shipping') {
            $bOrderTotalShipping = true;
        }
    }

    if ($bOrderTotalShipping == false) {
        foreach ($aEmailsError as $sEmail) {
            tep_mail(
                'francobordo',
                $sEmail,
                'Ha ocurrido un error en un pedido, no tenia forma de envio.',
                sprintf('El cliente %s no tiene forma de envío y no ha podido terminar el pedido.', $order->customer['email_address']) . '<pre>' . print_r($_SERVER, 1) . '</pre>' . '<pre>' . print_r($_SESSION, 1) . '</pre>',
                STORE_OWNER,
                STORE_OWNER_EMAIL_ADDRESS
            );
        }
    }*/

    /**
     * @author Daniel Lucia <daniel.lucia@denox.es>
     * Revisamos que tenga title la forma de envio.
     * En algún punto de la tienda, pierde este valor
     * con lo que luego no lo tiene en cuenta en la
     * totalización.
     *
     * #TCU-804-51322
     */

    if (!empty($_SESSION['shipping'])) {
        if ($_SESSION['shipping']['title'] == '') {
            $_SESSION['shipping']['title'] = 'Gastos de envío';
        }
    }
}


// End OrderCheck
//  ------------------------------------------------------------------------------------------


$sql_data_array = array('orders_id' => $insert_id,
    'customers_id' => $customer_id,
    'customers_name' => $order->customer['firstname'] . ' ' . $order->customer['lastname'],
    'customers_company' => $order->customer['company'],
    'customers_street_address' => $order->customer['street_address'],
    'customers_suburb' => $order->customer['suburb'],
    'customers_city' => $order->customer['city'],
    'customers_postcode' => $order->customer['postcode'],
    'customers_state' => $order->customer['state'],
    'customers_country' => $order->customer['country']['title'],
    'customers_telephone' => $order->customer['telephone'],
    'customers_email_address' => $order->customer['email_address'],
    'customers_address_format_id' => $order->customer['format_id'],
    'delivery_name' => trim($order->delivery['firstname'] . ' ' . $order->delivery['lastname']),
    'delivery_company' => $order->delivery['company'],
    //NIF start
    'billing_nif' => $order->billing['nif'],
    //NIF end
    'delivery_street_address' => $order->delivery['street_address'],
    'delivery_telephone' => ($order->delivery['telephone'] != '' ? $order->delivery['telephone'] : $order->customer['telephone']),
    'delivery_suburb' => $order->delivery['suburb'],
    'delivery_city' => $order->delivery['city'],
    'delivery_postcode' => $order->delivery['postcode'],
    'delivery_state' => $order->delivery['state'],
    'delivery_country' => $order->delivery['country']['title'],
    'delivery_address_format_id' => $order->delivery['format_id'],
    'billing_name' => $order->billing['firstname'] . ' ' . $order->billing['lastname'],
    'billing_company' => $order->billing['company'],
    'billing_street_address' => $order->billing['street_address'],
    'billing_suburb' => $order->billing['suburb'],
    'billing_city' => $order->billing['city'],
    'billing_postcode' => $order->billing['postcode'],
    'billing_state' => $order->billing['state'],
    'billing_country' => $order->billing['country']['title'],
    'billing_address_format_id' => $order->billing['format_id'],
    'payment_method' => $order->info['payment_method'],
// BOF: Order Editor
    'shipping_module' => $shipping['id'],
// EOF: Order Editor
    'cc_type' => $order->info['cc_type'],
    'cc_owner' => $order->info['cc_owner'],
    'cc_number' => $order->info['cc_number'],
    'cc_expires' => $order->info['cc_expires'],
    'date_purchased' => 'now()',
    'orders_status' => $order->info['order_status'],
    'currency' => $order->info['currency'],
    'currency_value' => $order->info['currency_value']);

// Inicio, tiendas
if (tep_session_is_registered('store_id')) {
    $sql_data_array['id_store'] = $store_id;

     /**
     * Cambiamos la dirección del cliente
     * #IQA-149-71728
     * @author Daniel Lucia <daniel.lucia@denox.es>
     */

    if ($shipping['id'] == 'retira_retira') {
        switch ((int) $store_id) {
            case 1:
                $sql_data_array['delivery_street_address'] = 'Calle San Rafael 8';
                $sql_data_array['delivery_postcode'] = '28108';
                $sql_data_array['delivery_city'] = 'Alcobendas';
                $sql_data_array['delivery_state'] = 'Madrid';
                $sql_data_array['delivery_company'] = 'Francobordo Artículos Náuticos';
            break;

            case 2:
                $sql_data_array['delivery_street_address'] = 'Marina de Denia, Edif. H, Local 3';
                $sql_data_array['delivery_postcode'] = '03700 ';
                $sql_data_array['delivery_city'] = 'Denia';
                $sql_data_array['delivery_state'] = 'Alicante';
                $sql_data_array['delivery_company'] = 'Velas y Viento';
            break;
        }

        $aDatos = tep_db_query('select store_name, store_address from store where id_store = "' . (int) $store_id . '"');

        if (tep_db_num_rows($aDatos) > 0) {
            $aDato = tep_db_fetch_array($aDatos);
            $comments .= '<br>Recogida en tienda: (' . $aDato['store_name'] . ', ' . $aDato['store_address'] . ')';
        }
    }

}

// Fin, tiendas

tep_db_perform(TABLE_ORDERS, $sql_data_array);
//  OrderCheck
// commented out the line below
//  $insert_id = tep_db_insert_id();

/**
 * XCC-313-91043
 * @author Daniel Lucia <daniel.lucia@denox.es>
 */
Affiliates::generateOrder($order, intval($insert_id));

if (preg_match('/UPS \(UPS\)/i', $order->info['shipping_method'])) {$upsshipping->update_order($insert_id, $cartID);}

for ($i = 0, $n = sizeof($order_totals); $i < $n; $i++) {
    $sql_data_array = array('orders_id' => $insert_id,
        'title' => $order_totals[$i]['title'],
        'text' => $order_totals[$i]['text'],
        'value' => $order_totals[$i]['value'],
        'class' => $order_totals[$i]['code'],
        'sort_order' => $order_totals[$i]['sort_order']);
    tep_db_perform(TABLE_ORDERS_TOTAL, $sql_data_array);
}
#### Points/Rewards Module V2.1rc2a balance customer points BOF ####
if ((USE_POINTS_SYSTEM == 'true') && (USE_REDEEM_SYSTEM == 'true') && ($customer_group_id == '0')) {
// customer pending points added
    if ($order->info['total'] > 0) {
        $points_toadd = get_points_toadd($order);
        $points_comment = 'TEXT_DEFAULT_COMMENT';
        $points_type = 'SP';
        if ((get_redemption_awards($customer_shopping_points_spending) == true) && ($points_toadd > 0)) {
            tep_add_pending_points($customer_id, $insert_id, $points_toadd, $points_comment, $points_type);
        }
    }
// customer referral points added
    if ((tep_session_is_registered('customer_referral')) && (tep_not_null(USE_REFERRAL_SYSTEM))) {
        $referral_twice_query = tep_db_query("select unique_id from " . TABLE_CUSTOMERS_POINTS_PENDING . " where orders_id = '" . (int) $insert_id . "' and points_type = 'RF' limit 1");
        if (!tep_db_num_rows($referral_twice_query)) {
            $points_toadd = USE_REFERRAL_SYSTEM;
            $points_comment = 'TEXT_DEFAULT_REFERRAL';
            $points_type = 'RF';
            tep_add_pending_points($customer_referral, $insert_id, $points_toadd, $points_comment, $points_type);
        }
    }
// customer shoppping points account balanced
    if ($customer_shopping_points_spending) {
        tep_redeemed_points($customer_id, $insert_id, $customer_shopping_points_spending);
    }
}
#### Points/Rewards Module V2.1rc2a balance customer points EOF ####*/

$customer_notification = (SEND_EMAILS == 'true') ? '1' : '0';
$sql_data_array = array('orders_id' => $insert_id,
    'orders_status_id' => $order->info['order_status'],
    'date_added' => 'now()',
    'customer_notified' => $customer_notification,
    'comments' => $comments);
tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
//kgt - discount coupons
if (tep_session_is_registered('coupon') && is_object($order->coupon)) {
    $sql_data_array = array('coupons_id' => $order->coupon->coupon['coupons_id'],
        'orders_id' => $insert_id);
    tep_db_perform(TABLE_DISCOUNT_COUPONS_TO_ORDERS, $sql_data_array);
}
//end kgt - discount coupons

// initialized for the email confirmation
$products_ordered = '';
$subtotal = 0;
$total_tax = 0;

// BOF Bundled Products
for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
// Stock Update - Joao Correia
    if (STOCK_LIMITED == 'true') {
        if (DOWNLOAD_ENABLED == 'true') {
            //$stock_query_raw = "SELECT products_quantity, pad.products_attributes_filename
            $stock_query_raw = "SELECT products_quantity, products_bundle, pad.products_attributes_filename
                            FROM " . TABLE_PRODUCTS . " p
                            LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                             ON p.products_id=pa.products_id
                            LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                             ON pa.products_attributes_id=pad.products_attributes_id
                            WHERE p.products_id = '" . tep_get_prid($order->products[$i]['id']) . "'";
// Will work with only one option for downloadable products
            // otherwise, we have to build the query dynamically with a loop
            $products_attributes = (isset($order->products[$i]['attributes'])) ? $order->products[$i]['attributes'] : '';
            if (is_array($products_attributes)) {
                $stock_query_raw .= " AND pa.options_id = '" . (int) $products_attributes[0]['option_id'] . "' AND pa.options_values_id = '" . (int) $products_attributes[0]['value_id'] . "'";
            }
            $stock_query = tep_db_query($stock_query_raw);
        } else {
            $stock_query = tep_db_query("select products_quantity, products_bundle from " . TABLE_PRODUCTS . " where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
        }
        if (tep_db_num_rows($stock_query) > 0) {
            $stock_values = tep_db_fetch_array($stock_query);
            if ($stock_values['products_bundle'] == 'yes') {
                // order item is a bundle and must be separated
                $report_text .= "Bundle found in order : " . tep_get_prid($order->products[$i]['id']) . "<br>\n";
                $bundle_query = tep_db_query("select pb.subproduct_id, pb.subproduct_qty, p.products_model, p.products_quantity, p.products_bundle
          from " . TABLE_PRODUCTS_BUNDLES . " pb
          LEFT JOIN " . TABLE_PRODUCTS . " p
          ON p.products_id=pb.subproduct_id
          where pb.bundle_id = '" . tep_get_prid($order->products[$i]['id']) . "'");

                while ($bundle_data = tep_db_fetch_array($bundle_query)) {
                    if ($bundle_data['products_bundle'] == "yes") {
                        $report_text .= "<br>level 2 bundle found in order : " . $bundle_data['products_model'] . "<br>";
                        $bundle_query_nested = tep_db_query("select pb.subproduct_id, pb.subproduct_qty, p.products_model, p.products_quantity, p.products_bundle
              from " . TABLE_PRODUCTS_BUNDLES . " pb
              LEFT JOIN " . TABLE_PRODUCTS . " p
              ON p.products_id=pb.subproduct_id
              where pb.bundle_id = '" . $bundle_data['subproduct_id'] . "'");
                        while ($bundle_data_nested = tep_db_fetch_array($bundle_query_nested)) {
                            $stock_left = $bundle_data_nested['products_quantity'] - $bundle_data_nested['subproduct_qty'] * $order->products[$i]['qty'];
                            $report_text .= "updating level 2 item " . $bundle_data_nested['products_model'] . " : was " . $bundle_data_nested['products_quantity'] . " and number ordered is " . ($bundle_data_nested['subproduct_qty'] * $order->products[$i]['qty']) . " <br>\n";
                            tep_db_query("update " . TABLE_PRODUCTS . " set products_quantity = '" . $stock_left . "' where products_id = '" . $bundle_data_nested['subproduct_id'] . "'");
                        }
                    } else {
                        $stock_left = $bundle_data['products_quantity'] - $bundle_data['subproduct_qty'] * $order->products[$i]['qty'];
                        $report_text .= "updating level 1 item " . $bundle_data['products_model'] . " : was " . $bundle_data['products_quantity'] . " and number ordered is " . ($bundle_data['subproduct_qty'] * $order->products[$i]['qty']) . " <br>\n";
                        tep_db_query("update " . TABLE_PRODUCTS . " set products_quantity = '" . $stock_left . "' where products_id = '" . $bundle_data['subproduct_id'] . "'");
                    }
                }
            } else {
                // order item is normal and should be treated as such
                $report_text .= "Normal product found in order : " . tep_get_prid($order->products[$i]['id']) . "\n";
                // do not decrement quantities if products_attributes_filename exists
                if ((DOWNLOAD_ENABLED != 'true') || (!$stock_values['products_attributes_filename'])) {
                    $stock_left = $stock_values['products_quantity'] - $order->products[$i]['qty'];
                } else {
                    $stock_left = $stock_values['products_quantity'];
                }
                tep_db_query("update " . TABLE_PRODUCTS . " set products_quantity = '" . (int) $stock_left . "', products_last_modified = now() where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
                if (($stock_left < 1) && (STOCK_ALLOW_CHECKOUT == 'false')) {
                    tep_db_query("update " . TABLE_PRODUCTS . " set products_status = '0' where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
                }
            }
        }
    }
    //EOF Bundled Products

// Update products_ordered (for bestsellers list)
    tep_db_query("update " . TABLE_PRODUCTS . " set products_ordered = products_ordered + " . sprintf('%d', $order->products[$i]['qty']) . " where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");

    $sql_data_array = array('orders_id' => $insert_id,
        'products_id' => tep_get_prid($order->products[$i]['id']),
        'products_model' => $order->products[$i]['model'],
        'products_name' => $order->products[$i]['name'],
        'products_price' => $order->products[$i]['price'],
        'final_price' => $order->products[$i]['final_price'],
        'products_tax' => $order->products[$i]['tax'],

        /**
         * #XCC-313-91043
         */
        'profit' => $order->products[$i]['profit'],
        'products_quantity' => $order->products[$i]['qty']);
    tep_db_perform(TABLE_ORDERS_PRODUCTS, $sql_data_array);
    $order_products_id = tep_db_insert_id();

//------insert customer choosen option to order--------
    $attributes_exist = '0';
    $products_ordered_attributes = '';
    if (isset($order->products[$i]['attributes'])) {
        $attributes_exist = '1';
        for ($j = 0, $n2 = sizeof($order->products[$i]['attributes']); $j < $n2; $j++) {
            if (DOWNLOAD_ENABLED == 'true') {
// START: More Product Weight
                $attributes_query = "select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pa.options_values_weight, pa.weight_prefix, pad.products_attributes_maxdays, pad.products_attributes_maxcount , pad.products_attributes_filename
                               from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                               left join " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                                on pa.products_attributes_id=pad.products_attributes_id
                               where pa.products_id = '" . (int) $order->products[$i]['id'] . "'
                                and pa.options_id = '" . (int) $order->products[$i]['attributes'][$j]['option_id'] . "'
                                and pa.options_id = popt.products_options_id
                                and pa.options_values_id = '" . (int) $order->products[$i]['attributes'][$j]['value_id'] . "'
                                and pa.options_values_id = poval.products_options_values_id
                                and popt.language_id = '" . (int) $languages_id . "'
                                and poval.language_id = '" . (int) $languages_id . "'";
// END: More Product Weight
                $attributes = tep_db_query($attributes_query);
            } else {
// START: More Product Weight
                // qfacwin attributtes
                // modif reference_attributes
                $attributes = tep_db_query("select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pa.reference, pa.products_attributes_id,  pa.options_values_weight, pa.weight_prefix from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa where pa.products_id = '" . (int) $order->products[$i]['id'] . "' and pa.options_id = '" . (int) $order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_id = popt.products_options_id and pa.options_values_id = '" . (int) $order->products[$i]['attributes'][$j]['value_id'] . "' and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . (int) $languages_id . "' and poval.language_id = '" . (int) $languages_id . "'");
// eof reference_attributes
                //eof qfacwin attributes
                // END: More Product Weight

            }
            $attributes_values = tep_db_fetch_array($attributes);
// BOF Separate Pricing Per Customer attribute_groups mod
            if (isset($_SESSION['sppc_customer_group_id']) && $_SESSION['sppc_customer_group_id'] != '0') {
                $attributes_group_query = tep_db_query("select pag.options_values_price, pag.price_prefix from " . TABLE_PRODUCTS_ATTRIBUTES . " pa left join " . TABLE_PRODUCTS_ATTRIBUTES_GROUPS . " pag using(products_attributes_id) where pa.products_id = '" . tep_get_prid($order->products[$i]['id']) . "' and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "' and pag.customers_group_id = '" . (int) $_SESSION['sppc_customer_group_id'] . "'");
                if ($attributes_group = tep_db_fetch_array($attributes_group_query)) {
                    $attributes_values['options_values_price'] = $attributes_group['options_values_price'];
                    $attributes_values['price_prefix'] = $attributes_group['price_prefix'];
                }
            }
// EOF Separate Pricing Per Customer attribute_groups mod

            $attr_name = $attributes_values['products_options_name'];

            if ($attributes_values['products_options_id'] == 'PRODUCTS_OPTIONS_VALUE_TEXT_ID') {
                $attr_name_sql_raw = 'SELECT po.products_options_name FROM ' .
                TABLE_PRODUCTS_OPTIONS . ' po, ' .
                TABLE_PRODUCTS_ATTRIBUTES . ' pa WHERE ' .
                ' pa.products_id="' . tep_get_prid($order->products[$i]['id']) . '" AND ' .
                ' pa.options_id="' . $order->products[$i]['attributes'][$j]['option_id'] . '" AND ' .
                    ' pa.options_id=po.products_options_id AND ' .
                    ' po.language_id="' . $languages_id . '" ';
                $attr_name_sql = tep_db_query($attr_name_sql_raw);
                if ($arr = tep_db_fetch_array($attr_name_sql)) {
                    $attr_name = $arr['products_options_name'];
                }
            }

            // PARCHE DEFENSIVO 2026-05-12: rellenar IDs/reference si el cart los pasó vacíos
            $_orig_opt_id        = (int)$order->products[$i]['attributes'][$j]['option_id'];
            $_orig_val_id        = (int)$order->products[$i]['attributes'][$j]['value_id'];
            $_resolved_opt_id    = $_orig_opt_id;
            $_resolved_val_id    = $_orig_val_id;
            $_resolved_reference = isset($attributes_values['reference']) ? $attributes_values['reference'] : '';
            $_resolved_ean       = isset($attributes_values['products_attributes_ean']) ? $attributes_values['products_attributes_ean'] : '';
            $_resolved_attr_id   = isset($attributes_values['products_attributes_id']) ? $attributes_values['products_attributes_id'] : 0;
            $_resolved_weight    = isset($attributes_values['options_values_weight']) ? $attributes_values['options_values_weight'] : 0;
            $_resolved_wprefix   = isset($attributes_values['weight_prefix']) ? $attributes_values['weight_prefix'] : '+';
            $_opt_name_text      = $attr_name;
            $_val_name_text      = $order->products[$i]['attributes'][$j]['value'];
            if (($_resolved_opt_id === 0 || $_resolved_val_id === 0) && tep_not_null($_opt_name_text) && tep_not_null($_val_name_text)) {
                $_resolve_q = tep_db_query("SELECT pa.options_id, pa.options_values_id, pa.products_attributes_id, pa.reference, pa.products_attributes_ean, pa.options_values_weight, pa.weight_prefix FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa JOIN " . TABLE_PRODUCTS_OPTIONS . " po ON po.products_options_id = pa.options_id JOIN " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov ON pov.products_options_values_id = pa.options_values_id WHERE pa.products_id = '" . (int)tep_get_prid($order->products[$i]['id']) . "' AND po.products_options_name = '" . tep_db_input($_opt_name_text) . "' AND pov.products_options_values_name = '" . tep_db_input($_val_name_text) . "' AND po.language_id = '" . (int)$languages_id . "' AND pov.language_id = '" . (int)$languages_id . "' LIMIT 1");
                if ($_resolved = tep_db_fetch_array($_resolve_q)) {
                    $_resolved_opt_id  = (int)$_resolved['options_id'];
                    $_resolved_val_id  = (int)$_resolved['options_values_id'];
                    if (empty($_resolved_reference)) $_resolved_reference = $_resolved['reference'];
                    if (empty($_resolved_ean))       $_resolved_ean       = $_resolved['products_attributes_ean'];
                    if (empty($_resolved_attr_id))   $_resolved_attr_id   = $_resolved['products_attributes_id'];
                    if (empty($_resolved_weight))    $_resolved_weight    = $_resolved['options_values_weight'];
                }
            }
            // LOG fallback: cuando el cart pasó IDs=0, dejar traza para diagnosticar el flujo origen
            if ($_orig_opt_id === 0 || $_orig_val_id === 0) {
                $_log_status = ($_resolved_opt_id > 0 && $_resolved_val_id > 0) ? 'RESOLVED' : 'UNRESOLVED';
                $_log_line = sprintf(
                    "[%s] src=checkout_process order=%d op_id=%d pid=%d opt=%s val=%s orig=%d/%d resolved=%d/%d status=%s referer=%s ua=%s\n",
                    date('Y-m-d H:i:s'), (int)$insert_id, (int)$order_products_id, (int)tep_get_prid($order->products[$i]['id']),
                    json_encode((string)$_opt_name_text, JSON_UNESCAPED_UNICODE),
                    json_encode((string)$_val_name_text, JSON_UNESCAPED_UNICODE),
                    $_orig_opt_id, $_orig_val_id, $_resolved_opt_id, $_resolved_val_id, $_log_status,
                    json_encode((string)($_SERVER['HTTP_REFERER'] ?? ''), JSON_UNESCAPED_UNICODE),
                    json_encode(substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120), JSON_UNESCAPED_UNICODE)
                );
                @file_put_contents(DIR_FS_DOCUMENT_ROOT . 'logs/opa_fallback.log', $_log_line, FILE_APPEND | LOCK_EX);
            }

            $sql_data_array = array('orders_id' => $insert_id,
                'orders_products_id' => $order_products_id,
                // OTF contrib begins
                //'products_options' => $attributes_values['products_options_name'],
                //'products_options_values' => $attributes_values['products_options_values_name'],
                'products_options' => $attr_name,
                'products_options_values' => $order->products[$i]['attributes'][$j]['value'],
                // OTF contrib ends
                'options_values_price' => $attributes_values['options_values_price'],
                // qfacwin attributtes
                'NIDATRIB' => $_resolved_attr_id,
                //eof qfacwin attributes
                'products_options_id' => $_resolved_opt_id,
                'products_options_values_id' => $_resolved_val_id,
                // START: More Product Weight
                //                                'price_prefix' => $attributes_values['price_prefix']);
                'price_prefix' => $attributes_values['price_prefix'],
                // PARCHE 2026-05-12: persistir reference y EAN para que "Repetir pedido" pueda resolver variantes
                'reference' => $_resolved_reference,
                'products_attributes_ean' => $_resolved_ean,
                'options_values_weight' => $_resolved_weight,
                'weight_prefix' => $_resolved_wprefix);
// END: More Product Weight

            tep_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $sql_data_array);

// modif reference_attributes
            // si l'attribut a une référence on l'ajoute ˆ modle pour reconstituer la rŽfŽrence complte
            if (isset($attributes_values['reference'])) {
                $nouvelle_reference = $order->products[$i]['model'] . "-" . $attributes_values['reference'];
                tep_db_query("update " . TABLE_ORDERS_PRODUCTS . " set products_model = '" . $nouvelle_reference . "' where orders_products_id = '" . $order_products_id . "'");
                $order->products[$i]['model'] = $nouvelle_reference;
            }
// eof reference_attributes

            if ((DOWNLOAD_ENABLED == 'true') && isset($attributes_values['products_attributes_filename']) && tep_not_null($attributes_values['products_attributes_filename'])) {
                $sql_data_array = array('orders_id' => $insert_id,
                    'orders_products_id' => $order_products_id,
                    'orders_products_filename' => $attributes_values['products_attributes_filename'],
                    'download_maxdays' => $attributes_values['products_attributes_maxdays'],
                    'download_count' => $attributes_values['products_attributes_maxcount']);
                tep_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD, $sql_data_array);
            }
            // OTF contrib begins
            //$products_ordered_attributes .= "\n\t" . $attributes_values['products_options_name'] . ' ' . $attributes_values['products_options_values_name'];
            $products_ordered_attributes .= "\n\t" . $attributes_values['products_options_name'] . ' ' . tep_decode_specialchars($order->products[$i]['attributes'][$j]['value']);
            // OTF contrib ends

        }
    }
//------insert customer choosen option eof ----
    //BEGIN SEND HTML MAIL//
    $total_weight += ($order->products[$i]['qty'] * $order->products[$i]['weight']);
    $total_tax += tep_calculate_tax($total_products_price, $products_tax) * $order->products[$i]['qty'];
    $total_cost += $total_products_price;

    $products_ordered .= $order->products[$i]['qty'] . ' x ' . $order->products[$i]['name'] . ' (' . $order->products[$i]['model'] . ') = ' . $currencies->display_price($order->products[$i]['final_price'], $order->products[$i]['tax'], $order->products[$i]['qty']) . $products_ordered_attributes . "\n";
    $products_quantity .= nl2br($order->products[$i]['qty'] . "\n");
    $products_name .= nl2br("" . $order->products[$i]['name'] . $products_ordered_attributes . "\n");

    if (!tep_not_null($order->products[$i]['model'])) {
        $products_model .= '' . EMAIL_NO_MODEL . '';
    } else {
        $products_model .= nl2br($order->products[$i]['model'] . "\n");
    }

    $products_price .= nl2br($currencies->format($order->products[$i]['final_price'] * $order->products[$i]['qty']) . "\n");

}

// Marcamos como descatalogados los productos que estén en liquidación y no tengan stock
tep_db_query('UPDATE products SET products_status = 2 WHERE products_liquidacion = 1 AND products_quantity = 0');

/**
 * XCC-313-91043
 * Recalculamos beneficios del pedido
 * @author Daniel Lucia <daniel.lucia@denox.es>
 */
Affiliates::calculateOrderProfit(intval($insert_id));

// Fecha estimada de entrega — calculateForOrder se auto-defiende si el módulo no está activo o sin instalar
if( file_exists( DIR_WS_MODULES . 'delivery_estimate/delivery_estimate.php' ) ) {
    require_once( DIR_WS_MODULES . 'delivery_estimate/delivery_estimate.php' );
    $delivery_estimate_module = new delivery_estimate();
    $delivery_estimate_module->calculateForOrder( (int)$insert_id );
}
//fec start

// EMAIL //
if (EMAIL_USE_HTML == 'true') {
    require DIR_WS_MODULES . 'UHtmlEmails/' . ULTIMATE_HTML_EMAIL_LAYOUT . '/checkout_process.php';
    $email_order = $html_email;
} else { //Send text email
    //---  End of addition: Ultimate HTML Emails  ---//
    $email_order = STORE_NAME . "\n" .
    EMAIL_SEPARATOR . "\n" .
    EMAIL_TEXT_ORDER_NUMBER . ' ' . $insert_id . "\n" .
    EMAIL_TEXT_INVOICE_URL . ' ' . tep_href_link(FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . $insert_id, 'SSL', false) . "\n" .
    EMAIL_TEXT_DATE_ORDERED . ' ' . date('d/m/Y') . "\n\n";
    if ($order->info['comments']) {
        $email_order .= tep_db_output($order->info['comments']) . "\n\n";
    }
    $email_order .= EMAIL_TEXT_PRODUCTS . "\n" .
        EMAIL_SEPARATOR . "\n" .
        $products_ordered .
        EMAIL_SEPARATOR . "\n";

    for ($i = 0, $n = sizeof($order_totals); $i < $n; $i++) {
        $email_order .= strip_tags($order_totals[$i]['title']) . ' ' . strip_tags($order_totals[$i]['text']) . "\n";
    }

    if ($order->content_type != 'virtual') {
        $email_order .= "\n" . EMAIL_TEXT_DELIVERY_ADDRESS . "\n" .
        EMAIL_SEPARATOR . "\n" .
        tep_address_label($customer_id, $sendto, 0, '', "\n") . "\n";
    }

    $email_order .= "\n" . EMAIL_TEXT_BILLING_ADDRESS . "\n" .
    EMAIL_SEPARATOR . "\n" .
    tep_address_label($customer_id, $billto, 0, '', "\n") . "\n\n";
    if (is_object($$payment)) {
        $email_order .= EMAIL_TEXT_PAYMENT_METHOD . "\n" .
            EMAIL_SEPARATOR . "\n";
        $payment_class = $$payment;
        $email_order .= $order->info['payment_method'] . "\n\n";
        if ($payment_class->email_footer) {
            $email_order .= $payment_class->email_footer . "\n\n";
        }
    }
//---  Beginning of addition: Ultimate HTML Emails  ---//
}

if (ULTIMATE_HTML_EMAIL_DEVELOPMENT_MODE === 'true') {
    //Save the contents of the generated html email to the harddrive in .htm file. This can be practical when developing a new layout.
    $TheFileName = 'Last_mail_from_checkout_process.php.htm';
    $TheFileHandle = fopen($TheFileName, 'w') or die("can't open error log file");
    fwrite($TheFileHandle, $email_order);
    fclose($TheFileHandle);
}
// Fallback si EMAIL_TEXT_SUBJECT no esta definida (contexto IPN)
if (!defined("EMAIL_TEXT_SUBJECT")) {
    define("EMAIL_TEXT_SUBJECT", "Pedido en " . STORE_NAME);
}
//---  End of addition: Ultimate HTML Emails  ---//
tep_mail($order->customer['firstname'] . ' ' . $order->customer['lastname'], $order->customer['email_address'], EMAIL_TEXT_SUBJECT, $email_order, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

// send emails to other people
if (SEND_EXTRA_ORDER_EMAILS_TO != '') {
    tep_mail('', SEND_EXTRA_ORDER_EMAILS_TO, EMAIL_TEXT_SUBJECT, $email_order, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
}

// load the after_process function from the payment modules
$payment_modules->after_process();

$cart->reset(true);

// Si es una transferencia bancaria
if (preg_match('/bancar/i', strtolower($order->info['payment_method']))) {
    tep_session_register('transferencia_bancaria');
    $transferencia_bancaria = true;
}

// unregister session variables used during checkout
tep_session_unregister('sendto');
tep_session_unregister('billto');
tep_session_unregister('shipping');
tep_session_unregister('payment');
tep_session_unregister('comments');
tep_session_unregister('store_id');
tep_session_unregister('store_cost');
tep_session_unregister('coupon');
if (tep_session_is_registered('customer_shopping_points')) {
    tep_session_unregister('customer_shopping_points');
}

if (tep_session_is_registered('customer_shopping_points_spending')) {
    tep_session_unregister('customer_shopping_points_spending');
}

if (tep_session_is_registered('customer_referral')) {
    tep_session_unregister('customer_referral');
}

if ($payment == 'servired') {
    echo "Pago Realizado";
} else {
    // Si no es ajax
    if (!isAjax()) {
        tep_redirect(tep_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
    }
}

  require(DIR_WS_INCLUDES . 'application_bottom.php');
