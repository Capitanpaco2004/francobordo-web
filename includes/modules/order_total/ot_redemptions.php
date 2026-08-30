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

		// #FB-PUNTOS-TOPE (2026-08-29): TOPE AUTORITATIVO del canje. Este es el unico punto del
		// flujo en el que $order->info['total'] es el importe real a pagar (subtotal + IVA + envio
		// + seguro + comisiones + cupon) y TODAVIA sin descontar los puntos, es decir, el techo que
		// el canje no puede sobrepasar. Sin este tope el total del pedido podia quedar NEGATIVO
		// (33 pedidos historicos, el peor -63,96 EUR).
		// calculate_max_points() devuelve min(puntos_pedidos, total / REDEEM_POINT_VALUE,
		// POINTS_MAX_VALUE) redondeado a la baja, respetando las reglas de negocio ya existentes
		// (REDEMPTION_DISCOUNTED y RESTRICTION_*). NO cambia cuantos euros vale cada punto.
		$nPuntosPedidos = (int) floor((float) $customer_shopping_points_spending);
		$nPuntosAplicar = (int) calculate_max_points($nPuntosPedidos);
		if ($nPuntosAplicar < 0) {
			$nPuntosAplicar = 0;
		}

		if ($nPuntosAplicar !== $nPuntosPedidos) {
			// Propagamos el valor recortado (la variable es global) para que checkout_process
			// descuente del saldo EXACTAMENTE los mismos puntos que se descuentan del pedido.
			$customer_shopping_points_spending = $nPuntosAplicar;
			if (isset($_SESSION['customer_shopping_points_spending'])) {
				$_SESSION['customer_shopping_points_spending'] = $nPuntosAplicar;
			}
		}

		if ($nPuntosAplicar <= 0) {
			return;
		}

		// REDEEM_POINT_VALUE se configura CON IVA inc. (lo que el cliente ve en my_points y
		// en su saldo). Ese valor con IVA es lo que el cliente AHORRA. Internamente la línea
		// del desglose se grava SIN IVA (base), de forma que:
		//   - el desglose web cuadra linealmente (subtotal + envío − base + IVA = total)
		//   - QFacWin, al aplicarle su IVA a la base, muestra el importe con IVA correcto (= ahorro)
		// (Opción B — 2026-05-19, sustituye a la Opción A que mostraba el valor con IVA en la línea)
		$nPointValueConIva = tep_calc_shopping_pvalue($nPuntosAplicar);

		// Peso bruto del carrito por tipo de IVA — para prorratear base/IVA del descuento entre grupos
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

		// Repartir el descuento (con IVA) entre grupos: calcular la parte BASE (sin IVA) total
		// y bajar el IVA de cada grupo en su parte proporcional.
		$nBaseDescuento = 0;
		if ($nTotalBruto > 0) {
			foreach ($aGrupos as $sKey => $aGrupo) {
				$nShareConIva = $nPointValueConIva * ($aGrupo['bruto'] / $nTotalBruto);
				$nBaseShare   = ($aGrupo['tax'] > 0) ? $nShareConIva / (1 + ($aGrupo['tax'] / 100)) : $nShareConIva;
				$nIvaShare    = $nShareConIva - $nBaseShare;
				$nBaseDescuento += $nBaseShare;
				if (isset($order->info['tax_groups'][$sKey])) {
					$order->info['tax_groups'][$sKey] -= $nIvaShare;
				}
			}
		} else {
			// Carrito sin productos con peso (caso raro): tratar todo el descuento como base
			$nBaseDescuento = $nPointValueConIva;
		}

		// El cliente ahorra el valor CON IVA: bajamos el total (que ya incluye IVA) en ese importe.
		$order->info['total']         -= $nPointValueConIva;
		$order->info['payment_method'] = ($order->info['total'] > 0)
			? $order->info['payment_method'] . '+' . str_replace(':', '', TEXT_POINTS)
			: str_replace(':', '', TEXT_POINTS);

		// TEXTO mostrado (cliente en checkout + admin) = CON IVA, coherente con el resto de
		// líneas que el cliente ve con IVA (productos, envío, seguro).
		// VALUE guardado en orders_total = BASE sin IVA → QFacWin le aplica su IVA y cuadra a con-IVA.
		$sFormattedConIva = $currencies->format($nPointValueConIva, true, $order->info['currency'], $order->info['currency_value']);

		$this->output[] = [
			'title'    => MODULE_ORDER_TOTAL_REDEMPTIONS_TEXT . ':',
			'text'     => '<span class="red">-' . $sFormattedConIva . '</span>',
			'text_tax' => '<span class="red">-' . $sFormattedConIva . '</span>',
			'value'    => $nBaseDescuento,
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
