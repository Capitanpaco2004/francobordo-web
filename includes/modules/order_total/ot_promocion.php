<?php

class ot_promocion {
	public $title, $output;
	public $code = 'ot_promocion';
	public $description;
	public $enabled;
	public $sort_order;
	public $tax_class;
	public $check;

	public function __construct() {
		global $payment, $customer_group_id;

		$this->code        = 'ot_promocion';
		$this->title       = MODULE_ORDER_TOTAL_PROMOCION_TITLE;
		$this->description = MODULE_ORDER_TOTAL_PROMOCION_DESCRIPTION;
		$this->enabled     = ((MODULE_ORDER_TOTAL_PROMOCION_STATUS == 'true') ? true : false);
		$this->sort_order  = MODULE_ORDER_TOTAL_PROMOCION_SORT_ORDER;
		$this->tax_class   = (defined('MODULE_ORDER_TOTAL_PROMOCION_TAX_CLASS') ? MODULE_ORDER_TOTAL_PROMOCION_TAX_CLASS : 1);
		$this->output      = [];

		if ($customer_group_id != 0) {
			$this->enabled = false;
		}

	}

	public function process() {
		global $order, $ot_subtotal, $currencies, $cart;

		$aProducts = $cart->get_products();
		$nResult   = 0;

		// Recorremos productos del carrito
		foreach ($aProducts as $nIdProduct => $aProduct) {
			if (!empty($cart->contents[$aProduct['id']]['promotion'])) {
				foreach ($cart->contents[$aProduct['id']]['promotion'] as $aPromotion) {
					if (!is_array($aPromotion)) continue;
					$nQty      = $aPromotion['qty'] ?? 0;
					$nDiscount = $aPromotion['discount'] ?? 0;

					if ($nDiscount > 0 && $nQty > 0) {
						if ($aPromotion['type'] == 'percent') {
							$nPrecioFinal = $aProduct['final_price'] * (tep_get_tax_rate($aProduct['tax_class_id']) / 100 + 1);
							$nResult      += (($nPrecioFinal * $nDiscount) / 100) * $nQty;
						} else if ($aPromotion['type'] == 'fixed') {
							$nPrecioFinal = $aProduct['final_price'] * (tep_get_tax_rate($aProduct['tax_class_id']) / 100 + 1);
							$nResult      += $nDiscount * $nQty;
						}
					}
				}
			}

		}

		if ($nResult > 0) {
			$this->output[] = ['title' => $this->title . ':',
							   'text'  => '-' . $currencies->format($nResult),
							   'value' => $nResult];

			$order->info['total'] = $order->info['total'] - $nResult;
		}
	}

	public function check() {
		if (!isset($this->check)) {
			$check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_ORDER_TOTAL_PROMOCION_STATUS'");
			$this->check = tep_db_num_rows($check_query);
		}

		return $this->check;
	}

	public function install() {
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Mostrar en la totalizacion', 'MODULE_ORDER_TOTAL_PROMOCION_STATUS', 'true', 'Quiere mostrar el descuento por promocion?', '6', '1','tep_cfg_select_option(array(\'true\', \'false\'), ', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Orden', 'MODULE_ORDER_TOTAL_PROMOCION_SORT_ORDER', '10', 'Orden al mostrarse', '6', '2', now())");
	}

	public function remove() {
		$keys       = '';
		$keys_array = $this->keys();
		for ($i = 0; $i < sizeof($keys_array); $i++) {
			$keys .= "'" . $keys_array[$i] . "',";
		}

		$keys = substr($keys, 0, -1);

		tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in (" . $keys . ")");
	}

	public function keys() {
		return ['MODULE_ORDER_TOTAL_PROMOCION_STATUS', 'MODULE_ORDER_TOTAL_PROMOCION_SORT_ORDER'];
	}
}
