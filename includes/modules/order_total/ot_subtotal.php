<?php
/*
$Id: ot_subtotal.php,v 1.7 2003/02/13 00:12:04 hpdl Exp $
osCommerce, Open Source E-Commerce Solutions
http://www.oscommerce.com
Copyright (c) 2003 osCommerce
Released under the GNU General Public License
 */
class ot_subtotal
{
    public $title;
    public $output;
    public $code = 'ot_subtotal';
    public $description;
    public $enabled;
    public $sort_order;
    public $_check;

    public function __construct()
    {
        $this->code = 'ot_subtotal';
        $this->title = MODULE_ORDER_TOTAL_SUBTOTAL_TITLE;
        $this->description = MODULE_ORDER_TOTAL_SUBTOTAL_DESCRIPTION;
        $this->enabled = ((MODULE_ORDER_TOTAL_SUBTOTAL_STATUS == 'true') ? true : false);
        $this->sort_order = MODULE_ORDER_TOTAL_SUBTOTAL_SORT_ORDER;
        $this->output = array();
    }

    public function process()
    {
        global $order, $currencies, $sppc_customer_group_id, $bActiveCheckoutOnePage;

        //Si se esta mostrando los precios con IVA en la tienda y el Grupo de Cliente tambien, restamos del subtotal
        if ((DISPLAY_PRICE_WITH_TAX == 'true' && ($_SESSION['sppc_customer_group_tax_exempt'] ?? '') != '1')) {
            $this->output[] = array(
                'title' => $this->title . ':',
                'text' => $currencies->format($order->info['subtotal'] - ($sppc_customer_group_id == 1 ? 0 : $order->info['tax']), true, $order->info['currency'], $order->info['currency_value']),
                'text_tax' => $currencies->format($order->info['subtotal'], true, $order->info['currency'], $order->info['currency_value']),
                'value' => $order->info['subtotal'] - ($sppc_customer_group_id == 1 ? 0 : $order->info['tax']),
                'value_tax' => $order->info['subtotal'],
            );
        } else {
            $this->output[] = array(
                'title' => $this->title . ':',
                'text' => $currencies->format($order->info['subtotal'], true, $order->info['currency'], $order->info['currency_value']),
                'value' => $order->info['subtotal'],
            );
        }

    }

    public function check()
    {
        if (!isset($this->_check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_ORDER_TOTAL_SUBTOTAL_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    public function keys()
    {
        return array('MODULE_ORDER_TOTAL_SUBTOTAL_STATUS', 'MODULE_ORDER_TOTAL_SUBTOTAL_SORT_ORDER');
    }

    public function install()
    {
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Display Sub-Total Excl.', 'MODULE_ORDER_TOTAL_SUBTOTAL_STATUS', 'true', 'Do you want to display the order sub-total cost Excl. ?', '6', '1','tep_cfg_select_option(array(\'true\', \'false\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort Order', 'MODULE_ORDER_TOTAL_SUBTOTAL_SORT_ORDER', '1', 'Sort order of display.', '6', '2', now())");
    }

    public function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }
}
