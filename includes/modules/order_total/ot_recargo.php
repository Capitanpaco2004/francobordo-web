<?php

class ot_recargo {
	public $title, $output;
	public $code = 'ot_recargo';
	public $description;
	public $enabled = false;
	public $sort_order;
	public $rec;
	public $_check;

	public function __construct() {
		global $customer_id;
		$this->code        = 'ot_recargo';
		$this->title       = MODULE_ORDER_TOTAL_REC_TITLE;
		$this->description = MODULE_ORDER_TOTAL_REC_DESCRIPTION;
		$this->enabled     = false;
		if (tiene_recargo($customer_id) == 1) {
			$this->enabled = true;
		}
		$this->sort_order = MODULE_ORDER_TOTAL_REC_SORT_ORDER;
		$this->rec        = MODULE_ORDER_TOTAL_REC_VALUE;
		$this->output     = [];
	}

	public function process() {
		global $order, $currencies;

		$recargo = (($order->info['subtotal'] + $order->info['shipping_cost']) * (MODULE_ORDER_TOTAL_REC_VALUE / 100));

		$recargo_total          = (($order->info['subtotal'] + $order->info['shipping_cost']) + (float)$recargo);
		$order->info['recargo'] = (float)$recargo;

		$order->info['total'] = $order->info['total'] + (float)$recargo;

		$this->output[] = [
			'title' => MODULE_ORDER_TOTAL_REC_TITLE . ': ',
			'text'  => $currencies->format($order->info['recargo'], true, $order->info['currency'], $order->info['currency_value']),
			'value' => $order->info['recargo'],
		];

	}

	public function check() {
		if (!isset($this->_check)) {
			$check_query  = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_ORDER_TOTAL_REC_STATUS'");
			$this->_check = tep_db_num_rows($check_query);
		}

		return $this->_check;
	}

	public function install() {
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Display Tax', 'MODULE_ORDER_TOTAL_REC_STATUS', 'true', 'Do you want to display the order tax value?', '6', '1','tep_cfg_select_option(array(\'true\', \'false\'), ', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort Order', 'MODULE_ORDER_TOTAL_REC_SORT_ORDER', '3', 'Sort order of display.', '6', '2', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Recargo de equivalencia %', 'MODULE_ORDER_TOTAL_REC_VALUE', '3', 'Valor de recargo de equivalencia', '6', '4', now())");
	}

	public function remove() {
		tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
	}

	public function keys() {
		return ['MODULE_ORDER_TOTAL_REC_STATUS', 'MODULE_ORDER_TOTAL_REC_SORT_ORDER', 'MODULE_ORDER_TOTAL_REC_VALUE'];
	}
}
