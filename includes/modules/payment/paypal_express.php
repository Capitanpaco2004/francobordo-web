<?php
/*
$Id: paypal_express.php 1803 2008-01-11 18:16:37Z hpdl $

osCommerce, Open Source E-Commerce Solutions
http://www.oscommerce.com

Copyright (c) 2008 osCommerce

Released under the GNU General Public License
 */

  class paypal_express {
    public $code;
    public $signature;
    public $title;
    public $public_title;
    public $description;
    public $sort_order;
    public $enabled;
    public $order_status;
    public $transaction_id;
    public $_check;

// class constructor
    function __construct() {
      global $order;

        $this->signature = 'paypal|paypal_express|1.0|2.2';

        $this->code = 'paypal_express';

        $this->title = defined('MODULE_PAYMENT_PAYPAL_EXPRESS_TEXT_TITLE')?MODULE_PAYMENT_PAYPAL_EXPRESS_TEXT_TITLE:'';
        $this->public_title = defined('MODULE_PAYMENT_PAYPAL_EXPRESS_TEXT_PUBLIC_TITLE')?MODULE_PAYMENT_PAYPAL_EXPRESS_TEXT_PUBLIC_TITLE:'';
        $this->description = defined('MODULE_PAYMENT_PAYPAL_EXPRESS_TEXT_DESCRIPTION')?MODULE_PAYMENT_PAYPAL_EXPRESS_TEXT_DESCRIPTION:'';
        $this->sort_order = defined('MODULE_PAYMENT_PAYPAL_EXPRESS_SORT_ORDER')?MODULE_PAYMENT_PAYPAL_EXPRESS_SORT_ORDER:'';
        $this->enabled = ((MODULE_PAYMENT_PAYPAL_EXPRESS_STATUS == 'True') ? true : false);

        /*if ($_SERVER['REMOTE_ADDR'] == '90.94.246.83') {
        $this->enabled = true;
        }*/

        if ((int) MODULE_PAYMENT_PAYPAL_EXPRESS_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_PAYPAL_EXPRESS_ORDER_STATUS_ID;
        }

        if (is_object($order)) {
            $this->update_status();
        }

    }

    // class methods
    public function update_status()
    {
        global $order;

        if (($this->enabled == true) && ((int) MODULE_PAYMENT_PAYPAL_EXPRESS_ZONE > 0)) {
            $check_flag = false;
            $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_PAYPAL_EXPRESS_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");
            while ($check = tep_db_fetch_array($check_query)) {
                if ($check['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check['zone_id'] == $order->delivery['zone_id']) {
                    $check_flag = true;
                    break;
                }
            }

            if ($check_flag == false) {
                $this->enabled = false;
            }
        }
    }

    //   function checkout_initialization_method() {
    //     global $language;

    //   if (file_exists(DIR_FS_CATALOG . 'ext/modules/payment/paypal/images/btn_express_' . basename($language) . '.gif')) {
    //    $image = 'ext/modules/payment/paypal/images/btn_express_' . basename($language) . '.gif';
    //  } else {
    //    $image = 'ext/modules/payment/paypal/images/btn_express.gif';
    //  }

    //   $string = '<a href="' . tep_href_link('ext/modules/payment/paypal/express.php', '', 'SSL') . '"><img src="' . $image . '" border="0" alt="" title="' . tep_output_string_protected(MODULE_PAYMENT_PAYPAL_EXPRESS_TEXT_BUTTON) . '" /></a>';

    //   return $string;
    //  }

    public function javascript_validation()
    {
        return false;
    }

    public function selection()
    {
        return array('id' => $this->code,
            'module' => $this->public_title);
    }

    public function pre_confirmation_check()
    {
        if (!tep_session_is_registered('ppe_token')) {
            tep_redirect(tep_href_link('ext/modules/payment/paypal/paypal_express.php', '', 'SSL'));
        }
    }

    public function confirmation()
    {
        global $comments;

        if (!isset($comments)) {
            $comments = null;
        }

        $confirmation = false;

        if (empty($comments)) {
            $confirmation = array(
                'fields' => array(
                    array(
                        'title' => MODULE_PAYMENT_PAYPAL_EXPRESS_TEXT_COMMENTS,
                        'field' => tep_draw_textarea_field('ppecomments', 'soft', '60', '5', $comments),
                    ),
                ),
            );
        }

        return $confirmation;
    }

    public function process_button()
    {
        return false;
    }

    public function before_process()
    {
        global $order, $sendto, $ppe_token, $ppe_payerid, $_POST, $comments, $customer_id;

        if (empty($comments)) {
            if (isset($_POST['ppecomments']) && tep_not_null($_POST['ppecomments'])) {
                $comments = tep_db_prepare_input($_POST['ppecomments']);

                $order->info['comments'] = $comments;
            }
        }

        if (MODULE_PAYMENT_PAYPAL_EXPRESS_TRANSACTION_SERVER == 'Live') {
            $api_url = 'https://api-3t.paypal.com/nvp';
        } else {
            $api_url = 'https://api-3t.sandbox.paypal.com/nvp';
        }

        // if( $_SERVER['REMOTE_ADDR'] == '84.123.172.164')
        // $api_url = 'https://api-3t.sandbox.paypal.com/nvp';

        $params = array(
            'USER' => MODULE_PAYMENT_PAYPAL_EXPRESS_API_USERNAME,
            'PWD' => MODULE_PAYMENT_PAYPAL_EXPRESS_API_PASSWORD,
            'VERSION' => '3.2',
            'SIGNATURE' => MODULE_PAYMENT_PAYPAL_EXPRESS_API_SIGNATURE,
            'METHOD' => 'DoExpressCheckoutPayment',
            'TOKEN' => $ppe_token,
            'PAYMENTACTION' => ((MODULE_PAYMENT_PAYPAL_EXPRESS_TRANSACTION_METHOD == 'Sale') ? 'Sale' : 'Authorization'),
            'PAYERID' => $ppe_payerid,
            'AMT' => $this->format_raw($order->info['total']),
            'CURRENCYCODE' => $order->info['currency'],
            'BUTTONSOURCE' => 'osCommerce22_Default_EC',
        );

        if (is_numeric($sendto) && ($sendto > 0)) {
            // $params['SHIPTONAME'] = $order->delivery['firstname'] . ' ' . $order->delivery['lastname'];
            // $params['SHIPTOSTREET'] = $order->delivery['street_address'];
            // $params['SHIPTOCITY'] = $order->delivery['city'];
            // $params['SHIPTOSTATE'] = tep_get_zone_code($order->delivery['country']['id'], $order->delivery['zone_id'], $order->delivery['state']);
            // $params['SHIPTOCOUNTRYCODE'] = $order->delivery['country']['iso_code_2'];
            // $params['SHIPTOZIP'] = $order->delivery['postcode'];
        }

        $post_string = '';

        foreach ($params as $key => $value) {
            $post_string .= $key . '=' . urlencode(trim($value)) . '&';
        }

        $post_string = substr($post_string, 0, -1);

        $response = $this->sendTransactionToGateway($api_url, $post_string);
        $response_array = array();
        parse_str($response, $response_array);

        if (($response_array['ACK'] != 'Success') && ($response_array['ACK'] != 'SuccessWithWarning')) {
            /**
             * Si ha dado error, envío de emails.
             * @author Daniel Lucia <daniel.lucia@denox.es>
             */
            /*$aEmailsError = array(
                'daniel.lucia@denox.es',
                //'info@francobordo.com'
            );
            foreach ($aEmailsError as $sEmail) {
                tep_mail(
                    'francobordo',
                    $sEmail,
                    'Error de paypal',
                    '<pre>' . print_r($_SERVER, 1) . '</pre>' . '<pre>' . print_r($_SESSION, 1) . '</pre>' . '<pre>' . print_r($response_array, 1) . '</pre>',
                    STORE_OWNER,
                    STORE_OWNER_EMAIL_ADDRESS
                );
            }*/
            tep_redirect(tep_href_link(FILENAME_SHOPPING_CART, 'error_message=' . stripslashes($response_array['L_LONGMESSAGE0']), 'SSL'));
        } else {
            $this->transaction_id = $response_array['TRANSACTIONID'];

            /**
             * Guardamos el movimiento
             * #QAB-417-87278
             * @author Daniel Lucia <daniel.lucia@denox.es>
             */

            $values = [
                'reference' => $this->transaction_id,
                'value' => tep_round($order->info['total'], 2),
                'customer_id' => intval($customer_id),
                'module' => 'paypal',
                'date_created' => 'now()',
            ];

            tep_db_perform(
                'redsys_payment_movements',
                $values
            );
        }
    }

    public function after_process()
    {
        global $insert_id, $customer_id;
        tep_db_query("update orders set paypal_transaction_id = '" . $this->transaction_id . "' where orders_id='" . (int) $insert_id . "'");

        /**
         * Actualizamos el movimiento con el ID de pedido.
         * #AUI-747-91109
         * @author Daniel Lucia <daniel.lucia@denox.es>
         */
        $sql = sprintf(
            'UPDATE redsys_payment_movements SET orders_id = %d WHERE customer_id = %d AND reference = "%s"',
            $insert_id,
            $customer_id,
            $this->transaction_id
        );
        tep_db_query($sql);

        tep_session_unregister('ppe_token');
        tep_session_unregister('ppe_payerid');
    }

    public function get_error()
    {
        return false;
    }

    public function check()
    {
        if (!isset($this->_check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_PAYPAL_EXPRESS_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    public function install()
    {
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable PayPal Express Checkout', 'MODULE_PAYMENT_PAYPAL_EXPRESS_STATUS', 'False', 'Do you want to accept PayPal Express Checkout payments?', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('API Username', 'MODULE_PAYMENT_PAYPAL_EXPRESS_API_USERNAME', '', 'The username to use for the PayPal API service', '6', '0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('API Password', 'MODULE_PAYMENT_PAYPAL_EXPRESS_API_PASSWORD', '', 'The password to use for the PayPal API service', '6', '0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('API Signature', 'MODULE_PAYMENT_PAYPAL_EXPRESS_API_SIGNATURE', '', 'The signature to use for the PayPal API service', '6', '0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Transaction Server', 'MODULE_PAYMENT_PAYPAL_EXPRESS_TRANSACTION_SERVER', 'Live', 'Use the live or testing (sandbox) gateway server to process transactions?', '6', '0', 'tep_cfg_select_option(array(\'Live\', \'Sandbox\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Transaction Method', 'MODULE_PAYMENT_PAYPAL_EXPRESS_TRANSACTION_METHOD', 'Sale', 'The processing method to use for each transaction.', '6', '0', 'tep_cfg_select_option(array(\'Authorization\', \'Sale\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_PAYPAL_EXPRESS_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_PAYPAL_EXPRESS_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_PAYPAL_EXPRESS_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('cURL Program Location', 'MODULE_PAYMENT_PAYPAL_EXPRESS_CURL', '/usr/bin/curl', 'The location to the cURL program application.', '6', '0' , now())");
    }

    public function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    public function keys()
    {
        return array('MODULE_PAYMENT_PAYPAL_EXPRESS_STATUS', 'MODULE_PAYMENT_PAYPAL_EXPRESS_API_USERNAME', 'MODULE_PAYMENT_PAYPAL_EXPRESS_API_PASSWORD', 'MODULE_PAYMENT_PAYPAL_EXPRESS_API_SIGNATURE', 'MODULE_PAYMENT_PAYPAL_EXPRESS_TRANSACTION_SERVER', 'MODULE_PAYMENT_PAYPAL_EXPRESS_TRANSACTION_METHOD', 'MODULE_PAYMENT_PAYPAL_EXPRESS_ZONE', 'MODULE_PAYMENT_PAYPAL_EXPRESS_ORDER_STATUS_ID', 'MODULE_PAYMENT_PAYPAL_EXPRESS_SORT_ORDER', 'MODULE_PAYMENT_PAYPAL_EXPRESS_CURL');
    }

    public function sendTransactionToGateway($url, $parameters)
    {
        $server = parse_url($url);

        if (!isset($server['port'])) {
            $server['port'] = ($server['scheme'] == 'https') ? 443 : 80;
        }

        if (!isset($server['path'])) {
            $server['path'] = '/';
        }

        if (isset($server['user']) && isset($server['pass'])) {
            $header[] = 'Authorization: Basic ' . base64_encode($server['user'] . ':' . $server['pass']);
        }

        if (function_exists('curl_init')) {
            $curl = curl_init($server['scheme'] . '://' . $server['host'] . $server['path'] . (isset($server['query']) ? '?' . $server['query'] : ''));
            curl_setopt($curl, CURLOPT_PORT, $server['port']);
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
            curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $parameters);

            $result = curl_exec($curl);

            unset($curl);
        } else {
            exec(escapeshellarg(MODULE_PAYMENT_PAYPAL_EXPRESS_CURL) . ' -d ' . escapeshellarg($parameters) . ' "' . $server['scheme'] . '://' . $server['host'] . $server['path'] . (isset($server['query']) ? '?' . $server['query'] : '') . '" -P ' . $server['port'] . ' -k', $result);
            $result = implode("\n", $result);
        }

        return $result;
    }

    // format prices without currency formatting
    public function format_raw($number, $currency_code = '', $currency_value = '')
    {
        global $currencies, $currency;

        if (empty($currency_code) || !$this->is_set($currency_code)) {
            $currency_code = $currency;
        }

        if (empty($currency_value) || !is_numeric($currency_value)) {
            $currency_value = $currencies->currencies[$currency_code]['value'];
        }

        return number_format(tep_round($number * $currency_value, $currencies->currencies[$currency_code]['decimal_places']), $currencies->currencies[$currency_code]['decimal_places'], '.', '');
    }
}
