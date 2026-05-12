<?php
class payment {
	public $modules, $selected_module;
	function __construct($module = '') {
		global $payment, $language, $PHP_SELF, $shipping;

		if (defined('MODULE_PAYMENT_INSTALLED') && tep_not_null(MODULE_PAYMENT_INSTALLED)) {
			global $customer_id, $customer_group_id;

			// Metodos ship2pay
			$aPaymentToShipping = [];

			// Metodos de pago por grupo de cliente
			$aGroupPayment = [];

			// Metodos de pago instalados
			$aInstallModules = explode(';', MODULE_PAYMENT_INSTALLED);

			// Consulta para obtener los modulos de pago que contiene el grupo de cliente
			$aDatos = tep_db_query("select IF(c.customers_payment_allowed <> '', c.customers_payment_allowed, cg.group_payment_allowed) as payment_allowed
								 from " . TABLE_CUSTOMERS . " c, " . TABLE_CUSTOMERS_GROUPS . " cg
								 where c.customers_id = '" . $customer_id . "' and cg.customers_group_id =  '" . $customer_group_id . "'");

			// Obtenemos los metodos de pago por grupo de cliente
			if (tep_db_num_rows($aDatos) > 0) {
				$aDatos = tep_db_fetch_array($aDatos);
				$aDatos = explode(';', $aDatos['payment_allowed']);

				foreach ($aInstallModules as $value) {
					if (in_array($value, $aDatos)) {
						$aGroupPayment[] = $value;
					}
				}
			}

			// Obtenemos los metodos de pago segun envio ship2pay
			require_once(DIR_WS_CLASSES . 'ship2pay.php');
			$spShip2Pay = new ship2pay;

			// Obtenemos los metodos de envio
			$aShipping = (!empty($shipping['id'])) ? explode('_', $shipping['id']) : [];

			// Obtenemos los metodos de pago segun el envio
			if ($spShip2Pay->get_pay_modules($aShipping[0])) {
				$aPaymentToShipping = explode(';', $spShip2Pay->get_pay_modules($aShipping[0]));
			}

			// Si no contemos nada en ship2pay ni en grupo de cliente, mostraremos todo los metodos de pago
			if (count($aPaymentToShipping) == 0 && count($aGroupPayment) == 0) {
				$this->modules = $aInstallModules;
			} // Si no contemos nada en grupo de cliente pero si en ship2pay, mostraremo ship2pay
			else if (count($aGroupPayment) == 0 && count($aPaymentToShipping) != 0) {
				$this->modules = $aPaymentToShipping;
			} // Si no contemos nada en ship2pay pero si en grupo de cliente, mostramos grupo de cliente
			else if (count($aPaymentToShipping) == 0 && count($aGroupPayment) != 0) {
				$this->modules = $aGroupPayment;
			} // Por ultimo mostramos ship2pay que este permitido en grupo de cliente
			else {
				$aAux = [];

				// Recorremos ship2pay
				foreach ($aPaymentToShipping as $sSP) {
					// Vemos si esta permitido por grupo de cliente
					foreach ($aGroupPayment as $sGP)
						if ($sSP == $sGP)
							$aAux[] = $sSP;
				}

				$this->modules = $aAux;
			}

			$include_modules = [];

			if ((tep_not_null($module)) && (in_array($module . '.' . substr($PHP_SELF, (strrpos($PHP_SELF, '.') + 1)), $this->modules))) {
				$this->selected_module = $module;

				$include_modules[] = ['class' => $module, 'file' => $module . '.php'];
			} else {
				foreach ($this->modules as $value) {
					$class = substr($value, 0, strrpos($value, '.'));
					$include_modules[] = ['class' => $class, 'file' => $value];
				}
			}

			for ($i = 0, $n = count($include_modules); $i < $n; $i++) {
				if (file_exists(DIR_WS_LANGUAGES . $language . '/modules/payment/' . $include_modules[$i]['file'])) {
					include_once(DIR_WS_LANGUAGES . $language . '/modules/payment/' . $include_modules[$i]['file']);
				}

				if (file_exists(DIR_WS_MODULES . 'payment/' . $include_modules[$i]['file'])) {
					include_once(DIR_WS_MODULES . 'payment/' . $include_modules[$i]['file']);
				}

				$GLOBALS[$include_modules[$i]['class']] = new $include_modules[$i]['class'];
			}

			// if there is only one payment method, select it as default because in
			// checkout_confirmation.php the $payment variable is being assigned the
			// $_POST['payment'] value which will be empty (no radio button selection possible)
			if ((tep_count_payment_modules() == 1) && (!isset($GLOBALS[$payment]) || (isset($GLOBALS[$payment]) && !is_object($GLOBALS[$payment])))) {
				$payment = $include_modules[0]['class'];
			}

			if ((tep_not_null($module)) && (in_array($module, $this->modules)) && (isset($GLOBALS[$module]->form_action_url))) {
				$this->form_action_url = $GLOBALS[$module]->form_action_url;
			}
		}
	}

	/* The following method is needed in the checkout_confirmation.php page
	   due to a chicken and egg problem with the payment class and order class.
	   The payment modules needs the order destination data for the dynamic status
	   feature, and the order class needs the payment module title.
	   The following method is a work-around to implementing the method in all
	   payment modules available which would break the modules in the contributions
	   section. This should be looked into again post 2.2.
	*/
	function update_status() {
		if (is_array($this->modules)) {
			if (is_object($GLOBALS[$this->selected_module])) {
				if (method_exists($GLOBALS[$this->selected_module], 'update_status')) {
					$GLOBALS[$this->selected_module]->update_status();
				}
			}
		}
	}

	function javascript_validation() {
		$js = '';
		if (is_array($this->modules)) {
			$js = '<script type="text/javascript"><!-- ' . "\n" .
				'function check_form() {' . "\n" .
				'  var error = 0;' . "\n" .
				'  var error_message = "' . JS_ERROR . '";' . "\n" .
				'  var payment_value = null;' . "\n" .
				'  if (document.checkout_payment.payment.length) {' . "\n" .
				'    for (var i=0; i<document.checkout_payment.payment.length; i++) {' . "\n" .
				'      if (document.checkout_payment.payment[i].checked) {' . "\n" .
				'        payment_value = document.checkout_payment.payment[i].value;' . "\n" .
				'      }' . "\n" .
				'    }' . "\n" .
				'  } else if (document.checkout_payment.payment.checked) {' . "\n" .
				'    payment_value = document.checkout_payment.payment.value;' . "\n" .
				'  } else if (document.checkout_payment.payment.value) {' . "\n" .
				'    payment_value = document.checkout_payment.payment.value;' . "\n" .
				'  }' . "\n\n";

			foreach ($this->modules as $value) {
				$class = substr($value, 0, strrpos($value, '.'));
				if ($GLOBALS[$class]->enabled) {
					$js .= $GLOBALS[$class]->javascript_validation();
				}
			}

			$js .= "\n" . '  if (payment_value == null) {' . "\n" .
				'    error_message = error_message + "' . JS_ERROR_NO_PAYMENT_MODULE_SELECTED . '";' . "\n" .
				'    error = 1;' . "\n" .
				'  }' . "\n\n" .
				'  if (error == 1 && submitter != 1) {' . "\n" .
				'    alert(error_message);' . "\n" .
				'    return false;' . "\n" .
				'  } else {' . "\n" .
				'    return true;' . "\n" .
				'  }' . "\n" .
				'}' . "\n" .
				'//--></script>' . "\n";
		}

		return $js;
	}

	function checkout_initialization_method() {
		$initialize_array = [];

		if (is_array($this->modules)) {
			foreach ($this->modules as $value) {
				$class = substr($value, 0, strrpos($value, '.'));
				if ($GLOBALS[$class]->enabled && method_exists($GLOBALS[$class], 'checkout_initialization_method')) {
					$initialize_array[] = $GLOBALS[$class]->checkout_initialization_method();
				}
			}
		}

		return $initialize_array;
	}

	function selection() {
		global $cart;

		$cart_products = $cart->get_products();
		$real_ids = [];
		foreach ($cart_products as $prod) {
			$real_ids[] = tep_get_prid($prod['id']);
		}
		$sql = "SELECT payment_methods FROM " . TABLE_PRODUCTS . " WHERE products_id IN (" . implode(',', $real_ids) . ") AND payment_methods IS NOT NULL AND payment_methods <> ''";
		$query = tep_db_query($sql);

		$allow_mod_array = [];
		while ($rec = tep_db_fetch_array($query)) {
			if (empty($allow_mod_array)) $startedempty = true;
			$methods_array = [];
			$methods_array = explode(';', $rec['payment_methods']);
			if (!empty($methods_array)) {
				foreach ($methods_array as $method) {
					$allow_mod_array[] = $method;
				}
			}
			if ($startedempty) {
				$startedempty = false;
			} else {
				$temp_array = [];
				foreach ($allow_mod_array as $val) {
					$temp_array[$val]++;
				}
				$allow_mod_array = [];
				foreach ($temp_array as $key => $val) {
					if ($val > 1) {
						$allow_mod_array[] = $key;
					}
				}
			}
		}
		// INDIV_PM END
		$selection_array = [];

		if (is_array($this->modules)) {
			foreach ($this->modules as $value) {
				$class = substr($value, 0, strrpos($value, '.'));
				if ($GLOBALS[$class]->enabled && (tep_db_num_rows($query) == 0 || in_array($class, $allow_mod_array))) {
					$selection = $GLOBALS[$class]->selection();
					if (is_array($selection)) $selection_array[] = $selection;
				}
			}
		}

		return $selection_array;
	}

	function pre_confirmation_check() {
		if (is_array($this->modules)) {
			if (is_object($GLOBALS[$this->selected_module]) && ($GLOBALS[$this->selected_module]->enabled)) {
				$GLOBALS[$this->selected_module]->pre_confirmation_check();
			}
		}
	}

	function confirmation() {
		if (is_array($this->modules)) {
			if (is_object($GLOBALS[$this->selected_module]) && ($GLOBALS[$this->selected_module]->enabled)) {
				return $GLOBALS[$this->selected_module]->confirmation();
			}
		}
	}

	function process_button() {
		if (is_array($this->modules)) {
			if (is_object($GLOBALS[$this->selected_module]) && ($GLOBALS[$this->selected_module]->enabled)) {
				return $GLOBALS[$this->selected_module]->process_button();
			}
		}
	}

	function before_process() {
		if (is_array($this->modules)) {
			if (is_object($GLOBALS[$this->selected_module]) && ($GLOBALS[$this->selected_module]->enabled)) {
				return $GLOBALS[$this->selected_module]->before_process();
			}
		}
	}

	function after_process() {
		if (is_array($this->modules)) {
			if (is_object($GLOBALS[$this->selected_module]) && ($GLOBALS[$this->selected_module]->enabled)) {
				return $GLOBALS[$this->selected_module]->after_process();
			}
		}
	}

	function get_error() {
		if (is_array($this->modules)) {
			if (is_object($GLOBALS[$this->selected_module]) && ($GLOBALS[$this->selected_module]->enabled)) {
				return $GLOBALS[$this->selected_module]->get_error();
			}
		}
	}
}

?>
