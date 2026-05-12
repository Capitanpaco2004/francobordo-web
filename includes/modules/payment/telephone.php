<?php
/*
$Id: telephone.php,v 1.1 2003/04/30
osCommerce, Open Source E-Commerce Solutions
http://www.oscommerce.com
Copyright (c) 2002 osCommerce
Released under the GNU General Public License
Based on Ausbank.php - Modified/translated in French by Gelong Shenphen (shenphen@dharmaling.net)
 */
class telephone
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
    public $check_languages;
// class constructor
    public function __construct()
    {
        global $order;

        $this->code = 'telephone';
        $this->title = MODULE_PAYMENT_TELEPHONE_TEXT_TITLE;
        $this->description = $this->title;
        $this->email_footer = MODULE_PAYMENT_TELEPHONE_TEXT_EMAIL_FOOTER;
        $this->sort_order = MODULE_PAYMENT_TELEPHONE_SORT_ORDER;
        $this->icon = DIR_WS_ICONS . 'phone.png'; // icon
        $this->enabled = ((MODULE_PAYMENT_TELEPHONE_STATUS == 'True') ? true : false);
        if ( defined( 'MODULE_PAYMENT_TELEPHONE_STATUS_ID' ) && (int) MODULE_PAYMENT_TELEPHONE_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_TELEPHONE_ORDER_STATUS_ID;
        }
        if (is_object($order)) {
            $this->update_status();
        }

    }
    function update_status() {
    }
// class methods
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
        return array('title' => MODULE_PAYMENT_TELEPHONE_TEXT_DESCRIPTION);
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
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_TELEPHONE_STATUS'");
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
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Permettre le paiement par téléphone', 'MODULE_PAYMENT_TELEPHONE_STATUS', 'False', 'Voulez-vous accepter les paiements par téléphone?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Número de teléfono', 'MODULE_PAYMENT_TELEPHONE_NUM', '0(033)800000000', 'Número de teléfono', '6', '1', now());");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Número de órden', 'MODULE_PAYMENT_TELEPHONE_SORT_ORDER', '0', 'Orden de clasificación de la forma de pago).', '6', '2', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Estado del pedido', 'MODULE_PAYMENT_TELEPHONE_ORDER_STATUS_ID', '0', 'Estado del pedido por defecto con esta forma de pago', '6', '3', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");

// languages
        $key_query_languages = tep_db_query("select code from " . TABLE_LANGUAGES . "");
        while ($key_languages = tep_db_fetch_array($key_query_languages)) {

            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('" . strtoupper($key_languages['code']) . " : Horario de apertura  d\'apertura', 'MODULE_PAYMENT_TELEPHONE_OUVERTURE_" . strtoupper($key_languages['code']) . "', 'De Lunes a Viernes de 10h00 a 19h30 y Sábados de 10h30  a 14h00.', 'Días y horarios de ''apertura', '6', '4', now());");
            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('" . strtoupper($key_languages['code']) . " : Detalle', 'MODULE_PAYMENT_TELEPHONE_PRECISION_" . strtoupper($key_languages['code']) . "', 'Pida hablar con un comercial y especifique el número de su pedido.', 'Aclaraciones a aportar al cliente.', '6', '4', now());");
        }
    }
    public function keys()
    {
        $keys = array();
        $key_configuration_query = tep_db_query("select configuration_key, sort_order from " . TABLE_CONFIGURATION . " where configuration_key like 'MODULE_PAYMENT_TELEPHONE_%' order by sort_order ASC");
        while ($key_configuration = tep_db_fetch_array($key_configuration_query)) {
            $keys[] = $key_configuration['configuration_key'];
        }
        return $keys;
    }

    public function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }
}
