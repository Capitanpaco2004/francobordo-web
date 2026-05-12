<?php
function bt_cmp($a, $b) {
	if ($a['final_price'] == $b['final_price']) {
		return 0;
	}

	if ($a['final_price'] < $b['final_price']) {
		return 1;
	}

	return -1;
}

// Make it pretty obvious that there's a problem
function bailout($str) {
	trigger_error($str);
	die($str);
}

define('PROD_TO_PROD', '1');
define('PROD_TO_CAT', '2');
define('CAT_TO_CAT', '3');
define('CAT_TO_PROD', '4');
define('TWOFER_PROD', '11');
define('TWOFER_CAT', '12');

class bt_discount {
	public $isvalid, $ident1, $ident2, $type, $amt, $flavor;

	public function init($ident1, $ident2, $type, $amt, $flavor) {
		$this->isvalid = 0;
		if ($type != "$" && $type != "%") {
			bailout("Bad type " . $type);
		}
		if ($flavor != PROD_TO_PROD &&
			$flavor != PROD_TO_CAT &&
			$flavor != CAT_TO_PROD &&
			$flavor != CAT_TO_CAT) {
			bailout("Bad flavor " . $flavor);
		}
		$this->ident1  = $ident1; // Product id or category
		$this->ident2  = $ident2; // Product id or category
		$this->type    = $type; // % or $
		$this->amt     = $amt; // numerical amount
		$this->flavor  = $flavor; // PROD_TO_PROD, PROD_TO_CAT, CAT_TO_CAT, CAT_TO_PROD
		$this->isvalid = 1;
	}

	public function getid() {
		return $this->ident1;
	}
}

class bt_twofer {
	public $isvalid, $ident1, $flavor;

	public function init($ident1, $flavor) {
		$this->isvalid = 0;
		if ($flavor != TWOFER_PROD &&
			$flavor != TWOFER_CAT) {
			bailout("Bad flavor " . $flavor);
		}
		$this->ident1  = $ident1; // Product id or category
		$this->flavor  = $flavor; // PROD, CAT
		$this->isvalid = 1;
	}

	public function getid() {
		return $this->ident1;
	}
}

class ot_better_together {
	public $title, $output;
	public $code, $description, $sort_order, $include_tax, $calculate_tax;
	public $credit_class, $discountlist, $twoferlist, $nocontext, $enabled, $_check;

	public function __construct() {
		$this->code          = 'ot_better_together';
		$this->title         = MODULE_ORDER_TOTAL_BETTER_TOGETHER_TITLE;
		$this->description   = MODULE_ORDER_TOTAL_BETTER_TOGETHER_DESCRIPTION;
		$this->sort_order    = (defined('MODULE_ORDER_TOTAL_BETTER_TOGETHER_SORT_ORDER') ? MODULE_ORDER_TOTAL_BETTER_TOGETHER_SORT_ORDER : 99);
		$this->include_tax   = (defined('MODULE_ORDER_TOTAL_BETTER_TOGETHER_INC_TAX') ? MODULE_ORDER_TOTAL_BETTER_TOGETHER_INC_TAX : false);
		$this->calculate_tax = (defined('MODULE_ORDER_TOTAL_BETTER_TOGETHER_CALC_TAX') ? MODULE_ORDER_TOTAL_BETTER_TOGETHER_CALC_TAX : 'Standard');
		$this->credit_class  = true;
		$this->output        = [];
		$this->discountlist  = [];
		$this->twoferlist    = [];
		$this->nocontext     = 0;
		$this->enabled       = ((defined('MODULE_ORDER_TOTAL_BETTER_TOGETHER_STATUS') && MODULE_ORDER_TOTAL_BETTER_TOGETHER_STATUS == 'true') ? true : false);
		if ($this->enabled) {
			$this->setup();
		}
	}

	public function setup() {
		// Add all linkages here
		// Some examples are provided:
		/*
		// Buy an item from category <a>, get item <x> at a discount
		$this->add_cat_to_prod(4, 83, "%", 100);
		 */
		// Buy product 83, get product 53 at 50% off
		/*        $this->add_prod_to_prod(4395, 4453, "%", 50);
		$this->add_prod_to_prod(4428, 4453, "%", 50);
		$this->add_prod_to_prod(4431, 4453, "%", 50);
		$this->add_prod_to_prod(6141, 4453, "%", 50);
		// Buy product 83, get one free
		$this->add_prod_to_prod(6167, 4769, "%", 100);
		$this->add_prod_to_prod(4836, 4769, "%", 100);
		$this->add_prod_to_prod(4837, 4769, "%", 100);
		$this->add_prod_to_prod(4845, 4769, "%", 100);
		// Buy product 83, get an item from category 14 free
		$this->add_prod_to_cat(83, 14, "%", 100);
		 */
		// Buy an item from category 21, get an item from category 14 free
		$this->add_cat_to_cat(34, 708, "%", 15);
		$this->add_cat_to_cat(686, 388, "%", 10);
		$this->add_cat_to_cat(421, 388, "%", 10);
		$this->add_cat_to_cat(686, 708, "%", 10);
		$this->add_cat_to_cat(421, 708, "%", 10);
		$this->add_cat_to_cat(784, 787, "%", 10);

		/*
		// Buy item 12, get a second one free.
		$this->add_twoforone_prod(12);
		// Buy any item from category 10, get a second identical one free
		$this->add_twoforone_cat(10);
		// $this->add_twoforone_prod(17);
		$this->add_prod_to_prod(26, 27, "%", 100);
		$this->add_prod_to_prod(83, 15, "%", 50);
		$this->add_prod_to_prod(83, 20, "%", 25);
		$this->add_cat_to_cat(14, 14, "%", 100);
		$this->add_prod_to_prod(3, 25, "%", 50);
		 */
		// Buy gps lowrance get an item from category 572 (navionics) 15% descuento
		$this->add_prod_to_cat(331849, 572, "%", 15);
		$this->add_prod_to_cat(337941, 572, "%", 15);
		$this->add_prod_to_cat(337280, 572, "%", 15);
		$this->add_prod_to_cat(337281, 572, "%", 15);
		$this->add_prod_to_cat(337282, 572, "%", 15);
	}

	public function add_cat_to_cat($ident1, $ident2, $type, $amt) {
		$d = new bt_discount;
		$d->init($ident1, $ident2, $type, $amt, CAT_TO_CAT);
		if ($d->isvalid == 1) {
			$this->discountlist[] = &$d;
		}
	}

	public function add_prod_to_cat($ident1, $ident2, $type, $amt) {
		$d = new bt_discount;
		$d->init($ident1, $ident2, $type, $amt, PROD_TO_CAT);
		if ($d->isvalid == 1) {
			$this->discountlist[] = &$d;
		}
	}

	public function setnocontext() {
		$this->nocontext = 1;
	}

	public function add_twoforone_prod($ident1) {
		$d = new bt_twofer;
		$d->init($ident1, TWOFER_PROD);
		if ($d->isvalid == 1) {
			$this->twoferlist[] = &$d;
		}
	}

	public function add_twoforone_cat($ident1) {
		$d = new bt_twofer;
		$d->init($ident1, TWOFER_CAT);
		if ($d->isvalid == 1) {
			$this->twoferlist[] = &$d;
		}
	}

	public function add_prod_to_prod($ident1, $ident2, $type, $amt) {
		$d = new bt_discount;
		$d->init($ident1, $ident2, $type, $amt, PROD_TO_PROD);
		if ($d->isvalid == 1) {
			$this->discountlist[] = &$d;
		}
	}

	public function add_cat_to_prod($ident1, $ident2, $type, $amt) {
		$d = new bt_discount;
		$d->init($ident1, $ident2, $type, $amt, CAT_TO_PROD);
		if ($d->isvalid == 1) {
			$this->discountlist[] = &$d;
		}
	}

	public function print_amount($amount) {
		global $order, $currencies;
		return $currencies->format($amount, true, $order->info['currency'], $order->info['currency_value']);
	}

	public function get_order_total() {
		global $order;
		$order_total_tax = $order->info['tax'];
		$order_total     = $order->info['total'];
		if ($this->include_tax != 'true') {
			$order_total -= $order->info['tax'];
		}

		$orderTotalFull = $order_total;
		$order_total    = ['totalFull' => $orderTotalFull, 'total' => $order_total, 'tax' => $order_total_tax];

		return $order_total;
	}

	public function process() {
		global $order, $currencies;
		$od_amount = $this->calculate_deductions();
		if ($od_amount['total'] > 0) {
			foreach ($order->info['tax_groups'] as $key => $value) {
				$tax_rate = $this->get_tax_rate_from_desc($key);
				if ($od_amount[$key]) {
					$order->info['tax_groups'][$key] -= $od_amount[$key];
					if ($this->calculate_tax != 'VAT') {
						$order->info['total'] -= $od_amount[$key];
					}
				}
			}

			$order->info['total'] = $order->info['total'] - $od_amount['total'];
			$this->output[]       = ['title' => $this->title . ':',
									 'text'  => '-' . $currencies->format($od_amount['total'], true, $order->info['currency'], $order->info['currency_value']),
									 'value' => $od_amount['total']];
		}
	}

	public function calculate_deductions() {
		global $order, $currencies;
		global $cart;
		$od_amount        = [];
		$od_amount['tax'] = 0;
		$products         = $cart->get_products();
		reset($products);
		$rc                    = usort($products, "bt_cmp");
		$discountable_products = [];
		// Build discount list
		for ($i = 0, $n = sizeof($products); $i < $n; $i++) {
			$discountable_products[$i] = $products[$i];
		}
		// Now compute discounts
		$discount = 0;
		for ($i = 0, $n = sizeof($discountable_products); $i < $n; $i++) {
			// Is it a twofer?
			if ($this->is_twofer($discountable_products[$i])) {
				$npairs                                = (int)($discountable_products[$i]['quantity'] / 2);
				$discountable_products[$i]['quantity'] -= ($npairs * 2);
				$item_discountable                     = $npairs * $discountable_products[$i]['final_price'];
				if ($this->include_tax == 'true') {
					$discount += $this->gross_up($item_discountable);
				} else {
					$discount += $item_discountable;
				}
			}
			// Otherwise, do regular bt processing
			while ($discountable_products[$i]['quantity'] > 0) {
				$discountable_products[$i]['quantity'] -= 1;
				$item_discountable                     = $this->get_discount(
					$discountable_products[$i], $discountable_products);
				if ($item_discountable == 0) {
					$discountable_products[$i]['quantity'] += 1;
					break;
				} else {
					if ($this->include_tax == 'true') {
						$discount += $this->gross_up($item_discountable);
					} else {
						$discount += $item_discountable;
					}
				}
			}
		}
		$od_amount['total'] = round($discount, 2);
		switch ($this->calculate_tax) {
			case 'Standard':
				foreach ($order->info['tax_groups'] as $key => $value) {
					$tax_rate = $this->get_tax_rate_from_desc($key);
					if ($tax_rate > 0) {
						$od_amount[$key]  = $tod_amount = round((($od_amount['total'] * $tax_rate)) / 100, 2);
						$od_amount['tax'] += $tod_amount;
					}
				}
				break;
			case 'VAT':
				foreach ($order->info['tax_groups'] as $key => $value) {
					$tax_rate = $this->get_tax_rate_from_desc($key);
					if ($tax_rate > 0) {
						$od_amount[$key]  = $tod_amount = $this->gross_down($od_amount['total']);
						$od_amount['tax'] += $tod_amount;
					}
				}
				break;
		}
		return $od_amount;
	}

	public function is_twofer($discount_item) {
		$discount = 0;
		for ($dis = 0, $n = count($this->twoferlist); $dis < $n; $dis++) {
			$li = $this->twoferlist[$dis];
			// Based on type, check ident1
			if (($li->flavor == TWOFER_PROD) &&
				($li->ident1 == $discount_item['id'])) {
				return true;
			} else if (($li->flavor == TWOFER_CAT) &&
				$this->cat_compatible($discount_item['id'], $li->ident1)) {
				return true;
			}
		}
		return false;
	}

	public function gross_up($net) {
		global $order;
		$gross_up_amt = 0;
		foreach ($order->info['tax_groups'] as $key => $value) {
			$tax_rate = $this->get_tax_rate_from_desc($key);
			if ($tax_rate > 0) {
				$gross_up_amt += round((($net * $tax_rate)) / 100, 2);
			}
		}
		return $gross_up_amt + $net;
	}

	public function get_tax_rate_from_desc($tax_desc) {
		$tax_rate = 0.00;

		$tax_descriptions = explode(' + ', $tax_desc);
		foreach ($tax_descriptions as $tax_description) {
			$tax_query_str = "SELECT tax_rate
                      FROM " . TABLE_TAX_RATES . "
                      WHERE tax_description = '" . $tax_desc . "'";

			$tax_query = tep_db_query($tax_query_str);
			if (tep_db_num_rows($tax_query)) {
				while ($tax = tep_db_fetch_array($tax_query)) {
					$tax_rate += $tax['tax_rate'];
				}
			}
		}
		return $tax_rate;
	}

	public function get_discount($discount_item, &$all_items) {
		$discount = 0;
		for ($dis = 0, $n = count($this->discountlist); $dis < $n; $dis++) {
			$li = $this->discountlist[$dis];
			// Based on type, check ident1
			if (($li->flavor == PROD_TO_PROD) ||
				($li->flavor == PROD_TO_CAT)) {
				if ($li->ident1 != $discount_item['id']) {
					continue;
				}
			} else { // CAT_TO_CAT, CAT_TO_PROD
				if (!$this->cat_compatible($discount_item['id'], $li->ident1)) {
					continue;
				}
			}
			for ($i = sizeof($all_items) - 1; $i >= 0; $i--) {
				if ($all_items[$i]['quantity'] == 0) {
					continue;
				}

				$match = 0;
				if (($li->flavor == PROD_TO_PROD) ||
					($li->flavor == CAT_TO_PROD)) {
					if ($all_items[$i]['id'] == $li->ident2) {
						$match = 1;
					}
				} else { // CAT_TO_CAT, PROD_TO_CAT
					if ($this->cat_compatible($all_items[$i]['id'], $li->ident2)) {
						$match = 1;
					}
				}
				if ($match == 1) {
					$all_items[$i]['quantity'] -= 1;
					if ($li->type == "$") {
						$discount = $li->amt;
					} else {
						$discount = $all_items[$i]['final_price'] *
							$li->amt / 100;
					}
					return $discount;
				}
			}
		}
		return 0;
	}

	// Required for 'VAT'

	public function cat_compatible($products_id, $id2) {
		$category_query = tep_db_query("select p2c.categories_id from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c where p.products_id = '" . (int)$products_id . "' and p.products_status = '1' and p.products_id = p2c.products_id");
		if (tep_db_num_rows($category_query)) {
			while ($category = tep_db_fetch_array($category_query)) {
				if ($category['categories_id'] == $id2) {
					return true;
				}

			}
		}

		return false;
	}

	public function gross_down($figure) {
		global $order;
		$gross_up_amt = 0;
		foreach ($order->info['tax_groups'] as $key => $value) {
			$tax_rate = $this->get_tax_rate_from_desc($key);
			if ($tax_rate > 0) {
				$amt          += $figure / (1 + ($tax_rate / 100));
				$gross_up_amt += round($amt, 2);
			}
		}
		return $figure - $gross_up_amt;
	}

	public function pre_confirmation_check($order_total) {
		$od_amount = $this->calculate_deductions();
		return $od_amount['total'] + $od_amount['tax'];
	}

	public function credit_selection() {
		return $selection;
	}

	public function collect_posts() {
	}

	public function update_credit_account($i) {
	}

	public function apply_credit() {
	}

	public function check() {
		if (!isset($this->_check)) {
			$check_query  = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_ORDER_TOTAL_BETTER_TOGETHER_STATUS'");
			$this->_check = tep_db_num_rows($check_query);
		}
		return $this->_check;
	}

	public function install() {
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('&copy; That Software Guy<br >This module is installed', 'MODULE_ORDER_TOTAL_BETTER_TOGETHER_STATUS', 'true', '', '6', '1','tep_cfg_select_option(array(\'true\'), ', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort Order', 'MODULE_ORDER_TOTAL_BETTER_TOGETHER_SORT_ORDER', '2', 'Sort order of display.', '6', '12', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function ,date_added) values ('Include Tax', 'MODULE_ORDER_TOTAL_BETTER_TOGETHER_INC_TAX', 'false', 'Include Tax in calculation.', '6', '13','tep_cfg_select_option(array(\'true\', \'false\'), ', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function ,date_added) values ('Re-calculate Tax', 'MODULE_ORDER_TOTAL_BETTER_TOGETHER_CALC_TAX', 'Standard', 'Re-Calculate Tax', '6', '14','tep_cfg_select_option(array(\'None\', \'Standard\', \'VAT\'), ', now())");
	}

	public function remove() {
		tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
	}

	public function keys() {
		return ['MODULE_ORDER_TOTAL_BETTER_TOGETHER_STATUS', 'MODULE_ORDER_TOTAL_BETTER_TOGETHER_SORT_ORDER', 'MODULE_ORDER_TOTAL_BETTER_TOGETHER_INC_TAX', 'MODULE_ORDER_TOTAL_BETTER_TOGETHER_CALC_TAX'];
	}

	// call this in product info page

	public function get_discount_info($id) {
		global $order, $currencies;
		global $languages_id;
		$response_arr = [];

		for ($dis = 0, $n = count($this->twoferlist); $dis < $n; $dis++) {
			$li    = $this->twoferlist[$dis];
			$match = 0;
			if (($li->flavor == TWOFER_PROD) &&
				($li->ident1 == $id)) {
				$match = 1;
				if ($this->nocontext == 0) {
					$disc_string = TWOFER_PROMO_STRING;
				} else {
					$disc_link   = '<a href="' . tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $li->ident1) . '">' . tep_get_products_name($li->ident1, $languages_id) . '</a>';
					$disc_string = sprintf(TWOFER_QUALIFY_STRING, $disc_link);
				}
			} else if (($li->flavor == TWOFER_CAT) &&
				$this->cat_compatible($id, $li->ident1)) {
				$match = 1;
				if ($this->nocontext == 0) {
					$disc_string = TWOFER_PROMO_STRING;
				} else {
					$disc_link   = '<a href="' . tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $id) . '">' . tep_get_products_name($id, $languages_id) . '</a>';
					$disc_string = sprintf(TWOFER_QUALIFY_STRING, $disc_link);
				}
			}
			if ($match == 1) {
				$response_arr[] = $disc_string;
				continue;
			}
		}
		for ($dis = 0, $n = count($this->discountlist); $dis < $n; $dis++) {
			$li    = $this->discountlist[$dis];
			$match = 0;
			if (($li->flavor == PROD_TO_PROD) &&
				($li->ident1 == $id)) {
				$match     = 1;
				$disc_link = '<a href="' . tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $li->ident2) . '">' . tep_get_products_name($li->ident2, $languages_id) . '</a>';
			} else if (($li->flavor == PROD_TO_CAT) &&
				($li->ident1 == $id)) {
				$match     = 1;
				$disc_link = '<a href="' . tep_href_link(FILENAME_DEFAULT, 'cPath=' . $li->ident2) . '">' . $this->get_category_name($li->ident2, $languages_id) . '</a>';
			} else if (($li->flavor == CAT_TO_CAT) &&
				$this->cat_compatible($id, $li->ident1)) {
				$match     = 1;
				$disc_link = '<a href="' . tep_href_link(FILENAME_DEFAULT, 'cPath=' . $li->ident2) . '">' . $this->get_category_name($li->ident2, $languages_id) . '</a>';
			} else if (($li->flavor == CAT_TO_PROD) &&
				$this->cat_compatible($id, $li->ident1)) {
				$match     = 1;
				$disc_link = '<a href="' . tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $li->ident2) . '">' . tep_get_products_name($li->ident2, $languages_id) . '</a>';
			}
			if ($match == 1) {
				if ($this->nocontext == 0) {
					$disc_string = BUY_THIS_ITEM;
				} else {
					$disc_string = QUALIFY;
				}

				if (($li->flavor == PROD_TO_PROD) ||
					($li->flavor == CAT_TO_PROD)) {
					$disc_string .= GET_THIS;
				} else {
					$disc_string .= GET_ANY;
				}
				if (($li->flavor == PROD_TO_PROD) &&
					($li->ident1 == $li->ident2) &&
					($this->nocontext == 0)) {
					$disc_string .= SECOND_ONE;
				} else {
					$disc_string .= $disc_link;
				}
				$disc_string .= " ";
				if ($li->type == "%") {
					if ($li->amt != 100) {
						$str_amt    = $li->amt . "%";
						$off_string = sprintf(OFF_STRING_PCT, $str_amt);
					} else {
						$off_string = FREE_STRING;
					}
					$disc_string .= $off_string;
				} else {
					$curr_string = $currencies->format($li->amt, true, $order->info['currency'], $order->info['currency_value']);
					$off_string  = sprintf(OFF_STRING_CURR, $curr_string);
					$disc_string .= $off_string;
				}
				$response_arr[] = $disc_string;
			}
		}
		return $response_arr;
	}
	// call this in product info page to show what to do to get a
	// discount (the opposite way.)

	public function get_category_name($cat_id, $language = '') {
		global $languages_id;

		if (empty($language)) {
			$language = $languages_id;
		}

		$category_query = tep_db_query("select categories_name from " . TABLE_CATEGORIES_DESCRIPTION . " where categories_id = '" . (int)$cat_id . "' and language_id = '" . (int)$language . "'");
		$category       = tep_db_fetch_array($category_query);

		return $category['categories_name'];
	}

	public function get_reverse_discount_info($id) {
		global $order, $currencies;
		global $languages_id;
		$response_arr = [];

		for ($dis = 0, $n = count($this->discountlist); $dis < $n; $dis++) {
			$li    = $this->discountlist[$dis];
			$match = 0;
			if ($li->ident2 == $li->ident1) {
				continue;
			}
			$this_string = REV_GET_DISC;
			if (($li->flavor == PROD_TO_PROD) &&
				($li->ident2 == $id)) {
				$match     = 1;
				$disc_link = '<a href="' . tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $li->ident1) . '">' . tep_get_products_name($li->ident1, $languages_id) . '</a>';
				if ($this->nocontext == 1) {
					$this_string = GET_YOUR_PROD . tep_get_products_name($li->ident2, $languages_id);
				}
			} else if (($li->flavor == PROD_TO_CAT) &&
				$this->cat_compatible($id, $li->ident2)) {
				$match     = 1;
				$disc_link = '<a href="' . tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $li->ident1) . '">' . tep_get_products_name($li->ident1, $languages_id) . '</a>';
				if ($this->nocontext == 1) {
					$this_string = GET_YOUR_CAT . $this->get_category_name($li->ident2, $languages_id);
				}
			} else if (($li->flavor == CAT_TO_CAT) &&
				$this->cat_compatible($id, $li->ident2)) {
				$match     = 1;
				$disc_link = '<a href="' . tep_href_link(FILENAME_DEFAULT, 'cPath=' . $li->ident1) . '">' . $this->get_category_name($li->ident1, $languages_id) . '</a>';
				if ($this->nocontext == 1) {
					$this_string = GET_YOUR_CAT . $this->get_category_name($li->ident2, $languages_id);
				}
			} else if (($li->flavor == CAT_TO_PROD) &&
				($li->ident2 == $id)) {
				$match     = 1;
				$disc_link = '<a href="' . tep_href_link(FILENAME_DEFAULT, 'cPath=' . $li->ident1) . '">' . $this->get_category_name($li->ident1, $languages_id) . '</a>';
				if ($this->nocontext == 1) {
					$this_string = GET_YOUR_PROD . tep_get_products_name($li->ident2, $languages_id);
				}
			}
			if ($match == 1) {
				if (($li->flavor == PROD_TO_PROD) ||
					($li->flavor == PROD_TO_CAT)) {
					$disc_string = REV_GET_THIS;
				} else { // CAT_TO_CAT, CAT_TO_PROD
					$disc_string = REV_GET_ANY;
				}
				$disc_string .= $disc_link;
				$disc_string .= $this_string;
				if ($li->type == "%") {
					if ($li->amt != 100) {
						$str_amt    = $li->amt . "%";
						$off_string = sprintf(OFF_STRING_PCT, $str_amt);
					} else {
						$off_string = FREE_STRING;
					}
					$disc_string .= $off_string;
				} else {
					$curr_string = $currencies->format($li->amt, true, $order->info['currency'], $order->info['currency_value']);
					$off_string  = sprintf(OFF_STRING_CURR, $curr_string);
					$disc_string .= $off_string;
				}
				$response_arr[] = $disc_string;
			}
		}
		return $response_arr;
	}
}
