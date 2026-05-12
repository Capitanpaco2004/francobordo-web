<?php
/*
$Id: cuenta_cliente.php,v 1.1 2003/04/30
osCommerce, Open Source E-Commerce Solutions
http://www.oscommerce.com
Copyright (c) 2002 osCommerce
Released under the GNU General Public License
Based on Ausbank.php - Modified/translated in French by Gelong Shenphen (shenphen@dharmaling.net)
 */
class cuenta_cliente
{
    public $code;
    public $title;
    public $description;
    public $enabled;
    public $email_footer;
    public $sort_order;
    public $icon;
    public $order_status;
    public $check;

    public function __construct()
    {
        $this->code = 'cuenta_cliente';
        $this->title = constant('MODULE_PAYMENT_CUENTA_CLIENTE_TEXT_TITLE');
        $this->description = $this->title;
        $this->email_footer = constant('MODULE_PAYMENT_CUENTA_CLIENTE_TEXT_EMAIL_FOOTER');
        $this->sort_order = constant('MODULE_PAYMENT_CUENTA_CLIENTE_SORT_ORDER');
        $this->icon = DIR_WS_ICONS . 'cuenta_cliente.png';
        $this->enabled = ((constant('MODULE_PAYMENT_CUENTA_CLIENTE_STATUS') == 'true') ? true : false);

        if (defined('MODULE_PAYMENT_CUENTA_CLIENTE_STATUS_ID') && (int) constant('MODULE_PAYMENT_CUENTA_CLIENTE_STATUS_ID') > 0) {
            $this->order_status = constant('MODULE_PAYMENT_CUENTA_CLIENTE_ORDER_STATUS_ID');
        }

        if ($this->_allowed() && constant('MODULE_PAYMENT_CUENTA_CLIENTE_STATUS') == 'true') {
            $this->enabled = true;
        } else {
            $this->enabled = false;
        }

    }

    /**
     * Retorna si el cliente conectado
     * puede hacer uso del método de pago.
     *
     * @return boolean
     */
    private function _allowed(): bool
    {
        global $customer_id;

        $query = tep_db_query("SELECT customers_id FROM " . TABLE_CUSTOMERS . " where customers_id = '" . $customer_id . "' AND cuenta_cliente = 1");
        return tep_db_num_rows($query) > 0;
    }

    public function javascript_validation()
    {
        return false;
    }

    public function selection()
    {
        return array('id' => $this->code, 'icon' => $this->icon,
            'module' => $this->title);
    }

    public function pre_confirmation_check()
    {
        return false;
    }

    public function confirmation()
    {
        return array('title' => MODULE_PAYMENT_CUENTA_CLIENTE_TEXT_DESCRIPTION);
    }

    public function process_button()
    {
        return false;
    }

    public function before_process()
    {
        return false;
    }

    public function after_process()
    {
        return false;
    }

    public function get_error()
    {
        return false;
    }

    public function check()
    {
        if (!isset($this->check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_CUENTA_CLIENTE_STATUS'");
            $this->check = tep_db_num_rows($check_query);
        }
        return $this->check;
    }

    public function check_languages()
    {
        $check_query_languages = tep_db_query("select code from " . TABLE_LANGUAGES . "");
        $this->check_languages = tep_db_fetch_array($check_query_languages);
    }

    public function install()
    {
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Mostrar este método de pago.', 'MODULE_PAYMENT_CUENTA_CLIENTE_STATUS', 'false', 'Mostrar este método de pago cuando el cliente lo tenga activo.', '6', '0', 'tep_cfg_select_option(array(\'true\', \'false\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Número de órden', 'MODULE_PAYMENT_CUENTA_CLIENTE_SORT_ORDER', '0', 'Orden de clasificación de la forma de pago).', '6', '2', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Estado del pedido', 'MODULE_PAYMENT_CUENTA_CLIENTE_ORDER_STATUS_ID', '0', 'Estado del pedido por defecto con esta forma de pago', '6', '3', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
        tep_db_query('ALTER TABLE `customers` ADD `cuenta_cliente` TINYINT NOT NULL COMMENT \'Permitir a los cliente el método de pago \"Cuenta cliente\"\' AFTER `id_term_pivacy_general`;');
    }

    public function keys()
    {
        $keys = array();
        $key_configuration_query = tep_db_query("select configuration_key, sort_order from " . TABLE_CONFIGURATION . " where configuration_key like 'MODULE_PAYMENT_CUENTA_CLIENTE_%' order by sort_order ASC");
        while ($key_configuration = tep_db_fetch_array($key_configuration_query)) {
            $keys[] = $key_configuration['configuration_key'];
        }
        return $keys;
    }

    public function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
        tep_db_query("ALTER TABLE `customers` DROP `cuenta_cliente`;");
    }
}
