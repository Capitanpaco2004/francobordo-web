<?php

class ot_sequra_fee
{
    public $title, $output;
    public $code = 'ot_sequra_fee';
    public $description;
    public $enabled;
    public $sort_order;
    public $tax_class;
    public $check;
    public $deduction = 0;

    public function __construct()
    {
        $this->code = 'ot_sequra_fee';
        $this->title = defined('MODULE_OT_SEQURA_FEE_TITLE')?MODULE_OT_SEQURA_FEE_TITLE:'';
        $this->description = defined('MODULE_OT_SEQURA_FEE_DESCRIPTION')?MODULE_OT_SEQURA_FEE_DESCRIPTION:'';
        $this->enabled = defined('MODULE_OT_SEQURA_FEE_STATUS')?MODULE_OT_SEQURA_FEE_STATUS:'';
        $this->sort_order = defined('MODULE_OT_SEQURA_FEE_SORT_ORDER')?MODULE_OT_SEQURA_FEE_SORT_ORDER:'';
        $this->tax_class = defined('MODULE_OT_SEQURA_FEE_TAX_CLASS')?MODULE_OT_SEQURA_FEE_TAX_CLASS:'';

        $this->output = array();
    }

    public function withTax()
    {
        return MODULE_OT_SEQURA_FEE_AMOUNT;
    }

    public function withoutTax()
    {
        $tax_rate = tep_get_tax_rate_value(MODULE_OT_SEQURA_FEE_TAX_CLASS);
        return MODULE_OT_SEQURA_FEE_AMOUNT / $tax_rate;
    }

    public function process()
    {
        global $payment, $order, $ot_subtotal, $currencies;
        if (!isset($payment)) {
            $payment = $_SESSION['payment'] ?? '';
        }

        if ($payment != 'sequra') {
            return;
        }

        $od_amount = MODULE_OT_SEQURA_FEE_AMOUNT;
        if ($od_amount != 0) {
            $this->deduction = $od_amount;

            $this->output[] = array(
                'title' => $this->title . ':',
                'text' => $currencies->format($od_amount),
                'value' => $od_amount,
            );

            $order->info['total'] = $order->info['total'] + $od_amount;
            if ($this->sort_order < $ot_subtotal->sort_order) {
                $order->info['subtotal'] = $order->info['subtotal'] + $od_amount;
            }
        }
    }

    public function check()
    {
        if (!isset($this->check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_OT_SEQURA_FEE_STATUS'");
            $this->check = tep_db_num_rows($check_query);
        }

        return $this->check;
    }

    public function keys()
    {
        return array('MODULE_OT_SEQURA_FEE_STATUS', 'MODULE_OT_SEQURA_FEE_SORT_ORDER', 'MODULE_OT_SEQURA_FEE_AMOUNT', 'MODULE_OT_SEQURA_FEE_DESCRIPTION', 'MODULE_OT_SEQURA_FEE_TAX_CLASS');
    }

    public function install()
    {
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Display Total', 'MODULE_OT_SEQURA_FEE_STATUS', 'true', 'Apply fee?', '6', '1','tep_cfg_select_option(array(\'true\', \'false\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort Order', 'MODULE_OT_SEQURA_FEE_SORT_ORDER', '10', 'Sort order of display.', '6', '2', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Fee', 'MODULE_OT_SEQURA_FEE_AMOUNT', 1.95, 'Fee amount (tax included)', '6', '5', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Description', 'MODULE_OT_SEQURA_FEE_DESCRIPTION', 'Gastos de gestión pago en 7 días', 'Fee descriptions shown in checkout.', '6', '6', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tax Class', 'MODULE_OT_SEQURA_FEE_TAX_CLASS', '0', 'Use the following tax class on the payment charge.', '6', '7', 'tep_get_tax_class_title', 'tep_cfg_pull_down_tax_classes(', now())");
    }

    public function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }
}
