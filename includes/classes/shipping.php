<?php

class shipping
{
	public $modules;

	// ------------------------------------------------------------------
	// Recargo de gastos de envío por grupo de cliente (SPPC).
	// El porcentaje se configura por grupo en el admin: Clientes > Grupos
	// de clientes (customers_groups.customers_group_shipping_surcharge,
	// ej. 30 = +30%). Se escribe también en $GLOBALS[$clase]->quotes porque
	// cheapest() y preselected() leen esa propiedad del módulo, no el
	// retorno de quote(). Recogida en tienda excluida (no es un envío).
	// ------------------------------------------------------------------
	const FB_GROUP_SURCHARGE_EXCLUDED = array('retira', 'pickup');

	private function fb_group_shipping_surcharge($class, $quotes)
	{
		global $customer_group_id;
		static $pct_cache = array();

		$group = (int)(isset($customer_group_id) ? $customer_group_id : (isset($_SESSION['sppc_customer_group_id']) ? $_SESSION['sppc_customer_group_id'] : 0));

		if (!array_key_exists($group, $pct_cache)) {
			$pct_cache[$group] = 0.0;
			$rs = tep_db_query("select customers_group_shipping_surcharge from " . TABLE_CUSTOMERS_GROUPS . " where customers_group_id = '" . (int)$group . "'");
			if ($row = tep_db_fetch_array($rs)) {
				$pct_cache[$group] = max(0.0, (float)$row['customers_group_shipping_surcharge']) / 100.0;
			}
		}
		$pct = $pct_cache[$group];

		if ($pct <= 0
			|| in_array($class, self::FB_GROUP_SURCHARGE_EXCLUDED)
			|| !is_array($quotes)
			|| !empty($quotes['error'])
			|| empty($quotes['methods']) || !is_array($quotes['methods'])
			|| !empty($quotes['fb_group_surcharged'])) {
			return $quotes;
		}

		foreach ($quotes['methods'] as $k => $m) {
			if (isset($m['cost']) && is_numeric($m['cost']) && $m['cost'] > 0) {
				$quotes['methods'][$k]['cost'] = round($m['cost'] * (1 + $pct), 2);
			}
		}
		$quotes['fb_group_surcharged'] = true;

		if (isset($GLOBALS[$class]) && is_object($GLOBALS[$class])) {
			$GLOBALS[$class]->quotes = $quotes;
		}

		return $quotes;
	}

	function __construct($module = '')
	{
		global $language, $PHP_SELF, $cart, $order, $baleares, $customer_id; // Agregamos la variable $baleares

		$baleares = null; // Definimos la variable $baleares inicialmente como null

		// New to fix attributes bug
		$cart_products = $cart->get_products();
		$real_ids = array();

		foreach ($cart_products as $prod) {
			$real_ids[] = tep_get_prid($prod['id']);
		}
		$real_ids = array_filter($real_ids);
		$allow_mod_array = array();
		if (count($real_ids) > 0) {
			// "Envío de Pirotecnia": clientes con la flag customers_pyro_courier=1 pueden
			// recibir por mensajería los productos de categoría 20 (pirotecnia) ignorando
			// su shipping_methods='retira'. Para el resto del carrito la restricción sigue.
			$pyro_courier_allowed = false;
			if (isset($customer_id) && (int)$customer_id > 0) {
				$qry = tep_db_query("SELECT customers_pyro_courier FROM " . TABLE_CUSTOMERS . " WHERE customers_id = '" . (int)$customer_id . "'");
				if ($row = tep_db_fetch_array($qry)) {
					$pyro_courier_allowed = ((int)$row['customers_pyro_courier'] === 1);
				}
			}
			$pyro_exclude_sql = $pyro_courier_allowed
				? " AND products_id NOT IN (SELECT products_id FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " WHERE categories_id = 20)"
				: "";
			$sql = "SELECT shipping_methods FROM " . TABLE_PRODUCTS . " WHERE products_id IN (" . implode(',', $real_ids) . ") AND shipping_methods IS NOT NULL AND shipping_methods <> ''" . $pyro_exclude_sql;
			$query = tep_db_query($sql);

			// End new bug fix

			while ($rec = tep_db_fetch_array($query)) {
				if (empty($allow_mod_array)) $startedempty = true;
				$methods_array = array();
				$methods_array = explode(';', $rec['shipping_methods']);
				if (!empty($methods_array)) {
					foreach ($methods_array as $method) {
						$allow_mod_array[] = $method;
					}
				}
				if ($startedempty) {
					$startedempty = false;
				} else {
					$temp_array = array();
					foreach ($allow_mod_array as $val) {
						$temp_array[$val]++;
					}
					$allow_mod_array = array();
					foreach ($temp_array as $key => $val) {
						if ($val > 1) {
							$allow_mod_array[] = $key;
						}
					}
				}
			}
		}
		// PSM END
		if (defined('MODULE_SHIPPING_INSTALLED') && tep_not_null(MODULE_SHIPPING_INSTALLED)) {
			global $customer_id, $customer_group_id;

			$customer_shipment_query = tep_db_query("select IF(c.customers_shipment_allowed <> '', c.customers_shipment_allowed, cg.group_shipment_allowed) as shipment_allowed from " . TABLE_CUSTOMERS . " c, " . TABLE_CUSTOMERS_GROUPS . " cg where c.customers_id = '" . $customer_id . "' and cg.customers_group_id =  '" . $customer_group_id . "'");
			if ($customer_shipment = tep_db_fetch_array($customer_shipment_query)) {
				if (tep_not_null($customer_shipment['shipment_allowed'])) {
					$temp_shipment_array = explode(';', $customer_shipment['shipment_allowed']);
					$installed_modules = explode(';', MODULE_SHIPPING_INSTALLED);
					for ($n = 0; $n < count($installed_modules); $n++) {
						// check to see if a shipping module is not de-installed
						if (in_array($installed_modules[$n], $temp_shipment_array)) {
							$shipment_array[] = $installed_modules[$n];
						}
					} // end for loop
					$this->modules = $shipment_array;
				} else {
					$this->modules = explode(';', MODULE_SHIPPING_INSTALLED);
					// PSM START
					$temp_array = $this->modules;
					$this->modules = array();
					foreach ($temp_array as $val) {
						if (tep_db_num_rows($query) == 0 || in_array(str_replace('.php', '', $val), $allow_mod_array)) {
							$this->modules[] = $val;
						}
					}

				}
			} else { // default
				$this->modules = explode(';', MODULE_SHIPPING_INSTALLED);
				// PSM START
				$temp_array = $this->modules;
				$this->modules = array();
				foreach ($temp_array as $val) {
					if (tep_db_num_rows($query) == 0 || in_array(str_replace('.php', '', $val), $allow_mod_array)) {
						$this->modules[] = $val;
					}
				}
			}
// EOF Separate Pricing Per Customer

			$include_modules = array();

			if ((tep_not_null($module)) && (in_array(substr((string)$module['id'], 0, strpos((string)$module['id'], '_')) . '.' . substr($PHP_SELF, (strrpos($PHP_SELF, '.') + 1)), $this->modules))) {
				$include_modules[] = array('class' => substr((string)$module['id'], 0, strpos((string)$module['id'], '_')), 'file' => substr($module['id'], 0, strpos((string)$module['id'], '_')) . '.' . substr($PHP_SELF, (strrpos($PHP_SELF, '.') + 1)));
			} else {
				foreach ($this->modules as $value) {
					$class = substr($value, 0, strrpos($value, '.'));
					$include_modules[] = array('class' => $class, 'file' => $value);
				}
			}

			for ($i = 0, $n = count($include_modules); $i < $n; $i++) {
				require_once(DIR_WS_LANGUAGES . $language . '/modules/shipping/' . $include_modules[$i]['file']);
				require_once(DIR_WS_MODULES . 'shipping/' . $include_modules[$i]['file']);
				$GLOBALS[$include_modules[$i]['class']] = new $include_modules[$i]['class'];
			}
		}
	}

	public function select(string $selected_module): array
	{
		global $total_weight, $shipping_weight, $shipping_quoted, $shipping_num_boxes;

		$quotes_array = array();

		if (is_array($this->modules)) {
			$shipping_quoted = '';
			$shipping_num_boxes = 1;
			$shipping_weight = $total_weight;

			if (SHIPPING_BOX_WEIGHT >= $shipping_weight * SHIPPING_BOX_PADDING / 100) {
				$shipping_weight = $shipping_weight + SHIPPING_BOX_WEIGHT;
			} else {
				$shipping_weight = $shipping_weight + ($shipping_weight * SHIPPING_BOX_PADDING / 100);
			}

			if ($shipping_weight > SHIPPING_MAX_WEIGHT) {
				$shipping_num_boxes = ceil($shipping_weight / SHIPPING_MAX_WEIGHT);
				$shipping_weight = $shipping_weight / $shipping_num_boxes;
			}

			$include_quotes = array();

			foreach ($this->modules as $value) {
				$class = substr($value, 0, strrpos($value, '.'));
				if ($GLOBALS[$class]->enabled) {
					$include_quotes[] = $class;
				}
			}

			$size = sizeof($include_quotes);
			for ($i = 0; $i < $size; $i++) {
				$quotes = $GLOBALS[$include_quotes[$i]]->quote('');
				if (is_array($quotes)) $quotes_array[] = $this->fb_group_shipping_surcharge($include_quotes[$i], $quotes);
			}
		}

		foreach ($quotes_array as $quote) {
			if ($quote['id'] == $selected_module) {
				return $quote;
			}
		}

		return [];
	}

	function quote($method = '', $module = '')
	{
		global $total_weight, $shipping_weight, $shipping_quoted, $shipping_num_boxes;

		$quotes_array = array();

		if (is_array($this->modules)) {
			$shipping_quoted = '';
			$shipping_num_boxes = 1;
			$shipping_weight = $total_weight;

			if (SHIPPING_BOX_WEIGHT >= $shipping_weight * SHIPPING_BOX_PADDING / 100) {
				$shipping_weight = $shipping_weight + SHIPPING_BOX_WEIGHT;
			} else {
				$shipping_weight = $shipping_weight + ($shipping_weight * SHIPPING_BOX_PADDING / 100);
			}

			if ($shipping_weight > SHIPPING_MAX_WEIGHT) { // Split into many boxes
				$shipping_num_boxes = ceil($shipping_weight / SHIPPING_MAX_WEIGHT);
				$shipping_weight = $shipping_weight / $shipping_num_boxes;
			}

			$include_quotes = array();

			foreach ($this->modules as $value) {
				$class = substr($value, 0, strrpos($value, '.'));
				if (tep_not_null($module)) {
					if (($module == $class) && ($GLOBALS[$class]->enabled)) {
						$include_quotes[] = $class;
					}
				} elseif ($GLOBALS[$class]->enabled) {
					$include_quotes[] = $class;
				}
			}

			$size = count($include_quotes);
			for ($i = 0; $i < $size; $i++) {
				$quotes = $GLOBALS[$include_quotes[$i]]->quote($method);
				if (is_array($quotes)) $quotes_array[] = $this->fb_group_shipping_surcharge($include_quotes[$i], $quotes);
			}
		}

		return $quotes_array;
	}

	/**
	 * Devuelve el método de envío que se debe preseleccionar por defecto en el checkout.
	 *
	 * Logica de negocio: muchos clientes acababan con "Recoger en tienda" sin querer
	 * porque era la opcion mas barata y quedaba preseleccionada.
	 *
	 *   1. Si TIPSA (mensajeria) esta disponible -> se preselecciona TIPSA.
	 *   2. Si no -> el mas barato, excluyendo modulos de recogida/oficina.
	 *
	 * @return array|false Array con id/title/cost del modulo elegido, o false si no hay ninguno.
	 */
	public function preselected()
	{
		if (!is_array($this->modules)) {
			return false;
		}

		// Modulos de recogida/oficina que NUNCA queremos preseleccionar
		$excluded = array(
			'pickup',                 // recogida (legacy)
			'retira',                 // Recoger en tienda
			'kialapoint',             // Kiala (recogida en punto)
			'correos',                // Oficina de Correos
			'correospaespceutamel',   // Oficina de Correos (Ceuta/Melilla)
			'correospaespbal',        // Oficina de Correos (Baleares)
			'correosint',             // Correos Oficina (internacional)
		);

		$cheapest = false;

		foreach ($this->modules as $value) {
			$class = substr($value, 0, strrpos($value, '.'));

			if (in_array($class, $excluded)) {
				continue;
			}

			if ($GLOBALS[$class] == null || !$GLOBALS[$class]->enabled) {
				continue;
			}

			$quotes = $GLOBALS[$class]->quotes;

			if (!is_array($quotes['methods']) || isset($quotes['error'])) {
				continue;
			}

			for ($i = 0, $n = count($quotes['methods']); $i < $n; $i++) {
				if (!isset($quotes['methods'][$i]['cost'])) {
					continue;
				}

				$rate = array(
					'id' => $quotes['id'] . '_' . $quotes['methods'][$i]['id'],
					'title' => $quotes['module'] . ' (' . $quotes['methods'][$i]['title'] . ')',
					'cost' => $quotes['methods'][$i]['cost']
				);

				// Preferencia: TIPSA (mensajeria) siempre que este disponible
				if ($class === 'tipsa') {
					return $rate;
				}

				if ($cheapest === false || $rate['cost'] < $cheapest['cost']) {
					$cheapest = $rate;
				}
			}
		}

		return $cheapest;
	}

	public function cheapest()
	{
		if (is_array($this->modules)) {
			$rates = array();

			foreach ($this->modules as $value) {
				$class = substr($value, 0, strrpos($value, '.'));

				// Modulos de envio que queremos pasar de ellos
				if (in_array($class, array('pickup'))) {
					continue;
				}

				if ($GLOBALS[$class] != null && $GLOBALS[$class]->enabled) { // Verificamos si $GLOBALS[$class] no es nulo
					$quotes = $GLOBALS[$class]->quotes;
					if (is_array($quotes['methods'])) {

						// Si contiene errores
						if (isset($quotes['error'])) {
							continue;
						}

						for ($i = 0, $n = count($quotes['methods']); $i < $n; $i++) {
							if (isset($quotes['methods'][$i]['cost'])) {
								$rates[] = array(
									'id' => $quotes['id'] . '_' . $quotes['methods'][$i]['id'],
									'title' => $quotes['module'] . ' (' . $quotes['methods'][$i]['title'] . ')',
									'cost' => $quotes['methods'][$i]['cost']
								);
							}
						}
					}
				}
			}

			$cheapest = false;
			for ($i = 0, $n = count($rates); $i < $n; $i++) {
				if (is_array($cheapest)) {
					if ($rates[$i]['cost'] < $cheapest['cost']) {
						$cheapest = $rates[$i];
					}
				} else {
					$cheapest = $rates[$i];
				}
			}

			return $cheapest;
		}
	}
}
