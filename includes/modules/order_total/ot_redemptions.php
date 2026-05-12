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
		global $order, $currencies, $customer_shopping_points_spending, $bActiveCheckoutOnePage;

		if ($customer_shopping_points_spending && $_SESSION['customer_shopping_points_spending']) {
			$customer_shopping_points_spending = $_SESSION['customer_shopping_points_spending'];
		}

		// if customer is using points to pay
		if (isset($customer_shopping_points_spending) && is_numeric($customer_shopping_points_spending) && ($customer_shopping_points_spending > 0)) {
			$nPointValue      = tep_calc_shopping_pvalue($customer_shopping_points_spending);
			$nPointValueFinal = $nPointValue;

			// Variables
			$aProductos = [];

			// Recorremos productos para guardar el total con sus tax
			foreach ($order->products as $aProducto) {
				// Si no existe el nuevo impuesto lo creamos
				if (!array_key_exists($aProducto['tax_description'], $aProductos)) {
					$aProductos[$aProducto['tax_description']] = ['con_tax' => 0, 'sin_tax' => 0, 'tax' => $aProducto['tax']];
				}

				// Vamos aumentando
				$aProductos[$aProducto['tax_description']]['con_tax'] += $aProducto['final_price'] * (1 + ($aProducto['tax'] / 100));
				$aProductos[$aProducto['tax_description']]['sin_tax'] += $aProducto['final_price'];
			}

			// Variable auxiliar para ir descontando al valor de los puntos
			$nAuxPoint = $nPointValue;

			// Variable auxiliar por si acaso existe un iva o varios
			$bTax = false;

			if (count($aProductos) > 1) {
				$bTax = true;
			}

			// Recorremos los tax con la suma de sus productos
			foreach ($aProductos as $sTax => $value) {
				// Tax
				$nTax = (1 + ($value['tax'] / 100));

				// Descuento
				$nDescuentoConTax = $nAuxPoint;
				$nDescuentoSinTax = $nDescuentoConTax / $nTax;
				$nDescuentoResto  = $nDescuentoConTax - $nDescuentoSinTax;

				// Producto
				$nProductoSinTax = $value['sin_tax'] - ($bTax ? $nDescuentoResto : $nDescuentoSinTax);
				$nProductoConTax = $nProductoSinTax * $nTax;
				$nProductoResto  = $nProductoConTax - $nProductoSinTax;

				// Descontamos del tax el tax del producto por si tiene mas cosas como tax de envio,seguro etc
				$nSubTax = $order->info['tax_groups'][$sTax] - ($value['con_tax'] - $value['sin_tax']);

				// Restamos tax
				$nAux = $order->info['tax_groups'][$sTax];

				if ($bTax) {
					$order->info['tax_groups'][$sTax] = $nProductoResto + $nSubTax;
				} else {
					$order->info['tax_groups'][$sTax] -= $nDescuentoResto;
				}

				// Restamos descuento
				$nAuxPoint -= $nProductoResto;

				// Descontamos
				$nPointValueFinal -= $nAux - $order->info['tax_groups'][$sTax];
			}

			$order->info['total']          -= $nPointValue;
			$order->info['payment_method'] = ($order->info['total'] > 0) ? $order->info['payment_method'] . '+' . str_replace(':', '', TEXT_POINTS) : str_replace(':', '', TEXT_POINTS);

			$module       = substr((string)$GLOBALS['shipping']['id'], 0, strpos((string)$GLOBALS['shipping']['id'], '_'));
			$shipping_tax = tep_get_tax_rate($GLOBALS[$module]->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);

			$this->output[] = [
				'title'    => MODULE_ORDER_TOTAL_REDEMPTIONS_TEXT . ':',
				'text_tax' => '<span class="red">-' . $currencies->format($nPointValue, true, $order->info['currency'], $order->info['currency_value'] . '</span>'),
				'text'     => '<span class="red">-' . $currencies->format(($bActiveCheckoutOnePage ? $nPointValueFinal + tep_calculate_tax($nPointValueFinal, $shipping_tax) : $nPointValueFinal), true, $order->info['currency'], $order->info['currency_value'] . '</span>'),
				'value'    => $nPointValueFinal,
				'value'    => $nPointValueFinal + tep_calculate_tax($nPointValueFinal, $shipping_tax),
			];
		}
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
