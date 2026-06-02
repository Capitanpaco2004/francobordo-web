<?php

/*
$Id: ot_shipping.php 1739 2007-12-20 00:52:16Z hpdl $
osCommerce, Open Source E-Commerce Solutions
http://www.oscommerce.com
Copyright (c) 2007 osCommerce
Released under the GNU General Public License
 */
class ot_shipping
{
    public $title;
    public $output;
    public $code = 'ot_shipping';
    public $description;
    public $enabled;
    public $sort_order;
    public $_check;

    public function __construct()
    {
        $this->code = 'ot_shipping';
        $this->title = MODULE_ORDER_TOTAL_SHIPPING_TITLE;
        $this->description = MODULE_ORDER_TOTAL_SHIPPING_DESCRIPTION;
        $this->enabled = ((MODULE_ORDER_TOTAL_SHIPPING_STATUS == 'true') ? true : false);
        $this->sort_order = MODULE_ORDER_TOTAL_SHIPPING_SORT_ORDER;
        $this->output = array();
    }

    /**
     * Nos devuelve si tenemos o no envío gratis
     */
    public function hasFreeShipping()
    {
        // Variables
        global $cart, $order, $customer_group_id;
        $bFreeShipping = false;
        $bDestination = false;

        // Si tenemos el modulo envio gratuito activo
        if (defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING') && MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING == 'true') {
            // Comprobamos si el modulo tiene activado solo para nacional o solo internacional o ambos casos
            if (MODULE_ORDER_TOTAL_SHIPPING_DESTINATION == 'national' && $order->delivery['country_id'] == STORE_COUNTRY) {
                $bDestination = true;
            } elseif (MODULE_ORDER_TOTAL_SHIPPING_DESTINATION == 'international' && $order->delivery['country_id'] != STORE_COUNTRY) {
                $bDestination = true;
            } elseif (MODULE_ORDER_TOTAL_SHIPPING_DESTINATION == 'both') {
                $bDestination = true;
            }

            // Si el cliente es grupo 0 y tenemos destino como envio gratuito
            if ($bDestination == true && (int) $customer_group_id == 0) {
                // Obtenemos el efectivo que queda en el carrito para llegar al envío gratuito si lo tenemos configurado
                $aCashFreeShipping = $this->getCashLeftFreeShipping();

                // Si tenemos efectivo comprobamos cuanto es, si es 0 sera envío gratis
                if (is_array($aCashFreeShipping) && $aCashFreeShipping['missing_price_float'] == 0) {
                    $bFreeShipping = true;
                }
            }
        } else {
            $bFreeShipping = false;
        }
        // Si aun no tenemos envío gratutio, comprobamos los productos de la cesta
        if ($bFreeShipping == false) {
            $aProducts = $cart->get_products();

            // En el momento que un producto sea gratis <-- Tenemos que crear un config para decir como queremos esta parte si o si o si existe un producto no gratuito no sea gratis
            foreach ($aProducts as $aProduct) {
                if (!empty($aProduct['free_shipping'])) {
                    $bFreeShipping = true;
                    break;
                }
            }
        }

        // Si finalmente es envio gratuito, revisamos por último las zonas
        if ($bFreeShipping == true) {
            $bFreeShipping = getProductFreeShippingByGeoZone();
        }

        // Retornamos
        return $bFreeShipping;
    }

    /**
     * Efectivo que queda para llegar a envío gratuito
     */
    public function getCashLeftFreeShipping()
    {
        // Variables
        global $order, $currencies, $cart;
        $bDestination = false;
        $aStateToFreeCash = array();
        $nTotal = $cart->show_total();

        // Si no esta habilitado
        if (!defined('MODULE_ORDER_TOTAL_SHIPPING_STATUS') || (defined('MODULE_ORDER_TOTAL_SHIPPING_STATUS') && MODULE_ORDER_TOTAL_SHIPPING_STATUS == 'false') || (defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING') && MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING == 'false')) {
            return false;
        }

        // Si no existe la clase la añadimos
        if (!class_exists('order')) {
            include 'includes/classes/order_total.php';
        }

        // Si no existe el cliente por defecto sera España Madrid
        if (!tep_session_is_registered('customer_id') || (isset($order) && $order->delivery['zone_id'] == '')) {
            $order = isset($order) ? $order : new StdClass;
            $order->delivery = array();
            $order->delivery['zone_id'] = STORE_ZONE;
            $order->delivery['state'] = tep_get_zone_name(STORE_COUNTRY, STORE_ZONE, 'Madrid');
            $order->delivery['country']['id'] = STORE_COUNTRY;
            $order->delivery['country']['title'] = tep_get_country_name(STORE_COUNTRY);
        }

        // Comprobamos si el modulo tiene activado solo para nacional o solo internacional o ambos casos
        if (MODULE_ORDER_TOTAL_SHIPPING_DESTINATION == 'national' && $order->delivery['country']['id'] == STORE_COUNTRY) {
            $bDestination = true;
        } elseif (MODULE_ORDER_TOTAL_SHIPPING_DESTINATION == 'international' && $order->delivery['country']['id'] != STORE_COUNTRY) {
            $bDestination = true;
        } elseif (MODULE_ORDER_TOTAL_SHIPPING_DESTINATION == 'both') {
            $bDestination = true;
        }

        // Si el destino no corresponde, retornamos false
        if (!$bDestination) {
            return false;
        }

        // Obtenemos todos los keys y buscamos MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER
        foreach ($this->keys() as $sKey) {
            if (preg_match('/MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER/i', $sKey)) {
                // Sacamos el estado y si no tuviera sera el de por defecto
                $sState = str_replace('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER', '', $sKey);
                $sState = $sState == '' ? 'DEFAULT' : preg_replace('/^_/i', '', $sState);

                // Vamos guardando estado con el efectivo
                $aStateToFreeCash[strtoupper($sState)] = constant($sKey);
            }
        }

        // Por defecto
        $nFreeCash = $aStateToFreeCash['DEFAULT'];
        $keySearch = str_replace(' ', '_', strtoupper($order->delivery['state']));

        // Si encontramos el estado cambiamos valor
        if (isset($aStateToFreeCash[$keySearch])) {
            $nFreeCash = $aStateToFreeCash[$keySearch];
        }

        // Float
        $nFreeCash = (float) $nFreeCash;

        // Si tenemos cupon
        if (is_object($order->coupon)) {
            foreach ($order->coupon->applied_discount as $tax => $value) {
                $nTotal -= $value;
            }
        }

        // Calculamos
        $nMissingPrice = ($nTotal < $nFreeCash ? $nFreeCash - $nTotal : 0);

        // Retornamos
        return array('maximum_price_float' => $nFreeCash, 'maximum_price' => $currencies->format($nFreeCash, 0), 'missing_price_float' => $nMissingPrice, 'missing_price' => $currencies->format($nMissingPrice));
    }

    public function process()
    {
        global $order, $currencies, $shipping, $store_id, $customer_group_id, $bActiveCheckoutOnePage;

        if (MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING == 'true') {
            switch (MODULE_ORDER_TOTAL_SHIPPING_DESTINATION) {
                case 'national':
                    if ($order->delivery['country_id'] == STORE_COUNTRY) {
                        $pass = true;
                    }

                    /**
                     * @author Daniel Lucia <daniel.lucia@denox.es>
                     * #UVT-295-80035
                     * Deshabilitamos envios gratis a Ceuta, Melilla e islas
                     */
                    if (in_array(strtolower($order->delivery['state']), array('melilla', 'ceuta', 'las palmas', 'santa cruz de tenerife'))) {
                        $pass = false;
                    }

                    break;
                case 'international':
                    if ($order->delivery['country_id'] != STORE_COUNTRY) {
                        $pass = true;
                    }

                    break;
                case 'both':
                    $pass = true;

                    break;
                default:
                    $pass = false;

                    break;
            }

            /**
             * Deshabilitamos que fuerce el envio gratis.
             * @author Daniel Lucia <daniel.lucia@denox.es>
             * #QRD-331-33376
             */
            /*$moduleOrderTotalShippingFreeShippingOver = (intval($customer_group_id) == 0 ? MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER : 500);
            if (($pass == true) && (($order->info['total'] - $order->info['shipping_cost']) >= $moduleOrderTotalShippingFreeShippingOver)) {
            $order->info['shipping_method'] = FREE_SHIPPING_TITLE;
            $order->info['total'] -= $order->info['shipping_cost'];
            $order->info['shipping_cost'] = 0;
            }*/
        }

        if (is_array($GLOBALS['shipping'])) {
            $module = substr((string) $GLOBALS['shipping']['id'], 0, strpos((string) $GLOBALS['shipping']['id'], '_'));
        } else {
            $module = substr((string) $GLOBALS['shipping'], 0, strpos((string) $GLOBALS['shipping'], '_'));
        }

        if ($module == '') {
            $module = $GLOBALS['shipping'];
        }
        
        if (is_array($module)) {
            $module = $module['id'];
        }

        if (in_array($module, ['Normal'])) {
            $module = 'correos';
        }

        if (tep_not_null($order->info['shipping_method'])) {
            
            $shipping_tax = 0;

            // Guard PHP 8: 'freeamount' (envio gratis) no declara tax_class, y en algunos
            // flujos $GLOBALS[$module] puede no existir aun. Sin envio con coste no hay IVA
            // de envio, asi que evaluar a 0 es correcto; ademas silencia el notice.
            if (is_object($GLOBALS[$module] ?? null) && ($GLOBALS[$module]->tax_class ?? 0) > 0) {
                $shipping_tax = tep_get_tax_rate($GLOBALS[$module]->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);


                $shipping_tax_description = tep_get_tax_description($GLOBALS[$module]->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
                $order->info['tax'] += tep_calculate_tax($order->info['shipping_cost'], $shipping_tax);
                $order->info['tax_groups']["$shipping_tax_description"] += tep_calculate_tax($order->info['shipping_cost'], $shipping_tax);
                $order->info['total'] += tep_calculate_tax($order->info['shipping_cost'], $shipping_tax);
            }

            if (is_array($order->info['shipping_method'])) {
                $order->info['shipping_method'] = explode('(', $order->info['shipping_method']);
                $order->info['shipping_method'] = $order->info['shipping_method'][0];
            } else {
                $order->info['shipping_method'] = str_replace('()', '', $order->info['shipping_method']);
            }

            // Inicio, tiendas
            $sCalle = '';
            if ($shipping['id'] == 'retira_retira') {
                $aDatos = tep_db_query('select store_name, store_address from store where id_store = "' . (int) $store_id . '"');

                if (tep_db_num_rows($aDatos) > 0) {
                    $aDato = tep_db_fetch_array($aDatos);
                    $sCalle = ' (' . $aDato['store_name'] . ', ' . $aDato['store_address'] . ')';
                }
            }

            // Fin, tiendas
            
            $this->output[] = array(
                'title' => $order->info['shipping_method'] . $sCalle . ':',
                'text' => $currencies->format($order->info['shipping_cost'], true, $order->info['currency'], $order->info['currency_value']),
                'text_tax' => $currencies->format(($order->info['shipping_cost'] + tep_calculate_tax($order->info['shipping_cost'], $shipping_tax)), $order->info['currency_value']),
                'value' => $order->info['shipping_cost'],
                'value_tax' => array_key_exists('curl_oe', $_GET) ?  $order->info['shipping_cost'] : $order->info['shipping_cost'] + tep_calculate_tax($order->info['shipping_cost'], $shipping_tax),
            );
        }
    }

    public function check()
    {
        if (!isset($this->_check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_ORDER_TOTAL_SHIPPING_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    public function keys()
    {
        return array('MODULE_ORDER_TOTAL_SHIPPING_STATUS', 'MODULE_ORDER_TOTAL_SHIPPING_SORT_ORDER', 'MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING', 'MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER', 'MODULE_ORDER_TOTAL_SHIPPING_DESTINATION');
    }

    public function install()
    {
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Display Shipping', 'MODULE_ORDER_TOTAL_SHIPPING_STATUS', 'true', 'Do you want to display the order shipping cost?', '6', '1','tep_cfg_select_option(array(\'true\', \'false\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort Order', 'MODULE_ORDER_TOTAL_SHIPPING_SORT_ORDER', '2', 'Sort order of display.', '6', '2', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Allow Free Shipping', 'MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING', 'false', 'Do you want to allow free shipping?', '6', '3', 'tep_cfg_select_option(array(\'true\', \'false\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, date_added) values ('Free Shipping For Orders Over', 'MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER', '50', 'Provide free shipping for orders over the set amount.', '6', '4', 'currencies->format', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Provide Free Shipping For Orders Made', 'MODULE_ORDER_TOTAL_SHIPPING_DESTINATION', 'national', 'Provide free shipping for orders sent to the set destination.', '6', '5', 'tep_cfg_select_option(array(\'national\', \'international\', \'both\'), ', now())");
    }

    public function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }
}
