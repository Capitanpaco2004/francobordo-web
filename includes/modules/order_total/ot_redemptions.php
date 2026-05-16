<?php

class ot_redemptions {
	public $title;
	public $output;
	public $code = 'ot_redemptions';
	public $description;
	public $enabled;
	public $sort_order;
	public $_check;

	public function __construct() {
		$this->code        = 'ot_redemptions';
		$this->title       = MODULE_ORDER_TOTAL_REDEMPTIONS_TITLE;
		$this->description = MODULE_ORDER_TOTAL_REDEMPTIONS_DESCRIPTION;

		if ($this->check()) {
			$this->enabled = ((USE_REDEEM_SYSTEM == 'true') ? true : false);
		} else {
			$this->enabled = false;
		}

		$this->sort_order = MODULE_ORDER_TOTAL_REDEMPTIONS_SORT_ORDER;
		$this->output     = [];
	}

	public function check() {
		if (!isset($this->_check)) {
			$check_query  = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_ORDER_TOTAL_REDEMPTIONS_SORT_ORDER'");
			$this->_check = tep_db_num_rows($check_query);
		}
		return $this->_check;
	}

	public function process() {
		global $order, $currencies, $customer_shopping_points_spending;

		if ($customer_shopping_points_spending && isset($_SESSION['customer_shopping_points_spending'])) {
			$customer_shopping_points_spending = $_SESSION['customer_shopping_points_spending'];
		}

		if (!isset($customer_shopping_points_spending) || !is_numeric($customer_shopping_points_spending) || ($customer_shopping_points_spending <= 0)) {
			return;
		}

		// REDEEM_POINT_VALUE se interpreta como precio FINAL con IVA inc. (Opción A — el cliente ve los puntos como €€ brutos)
		$nPointValue = tep_calc_shopping_pvalue($customer_shopping_points_spending);

		// Peso bruto del carrito por tipo de IVA — para prorratear el IVA del descuento entre grupos
		$aGrupos     = [];
		$nTotalBruto = 0;
		foreach ($order->products as $aProducto) {
			$sKey = $aProducto['tax_description'];
			if (!isset($aGrupos[$sKey])) {
				$aGrupos[$sKey] = ['bruto' => 0, 'tax' => (float)$aProducto['tax']];
			}
			$nBruto = $aProducto['final_price'] * (1 + ($aProducto['tax'] / 100)) * $aProducto['qty'];
			$aGrupos[$sKey]['bruto'] += $nBruto;
			$nTotalBruto             += $nBruto;
		}

		// Bajar el IVA de cada grupo en proporción al peso bruto del grupo en el carrito
		if ($nTotalBruto > 0) {
			foreach ($aGrupos as $sKey => $aGrupo) {
				$nShare    = $nPointValue * ($aGrupo['bruto'] / $nTotalBruto);
				$nIvaShare = ($aGrupo['tax'] > 0) ? $nShare * ($aGrupo['tax'] / (100 + $aGrupo['tax'])) : 0;
				if (isset($order->info['tax_groups'][$sKey])) {
					$order->info['tax_groups'][$sKey] -= $nIvaShare;
				}
			}
		}

		$order->info['total']         -= $nPointValue;
		$order->info['payment_method'] = ($order->info['total'] > 0)
			? $order->info['payment_method'] . '+' . str_replace(':', '', TEXT_POINTS)
			: str_replace(':', '', TEXT_POINTS);

		$sFormatted = $currencies->format($nPointValue, true, $order->info['currency'], $order->info['currency_value']);

		$this->output[] = [
			'title'    => MODULE_ORDER_TOTAL_REDEMPTIONS_TEXT . ':',
			'text'     => '<span class="red">-' . $sFormatted . '</span>',
			'text_tax' => '<span class="red">-' . $sFormatted . '</span>',
			'value'    => $nPointValue,
		];
	}

	public function install() {
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort Order', 'MODULE_ORDER_TOTAL_REDEMPTIONS_SORT_ORDER', '4', 'Sort order of display.', '6', '2', now())");
	}

	public function remove() {
		tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
	}

	public function keys() {
		return ['MODULE_ORDER_TOTAL_REDEMPTIONS_SORT_ORDER'];
	}
}
