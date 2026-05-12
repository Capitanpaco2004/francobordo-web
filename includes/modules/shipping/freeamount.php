<?php
/*
$Id$ freeamount.php 2
The Exchange Project - Community Made Shopping!
http://www.theexchangeproject.org
Copyright (c) 2003
Released under the GNU General Public License
----------------------------------------------
ane - 06/02/02 - modified freecount.php to
allow for freeshipping on minimum order amount
originally written by dwatkins 1/24/02
Modified BearHappy 09/04/04
----------------------------------------------
 */
class freeamount
{
    public $code, $title, $description, $icon, $enabled, $sort_order, $_check, $quotes, $enabled_free_shipping_product;
// class constructor
    public function __construct()
    {
        global $order, $customer, $customer_group_id, $shipping_num_boxes, $shipping_weight, $cart;
        $this->code = 'freeamount';
        $this->title = MODULE_SHIPPING_FREEAMOUNT_TEXT_TITLE;
        $this->description = MODULE_SHIPPING_FREEAMOUNT_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_SHIPPING_FREEAMOUNT_SORT_ORDER;
        $this->icon = DIR_WS_ICONS . 'freeshipping.png';
        $this->enabled = ((MODULE_SHIPPING_FREEAMOUNT_STATUS == 'True') ? true : false);
        if (($this->enabled == true) && ((int) MODULE_SHIPPING_FREEAMOUNT_ZONE > 0) && is_object($order)) {
            $check_flag = false;
            $check_query = tep_db_query("select zone_id, zone_country_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_SHIPPING_FREEAMOUNT_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");
            $order_shipping_country = $order->delivery['country']['id'];
            while ($check = tep_db_fetch_array($check_query)) {
                if ($check['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check['zone_id'] == $order->delivery['zone_id']) {
                //} elseif ($check['zone_country_id'] == $order->delivery['country']['id']) {
                    $check_flag = true;
                    break;
                }
            }

			// Envío permitido a Baleares, excepto Formentera
			if ($order->delivery['zone_id'] == 138 && ! preg_match('/(formentera)/i', $order->delivery['city'])) {
				$check_flag = true;
			}

            if ($check_flag == false) {
                $this->enabled = false;
            }

            // Si el grupo de cliente es distinto de Cliente Final, desactivar
            if ($customer_group_id != 0) {
                $this->enabled = false;
            }

        }
    }
// class methods
    public function quote($method = '')
    {
        global $order, $cart, $shipping_weight, $shipping_num_boxes;
        $dest_country = $order->delivery['country']['id'];
        $currency = $order->info['currency'];
        $get_total = false;
        $get_weight = false;
        $cart_total = $cart->show_total();
        // 2008-02-06 Joshwa
        // I rewrote this section that removes the value of any items in the card that are on
        // special because it did not take into account that items can have multiple quantities
        if (MODULE_SHIPPING_FREEAMOUNT_HIDE_SPECIALS == 'True') {
            if ($cart->count_contents() > 0) {
                $products = $cart->get_products();
                for ($i = 0, $n = sizeof($products); $i < $n; $i++) {
                    if ($special_price = tep_get_products_special_price($products[$i]['id'])) {
                        $cart_total -= ($special_price * $products[$i]['quantity']);
                    }
                }
            }
        }

        $cart_total = $cart->show_total();

        $total_weight = $shipping_num_boxes * $shipping_weight;

        //echo $cart_total.' - '.MODULE_SHIPPING_FREEAMOUNT_AMOUNT.'<br>'.$total_weight.' - '.MODULE_SHIPPING_FREEAMOUNT_WEIGHT_MAX;
        if ($cart_total < MODULE_SHIPPING_FREEAMOUNT_AMOUNT || $total_weight > 70) { /* @daniel.lucia: Comprobamos pedido y peso minimo Baleares*/
            $this->enabled = false;
		} else if (($order->delivery['zone_id'] == 138 && $cart_total < 999999) || ($total_weight > 70)) {
			$this->enabled = false;
        } else {
            $this->quotes = array( 
                'id' => $this->code,
                'module' => MODULE_SHIPPING_FREEAMOUNT_TEXT_TITLE,
                'methods' => array(
                    array(
                        'id' => $this->code,
                        'title' => MODULE_SHIPPING_FREEAMOUNT_TEXT_TITLE,
                        'cost' => MODULE_SHIPPING_FREEAMOUNT_COST,
                    ),
                ),
            );
        }

        $this->enabled_free_shipping_product = false;
        if (function_exists('getProductFreeShipping')) {
            foreach ($order->products as $product) { //Revisamos que hay productos con envio gratis
                if (getProductFreeShipping($product['id'])) {
                    $this->enabled = true;
                    $this->enabled_free_shipping_product = true;
                }
            }
        }

        // End of modification by Joshwa
        if ($cart_total < MODULE_SHIPPING_FREEAMOUNT_AMOUNT) {
            if (MODULE_SHIPPING_FREEAMOUNT_DISPLAY == 'True') {
                $this->quotes['error'] = MODULE_SHIPPING_FREEAMOUNT_TEXT_ERROR;
                $this->quotes['module'] = MODULE_SHIPPING_FREEAMOUNT_TEXT_TITLE;
            }
            $get_total = false;
        } else {
            $get_total = true;
        }
        $total_weight = $shipping_num_boxes * $shipping_weight;
        if ($total_weight > MODULE_SHIPPING_FREEAMOUNT_WEIGHT_MAX) {
            if (MODULE_SHIPPING_FREEAMOUNT_DISPLAY == 'True') {
                $this->quotes['error'] = MODULE_SHIPPING_FREEAMOUNT_TEXT_TO_HEIGHT;
                $this->quotes['module'] = MODULE_SHIPPING_FREEAMOUNT_TEXT_TITLE;
            }
            $get_weight = false;
        } else {
            $get_weight = true;
        }

        if (($get_total == true && $get_weight == true) or ($total_weight == 0) or ($this->enabled_free_shipping_product == true)) {

            $this->quotes = array(
                'id' => $this->code,
                'module' => MODULE_SHIPPING_FREEAMOUNT_TEXT_TITLE,
                'methods' => array(
                    array(
                        'id' => $this->code,
                        'title' => MODULE_SHIPPING_FREEAMOUNT_TEXT_TITLE,
                        'cost' => MODULE_SHIPPING_FREEAMOUNT_COST,
                    ),
                ),
            );
        }
        if (tep_not_null($this->icon)) {
            $this->quotes['icon'] = tep_image($this->icon, $this->title);
        }

        return $this->quotes;
    }
    public function check()
    {
        if (!isset($this->_check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SHIPPING_FREEAMOUNT_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }
    public function install()
    {
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Free Shipping with Minimum Purchase', 'MODULE_SHIPPING_FREEAMOUNT_STATUS', 'True', 'Do you want to offer minimum order free shipping?', '6', '7', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id,  sort_order, date_added) values ('Maximum Weight', 'MODULE_SHIPPING_FREEAMOUNT_WEIGHT_MAX', '10', 'What is the maximum weight you will ship?', '6', '8', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Display', 'MODULE_SHIPPING_FREEAMOUNT_DISPLAY', 'True', 'Do you want to display text way if the minimum amount is not reached?', '6', '7', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id,  sort_order, date_added) values ('Minimum Cost', 'MODULE_SHIPPING_FREEAMOUNT_AMOUNT', '50.00', 'Minimum order amount purchased before shipping is free?', '6', '8', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id,  sort_order, set_function, date_added) values ('Disable for Specials', 'MODULE_SHIPPING_FREEAMOUNT_HIDE_SPECIALS', 'True', 'Do you want to disable free shipping for products on special?', '6', '7', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort Order', 'MODULE_SHIPPING_FREEAMOUNT_SORT_ORDER', '0', 'Sort order of display.', '6', '0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Shipping Zone', 'MODULE_SHIPPING_FREEAMOUNT_ZONE', '0', 'If a zone is selected, only enable this shipping method for that zone.', '6', '0', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
    }
    public function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }
    public function keys()
    {
        $keys = array(
            'MODULE_SHIPPING_FREEAMOUNT_STATUS',
            'MODULE_SHIPPING_FREEAMOUNT_WEIGHT_MAX',
            'MODULE_SHIPPING_FREEAMOUNT_SORT_ORDER',
            'MODULE_SHIPPING_FREEAMOUNT_DISPLAY',
            'MODULE_SHIPPING_FREEAMOUNT_HIDE_SPECIALS',
            'MODULE_SHIPPING_FREEAMOUNT_AMOUNT',
            'MODULE_SHIPPING_FREEAMOUNT_AMOUNT_DISTRI',
            'MODULE_SHIPPING_FREEAMOUNT_ZONE',
        );
        return $keys;
    }
}
