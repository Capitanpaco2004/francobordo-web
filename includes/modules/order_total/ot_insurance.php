<?php

class ot_insurance {
	public $title, $output;
	public $code = 'ot_insurance';
	public $description;
	public $enabled;
	public $sort_order;
	public $multiplier;
	public $tax_class;
	public $check;

	public function __construct() {
		global $order;

		$this->code        = 'ot_insurance';
		$this->title       = defined('MODULE_ORDER_TOTAL_INSURANCE_TITLE') ? MODULE_ORDER_TOTAL_INSURANCE_TITLE : '';
		$this->description = defined('MODULE_ORDER_TOTAL_INSURANCE_DESCRIPTION') ? MODULE_ORDER_TOTAL_INSURANCE_DESCRIPTION : '';
		$this->enabled     = defined('MODULE_ORDER_TOTAL_INSURANCE_STATUS') ? MODULE_ORDER_TOTAL_INSURANCE_STATUS : '';
		$this->sort_order  = defined('MODULE_ORDER_TOTAL_INSURANCE_SORT_ORDER') ? MODULE_ORDER_TOTAL_INSURANCE_SORT_ORDER : '';
		$this->multiplier  = defined('MODULE_ORDER_TOTAL_INSURANCE_INT_MULT') ? MODULE_ORDER_TOTAL_INSURANCE_INT_MULT : '';
		$this->tax_class   = defined('MODULE_ORDER_TOTAL_INSURANCE_TAX_CLASS') ? MODULE_ORDER_TOTAL_INSURANCE_TAX_CLASS : '';
		$this->output      = [];

		if ($this->enabled == 'true'):

			if (is_object($order) && ($order->info['total'] >= MODULE_ORDER_TOTAL_INSURANCE_OVER) && (MODULE_ORDER_TOTAL_INSURANCE_STATUS == 'true') && (MODULE_ORDER_TOTAL_INSURANCE_USE == 'true')) {

				hooksAddAction('after.checkout.shipping', function () {
					?>
					<div class="otInsurance">
						<div class="pghd"><?php echo TEXT_SHIPPING_INSURANCE_TITLE; ?></div>
						<label style="display: block; padding: 10px; cursor: pointer;">
							<?php $checked = intval($_SESSION['choose_insurance']) == 1 || !isset($_SESSION['choose_insurance']); ?>
							<input type="checkbox" name="choose_insurance" value="1" <?php if ($checked): ?> checked <?php endif; ?> style="height: auto; margin: 0 5px 0 5px; display: inline-block;">
							<?php echo TEXT_SHIPPING_INSURANCE_CHOICE; ?>
						</label>
						<!--<div class="cop-information"><? echo TEXT_SHIPPING_INSURANCE_DISCLAIMER; ?></div>-->
					</div>
					<script>
						(function () {
							setTimeout(function () {
								jQuery("#cop-form").on("change", "input[name=choose_insurance]", function () {
									jQuery('#checkout_shipping .cop-row-method input:checked').parents('.cop-row-method').trigger("click");
								});
							}, 300);
						})();
					</script>
					<?php
				});
			}
		endif;
	}

	public function process() {
		global $order, $currencies, $bActiveCheckoutOnePage;
		$choose_insurance = $_SESSION['choose_insurance'] ?? '';

		if (MODULE_ORDER_TOTAL_INSURANCE_STATUS == 'true') {
			switch (MODULE_ORDER_TOTAL_INSURANCE_DESTINATION) {
				case 'national':
					if ($order->delivery['country_id'] == STORE_COUNTRY) {
						$pass = true;
					}

					break;
				case 'international':
					if ($order->delivery['country_id'] != STORE_COUNTRY) {
						$pass = true;
					}

					break;
				case 'both':
					$pass = true;
					break;
				default:
					$pass = false;
					break;
			}
		}

		if (($choose_insurance != '1') && (MODULE_ORDER_TOTAL_INSURANCE_USE == 'true')) {
			$pass = false;
		}

		// Added in by Juan Velez to stop any negative amount
		if ($order->info['total'] < MODULE_ORDER_TOTAL_INSURANCE_OVER) {
			$pass = false;
		}

		// End of add by Juan Velez
		if ($pass == true) {

			//variable $how_often is the amount of times to multiply the insurance rate.
			$how_often = ceil(($order->info['total'] - $order->info['tax'] - MODULE_ORDER_TOTAL_INSURANCE_OVER) / MODULE_ORDER_TOTAL_INSURANCE_INCREMENT);

			//variable $this_amount becomes the total insurance fee once multiplied by $how_often below.
			$this_amount = MODULE_ORDER_TOTAL_INSURANCE_FEE * $how_often;
			if ($this_amount < MODULE_ORDER_TOTAL_INSURANCE_MIN_CHARGE) {
				$this_amount = MODULE_ORDER_TOTAL_INSURANCE_MIN_CHARGE;
			}

			// If international shipment, multiply insurance charge by multiplier
			if ($order->delivery['country_id'] != STORE_COUNTRY) {
				$this_amount *= $this->multiplier;
			}

			// @Victor.DENOX Debido al ticket #BJU-123-41792 he obtenido el IVA del envío para saber si hay que calcular el IVA del seguro en el editor de pedidos

			$module = substr((string)$GLOBALS['shipping']['id'], 0, strpos((string)$GLOBALS['shipping']['id'], '_'));


			/**
			 * @author Daniel Lucia <daniel.lucia@denox.es>
			 * He modificado esta parte porque los envios con correos, no se crea el objeto en la variable global.
			 */
			if ($bActiveCheckoutOnePage) {
				if ($GLOBALS['shipping']['id'] == 'Normal_Normal') {
					if (strpos($GLOBALS['shipping']['title'], 'Correos Entrega a Domicilio') !== false) {
						if (is_object($GLOBALS[$module])) {
							$GLOBALS[$module]->tax_class = 1;
						} else {
							$GLOBALS[$module]            = new stdClass;
							$GLOBALS[$module]->tax_class = 1;
						}
					}
					if (strpos($GLOBALS['shipping']['title'], 'Entrega en Oficina de Correos') !== false) {
						if (is_object($GLOBALS[$module])) {
							$GLOBALS[$module]->tax_class = 1;
						} else {
							$GLOBALS[$module]            = new stdClass;
							$GLOBALS[$module]->tax_class = 1;
						}
					}
				}
			}


			//Correccion Impuestos
			if ((MODULE_ORDER_TOTAL_INSURANCE_TAX_CLASS > 0 && $GLOBALS[$module]->tax_class > 0) || $module == 'freeamount') {
				$tax                                         = tep_get_tax_rate(MODULE_ORDER_TOTAL_INSURANCE_TAX_CLASS, $order->delivery['country']['id'], $order->delivery['zone_id']);
				$tax_description                             = tep_get_tax_description(MODULE_ORDER_TOTAL_INSURANCE_TAX_CLASS, $order->delivery['country']['id'], $order->delivery['zone_id']);
				$tax_amount                                  = tep_calculate_tax(($this_amount), $tax);
				$order->info['tax']                          += $tax_amount;
				$order->info['tax_groups'][$tax_description] += $tax_amount;
			}
			//Fin correcciones

			$order->info['total'] += $this_amount + $tax_amount;

			$_SESSION['insurance_amount'] = $this_amount;

			$this->output[] = [
					'title'     => $this->title . ':',
					'text'      => $currencies->format($this_amount, true, $order->info['currency'], $order->info['currency_value']),
					'text_tax'  => $currencies->format($this_amount + $tax_amount, true, $order->info['currency'], $order->info['currency_value']),
					'value'     => $this_amount,
					'value_tax' => $this_amount + $tax_amount,
			];

		}
	}

	public function check() {
		if (!isset($this->check)) {
			$check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_ORDER_TOTAL_INSURANCE_STATUS'");
			$this->check = tep_db_num_rows($check_query);
		}
		return $this->check;
	}

	public function install() {
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Display Shipping Insurance', 'MODULE_ORDER_TOTAL_INSURANCE_STATUS', 'true', 'Do you want to offer Shipping Insurance?', '6', '1','tep_cfg_select_option(array(\'true\', \'false\'), ', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort Order', 'MODULE_ORDER_TOTAL_INSURANCE_SORT_ORDER', '4', 'Sort order of display.', '6', '2', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Customer Chooses?', 'MODULE_ORDER_TOTAL_INSURANCE_USE', 'false', 'Do you want the customer to have the choice?', '6', '3', 'tep_cfg_select_option(array(\'true\', \'false\'), ', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, date_added) values ('Amount Exempt From Fee', 'MODULE_ORDER_TOTAL_INSURANCE_OVER', '100', 'At what total amount do you start charging insurance?  For example, UPS insures amounts up to $100, so that is what you would put here.', '6', '4', 'currencies->format', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, date_added) values ('Increment Amount', 'MODULE_ORDER_TOTAL_INSURANCE_INCREMENT', '100', 'For each <b>how many dollars,</b> ie. the increment amount,  of the total (e.g. 100 here and .40 for the rate below would mean 40 cents fee for every $100 of the amount to be insured).', '6', '5', 'currencies->format', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, date_added) values ('Insurance Rate', 'MODULE_ORDER_TOTAL_INSURANCE_FEE', '.40', 'The amount charged per Increment Amount above.', '6', '6', 'currencies->format', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, date_added) values ('Minimum Charge', 'MODULE_ORDER_TOTAL_INSURANCE_MIN_CHARGE', '1.20', 'The minimum amount to be charged if order is over minimum total.', '6', '7', 'currencies->format', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Apply Insurance Fee To Which Orders', 'MODULE_ORDER_TOTAL_INSURANCE_DESTINATION', 'both', 'Apply insurance fee for orders sent to the set destination.', '6', '8', 'tep_cfg_select_option(array(\'national\', \'international\', \'both\'), ', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tax Class', 'MODULE_ORDER_TOTAL_INSURANCE_TAX_CLASS', '0', 'Use the following tax class on the insurance fee.', '6', '9', 'tep_get_tax_class_title', 'tep_cfg_pull_down_tax_classes(', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('International Multiplier', 'MODULE_ORDER_TOTAL_INSURANCE_INT_MULT', '1', 'For International Orders, multiply the total insurance cost by this number:', '6', '10', now())");
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
		return ['MODULE_ORDER_TOTAL_INSURANCE_STATUS', 'MODULE_ORDER_TOTAL_INSURANCE_SORT_ORDER', 'MODULE_ORDER_TOTAL_INSURANCE_USE', 'MODULE_ORDER_TOTAL_INSURANCE_OVER', 'MODULE_ORDER_TOTAL_INSURANCE_INCREMENT', 'MODULE_ORDER_TOTAL_INSURANCE_FEE', 'MODULE_ORDER_TOTAL_INSURANCE_MIN_CHARGE', 'MODULE_ORDER_TOTAL_INSURANCE_DESTINATION', 'MODULE_ORDER_TOTAL_INSURANCE_TAX_CLASS', 'MODULE_ORDER_TOTAL_INSURANCE_INT_MULT'];
	}
}
