<?php

use Ramsey\Uuid\Uuid;
use util\event;

// Sampedro: Inicio, Atributos por tipo //
// Array con las opciones que son insertacciones por parte del usuario como puede ser introducir texto, una imagen etc
$aOptionsInsertUser = [];

class shoppingCart {
	var $contents, $total, $weight, $cartID, $content_type;
	public $hasModified;
	public $cg_id;
	public $uuid;

	function __construct() {
		$this->hasModified = false;
		$this->reset();
	}

	function reset($reset_database = false) {
		global $customer_id;

		$this->contents     = [];
		$this->total        = 0;
		$this->weight       = 0;
		$this->content_type = false;

		if (tep_session_is_registered('customer_id') && ($reset_database == true)) {
			tep_db_query("delete from " . TABLE_CUSTOMERS_BASKET . " where customers_id = '" . (int)$customer_id . "'");
			tep_db_query("delete from " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " where customers_id = '" . (int)$customer_id . "'");
		}

		unset($this->cartID);

		if (tep_session_is_registered('cartID')) {
			tep_session_unregister('cartID');
		}

		$this->syncHasCartFlag();
	}

	function restore_contents() {
		global $customer_id, $languages_id; // languages_id needed for PriceFormatter - QPBPP

		if (!tep_session_is_registered('customer_id')) return false;

		// insert current cart contents in database
		if (is_array($this->contents)) {
			reset($this->contents);

			// BOF check product status and remove products from the table customers_basket that don't have status 1 (active)
			$products_status_query = tep_db_query("select products_id from " . TABLE_CUSTOMERS_BASKET . " where customers_id = '" . (int)$customer_id . "'");
			$products_id_array     = [];
			while ($products_status_result = tep_db_fetch_array($products_status_query)) {
				$products_id_array[] = tep_get_prid($products_status_result['products_id']);
			}
			// due to attributes there might be multiple instances of products_id's
			$products_id_array = array_unique($products_id_array);
			if (count($products_id_array) > 0) {
				$products_query = tep_db_query("select p.products_id, p.products_status from " . TABLE_PRODUCTS . " p where p.products_id in (" . implode(",", $products_id_array) . ")");
				while ($products_statuses = tep_db_fetch_array($products_query)) {
					if ($products_statuses['products_status'] != '1') {
						tep_db_query("delete from " . TABLE_CUSTOMERS_BASKET . " where customers_id = '" . (int)$customer_id . "' and (products_id = '" . (int)$products_statuses['products_id'] . "' or products_id like '" . (int)$products_statuses['products_id'] . "{%')");
						tep_db_query("delete from " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " where customers_id = '" . (int)$customer_id . "' and (products_id = '" . (int)$products_statuses['products_id'] . "' or products_id like '" . (int)$products_statuses['products_id'] . "{%')");
					}
				} // end while ($products_statuses = tep_db_fetch_array($products_query))
			} // end if (count($products_id_array) > 0)
			// EOF check product status and remove products from the table customers_basket that don't have status 1 (active)


			// BOF SPPC attribute hide/invalid check: loop through the shopping cart and check the attributes if they
			// are hidden for the now logged-in customer
			$this->cg_id = $this->get_customer_group_id();
			foreach (array_keys($this->contents) as $products_id) {
				// only check attributes if they are set for the product in the cart
				if (isset($this->contents[$products_id]['attributes'])) {
					$check_attributes_query = tep_db_query("select options_id, options_values_id, IF(find_in_set('" . $this->cg_id . "', attributes_hide_from_groups) = 0, '0', '1') as hide_attr_status from " . TABLE_PRODUCTS_ATTRIBUTES . " where products_id = '" . tep_get_prid($products_id) . "'");
					while ($_check_attributes = tep_db_fetch_array($check_attributes_query)) {
						$check_attributes[] = $_check_attributes;
					} // end while ($_check_attributes = tep_db_fetch_array($check_attributes_query))
					$no_of_check_attributes = count($check_attributes);
					$change_products_id     = '0';

					foreach ($this->contents[$products_id]['attributes'] as $attr_option => $attr_option_value) {
						$valid_option = '0';
						for ($x = 0; $x < $no_of_check_attributes; $x++) {
							if ($attr_option == $check_attributes[$x]['options_id'] && $attr_option_value == $check_attributes[$x]['options_values_id']) {
								$valid_option = '1';
								if ($check_attributes[$x]['hide_attr_status'] == '1') {
									// delete hidden attributes from array attributes, change products_id accordingly later
									$change_products_id = '1';
									unset($this->contents[$products_id]['attributes'][$attr_option]);
								}
							} // end if ($attr_option == $check_attributes[$x]['options_id']....
						} // end for ($x = 0; $x < $no_of_check_attributes ; $x++)
						if ($valid_option == '0') {
							// after having gone through the options for this product and not having found a matching one
							// we can conclude that apparently this is not a valid option for this product so remove it
							unset($this->contents[$products_id]['attributes'][$attr_option]);
							// change products_id accordingly later
							$change_products_id = '1';
						}
					} // end foreach($this->contents[$products_id]['attributes'] as $attr_option => $attr_option_value)

					if ($change_products_id == '1') {
						$original_products_id = $products_id;
						$products_id          = tep_get_prid($original_products_id);
						$products_id          = tep_get_uprid($products_id, $this->contents[$original_products_id]['attributes']);
						// add the product without the hidden attributes to the cart
						$this->contents[$products_id] = $this->contents[$original_products_id];
						// delete the originally added product with the hidden attributes
						unset($this->contents[$original_products_id]);
					}
				} // end if (isset($this->contents[$products_id]['attributes']))
			}
			reset($this->contents); // reset the array otherwise the cart will be emptied
			// EOF SPPC attribute hide/invalid check
			foreach (array_keys($this->contents) as $products_id) {
				$qty = $this->contents[$products_id]['qty'];
				// BOF QPBPP for SPPC adjust quantity blocks and min_order_qty for this customer group
				// warnings about this are raised in PriceFormatter
				$pf = new PriceFormatter;
				$pf->loadProduct(tep_get_prid($products_id), $languages_id);
				$qty = $pf->adjustQty($qty);
				// EOF QPBPP for SPPC
				$product_query = tep_db_query("select products_id from " . TABLE_CUSTOMERS_BASKET . " where customers_id = '" . (int)$customer_id . "' and products_id = '" . tep_db_input($products_id) . "'");
				if (!tep_db_num_rows($product_query)) {
					tep_db_query("insert into " . TABLE_CUSTOMERS_BASKET . " (customers_id, products_id, customers_basket_quantity, customers_basket_date_added) values ('" . (int)$customer_id . "', '" . tep_db_input($products_id) . "', '" . tep_db_input($qty) . "', '" . date('Ymd') . "')");
					if (isset($this->contents[$products_id]['attributes'])) {
						ksort($this->contents[$products_id]['attributes']);
						foreach ($this->contents[$products_id]['attributes'] as $option => $value) {
							// OTF contrib begins
							//tep_db_query("insert into " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " (customers_id, products_id, products_options_id, products_options_value_id) values ('" . $customer_id . "', '" . $products_id . "', '" . $option . "', '" . $value . "')");
							$attr_value = nl2br((string)($this->contents[$products_id]['attributes_values'][$option] ?? ''));
							$query_raw  = "insert into " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " (customers_id, products_id, products_options_id, products_options_value_id, products_options_value_text) values ('" . (int)$customer_id . "', '" . tep_db_input($products_id) . "', '" . (int)$option . "', '" . (int)$value . "', '" . tep_db_input($attr_value) . "')";
							tep_db_query($query_raw);
							// OTF contrib ends
						}
					}
				} else {
					tep_db_query("update " . TABLE_CUSTOMERS_BASKET . " set customers_basket_quantity = '" . tep_db_input($qty) . "' where customers_id = '" . (int)$customer_id . "' and products_id = '" . tep_db_input($products_id) . "'");
				}
			}
		}

		// reset per-session cart contents, but not the database contents
		$this->reset(false);

		$products_query = tep_db_query("select cb.products_id, ptdc.discount_categories_id, customers_basket_quantity from " . TABLE_CUSTOMERS_BASKET . " cb left join (select products_id, discount_categories_id from " . TABLE_PRODUCTS_TO_DISCOUNT_CATEGORIES . " where customers_group_id = '" . $this->cg_id . "') as ptdc on cb.products_id = ptdc.products_id where customers_id = '" . (int)$customer_id . "'");
		while ($products = tep_db_fetch_array($products_query)) {
			$this->contents[$products['products_id']] = ['qty' => $products['customers_basket_quantity'], 'discount_categories_id' => $products['discount_categories_id']];
			// attributes
			$attributes_query = tep_db_query("select products_options_id, products_options_value_id from " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " where customers_id = '" . (int)$customer_id . "' and products_id = '" . tep_db_input($products['products_id']) . "'");
			while ($attributes = tep_db_fetch_array($attributes_query)) {
				$this->contents[$products['products_id']]['attributes'][$attributes['products_options_id']] = $attributes['products_options_value_id'];
			}
		}

		$this->cleanup();
		// assign a temporary unique ID to the order contents to prevent hack attempts during the checkout procedure
		$this->cartID = $this->generate_cart_id();

		$this->syncHasCartFlag();
	}

	function get_customer_group_id() {
		if (isset($_SESSION['sppc_customer_group_id']) && $_SESSION['sppc_customer_group_id'] != '0') {
			$_cg_id = $_SESSION['sppc_customer_group_id'];
		} else {
			$_cg_id = 0;
		}
		return $_cg_id;
	}

	function cleanup() {
		global $customer_id;

		foreach (array_keys($this->contents) as $key) {
			if ($this->contents[$key]['qty'] < 1) {
				unset($this->contents[$key]);
				// remove from database
				if (tep_session_is_registered('customer_id')) {
					tep_db_query("delete from " . TABLE_CUSTOMERS_BASKET . " where customers_id = '" . (int)$customer_id . "' and products_id = '" . tep_db_input($key) . "'");
					tep_db_query("delete from " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " where customers_id = '" . (int)$customer_id . "' and products_id = '" . tep_db_input($key) . "'");
				}
			}
		}

		$this->syncHasCartFlag();
	}
	function generate_cart_id($length = 5) {
		return tep_create_random_value($length, 'digits');
	}

	public function getHasModified() {
		// Si estamos en editor de pedido pasamos de esto
		if (array_key_exists('curl_oe', $_GET)) {
			return false;
		}

		return $this->hasModified;
	}

	function remove($products_id) {
		global $customer_id;

		// Sampedro: Inicio, Atributos por tipo //
		// Si contiene atributos
		if (strstr($products_id, '{')) {
			// Obtenemos el id del producto
			$sProductsId = (int)preg_replace('/\{.+$/i', '', $products_id);
			// Obtenemos un array con los atributos
			$aAux = tep_get_array_uprid($products_id);

			// Obtenemos el real products_id
			$products_id = tep_get_uprid($sProductsId, $aAux);
		}
		// Sampedro: Fin, Atributos por tipo //

		// Eliminamos
		unset($this->contents[preg_replace('/\@/i', '%40', $products_id)]);

		// remove from database
		if (tep_session_is_registered('customer_id')) {
			tep_db_query("delete from " . TABLE_CUSTOMERS_BASKET . " where customers_id = '" . (int)$customer_id . "' and products_id = '" . tep_db_input($products_id) . "'");
			tep_db_query("delete from " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " where customers_id = '" . (int)$customer_id . "' and products_id = '" . tep_db_input($products_id) . "'");
		}

		// assign a temporary unique ID to the order contents to prevent hack attempts during the checkout procedure
		$this->cartID = $this->generate_cart_id();

		$this->syncHasCartFlag();
	}

	function remove_all() {
		$this->reset();
	}

	function get_product_id_list() {
		$product_id_list = '';
		if (is_array($this->contents)) {
			foreach (array_keys($this->contents) as $products_id) {
				$product_id_list .= ', ' . $products_id;
			}
		}

		return substr($product_id_list, 2);
	}

	function show_total() {
		$this->calculate();

		return $this->total;
	}

	function calculate() {
		global $currencies, $languages_id, $pfs; // for QPBPP added: $languages_id, $pfs

		$this->total  = 0;
		$this->weight = 0;
		if (!is_array($this->contents)) return 0;
		// BOF Separate Pricing Per Customer
		// global variable (session) $sppc_customer_group_id -> class variable cg_id
		$this->cg_id = $this->get_customer_group_id();
		// EOF Separate Pricing Per Customer
		// BOF QPBPP for SPPC
		$discount_category_quantity = []; // calculates no of items per discount category in shopping basket
		foreach ($this->contents as $products_id => $contents_array) {
			if (tep_not_null($contents_array['discount_categories_id'] ?? '')) {
				if (!isset($discount_category_quantity[$contents_array['discount_categories_id']])) {
					$discount_category_quantity[$contents_array['discount_categories_id']] = $contents_array['qty'];
				} else {
					$discount_category_quantity[$contents_array['discount_categories_id']] += $contents_array['qty'];
				}
			}
		} // end foreach

		$pf = new PriceFormatter;
		// EOF QPBPP for SPPC
		foreach (array_keys($this->contents) as $products_id) {
			$qty = $this->contents[$products_id]['qty'];

			// BOF QPBPP for SPPC
			if (tep_not_null($this->contents[$products_id]['discount_categories_id'] ?? '')) {
				$nof_items_in_cart_same_cat       = $discount_category_quantity[$this->contents[$products_id]['discount_categories_id']];
				$nof_other_items_in_cart_same_cat = $nof_items_in_cart_same_cat - $qty;
			} else {
				$nof_other_items_in_cart_same_cat = 0;
			}
			// EOF QPBPP for SPPC
			// products price
			// BOF QPBPP for SPPC
			$pf->loadProduct($products_id, $languages_id);
			if ($product = $pfs->getPriceFormatterData($products_id)) {
				$prid           = $product['products_id'];
				$products_tax   = tep_get_tax_rate($product['products_tax_class_id']);
				$products_price = $pf->computePrice($qty, $nof_other_items_in_cart_same_cat);
				// EOF QPBPP for SPPC
				$products_cost   = $product['products_cost'];
				$products_weight = $product['products_weight'];

				// BOF Separate Pricing Per Customer
				/*   $specials_price = tep_get_products_special_price((int)$prid);
					  if (tep_not_null($specials_price)) {
					 $products_price = $specials_price;
					  } elseif ($this->cg_id != 0){
						$customer_group_price_query = tep_db_query("select customers_group_price from " . TABLE_PRODUCTS_GROUPS . " where products_id = '" . (int)$prid . "' and customers_group_id =  '" . $this->cg_id . "'");
						if ($customer_group_price = tep_db_fetch_array($customer_group_price_query)) {
						$products_price = $customer_group_price['customers_group_price'];
						}
						  } */
				// EOF Separate Pricing Per Customer

				$this->total  += $currencies->calculate_price($products_price, $products_tax, $qty);
				$this->weight += ($qty * $products_weight);
			}

			// BOF SPPC attributes mod
			if (isset($this->contents[$products_id]['attributes'])) {
				reset($this->contents[$products_id]['attributes']);
				$where = " AND ((";
				foreach ($this->contents[$products_id]['attributes'] as $option => $value) {
					$where .= "options_id = '" . (int)$option . "' AND options_values_id = '" . (int)$value . "') OR (";
				}
				$where = substr($where, 0, -5) . ')';

				$attribute_price_query = tep_db_query("SELECT products_attributes_id, options_values_price, price_prefix, options_values_weight, weight_prefix FROM " . TABLE_PRODUCTS_ATTRIBUTES . " WHERE products_id = '" . (int)$products_id . "'" . $where . "");

				if (tep_db_num_rows($attribute_price_query)) {
					$list_of_prdcts_attributes_id = '';

					$attribute_price = [];
					while ($attributes_price_array = tep_db_fetch_array($attribute_price_query)) {
						$attribute_price[]            = $attributes_price_array;
						$list_of_prdcts_attributes_id .= $attributes_price_array['products_attributes_id'] . ",";
					}

					if (tep_not_null($list_of_prdcts_attributes_id) && $this->cg_id != '0') {
						$select_list_of_prdcts_attributes_ids = "(" . substr($list_of_prdcts_attributes_id, 0, -1) . ")";
						$pag_query                            = tep_db_query("select products_attributes_id, options_values_price, price_prefix, options_values_weight, weight_prefix from " . TABLE_PRODUCTS_ATTRIBUTES_GROUPS . " where products_attributes_id IN " . $select_list_of_prdcts_attributes_ids . " AND customers_group_id = '" . $this->cg_id . "'");
						while ($pag_array = tep_db_fetch_array($pag_query)) {
							$cg_attr_prices[] = $pag_array;
						}


						// substitute options_values_price and prefix for those for the customer group (if available)
						if ($customer_group_id != '0' && tep_not_null($cg_attr_prices)) {
							for ($n = 0; $n < count($attribute_price); $n++) {
								for ($i = 0; $i < count($cg_attr_prices); $i++) {
									if ($cg_attr_prices[$i]['products_attributes_id'] == $attribute_price[$n]['products_attributes_id']) {
										$attribute_price[$n]['price_prefix']         = $cg_attr_prices[$i]['price_prefix'];
										$attribute_price[$n]['options_values_price'] = $cg_attr_prices[$i]['options_values_price'];
									}
								} // end for ($i = 0; $i < count($cg_att_prices) ; $i++)
							}
						} // end if ($customer_group_id != '0' && (tep_not_null($cg_attr_prices))
					} // end if (tep_not_null($list_of_prdcts_attributes_id) && $customer_group_id != '0')

					// now loop through array $attribute_price to add up/substract attribute prices + weight
					for ($n = 0; $n < count($attribute_price); $n++) {
						if ($attribute_price[$n]['price_prefix'] == '-')
							$this->total -= abs($currencies->calculate_price($attribute_price[$n]['options_values_price'], $products_tax, $qty));
						else
							$this->total += ($currencies->calculate_price($attribute_price[$n]['options_values_price'], $products_tax, $qty));

						if ($attribute_price[$n]['weight_prefix'] == '-')
							$this->weight -= $qty * $attribute_price[$n]['options_values_weight'];
						else
							$this->weight += $qty * $attribute_price[$n]['options_values_weight'];
					}
				} // end if (tep_db_num_rows($attribute_price_query))
			} // end if (isset($this->contents[$products_id]['attributes']))
		}
	}

	function getPriceById($nProductsId) {
		// Obtenemos productos del carrito
		$aProductsPrice = $this->get_products();

		// Recorremos productos
		foreach ($aProductsPrice as $nIdProduct => $aProduct) {
			// Si el producto es el mismo que el enviado
			if ($nProductsId == $aProduct['id']) {
				// Si el precio final es igual precio original, retornamos su precio
				if ($aProduct['final_price'] == $aProduct['price_org'])
					return $aProduct['final_price'];
				// Si no, retornamos -1 para indicar que es un producto en oferta
				else
					return -1;
			}
		}

		return 0;
	}

	function get_products() {
		global $languages_id, $pfs, $currencies, $aOptionsInsertUser; // PriceFormatterStore added
		// BOF Separate Pricing Per Customer
		$this->cg_id = $this->get_customer_group_id();
		// EOF Separate Pricing Per Customer

		if (!is_array($this->contents)) return false;
		// BOF QPBPP for SPPC
		$discount_category_quantity = [];
		foreach ($this->contents as $products_id => $contents_array) {
			if (tep_not_null($contents_array['discount_categories_id'] ?? '')) {
				if (!isset($discount_category_quantity[$contents_array['discount_categories_id']])) {
					$discount_category_quantity[$contents_array['discount_categories_id']] = $contents_array['qty'];
				} else {
					$discount_category_quantity[$contents_array['discount_categories_id']] += $contents_array['qty'];
				}
			}
		} // end foreach

		$pf = new PriceFormatter;
		// EOF QPBPP for SPPC

		$products_array = [];

		foreach (array_keys($this->contents) as $products_id) {

			$pf->loadProduct($products_id, $languages_id); // does query if necessary and adds to
			// PriceFormatterStore or gets info from it next
			if ($products = $pfs->getPriceFormatterData($products_id)) {
				if (tep_not_null($this->contents[$products_id]['discount_categories_id'] ?? '')) {
					$nof_items_in_cart_same_cat       = $discount_category_quantity[$this->contents[$products_id]['discount_categories_id']];
					$nof_other_items_in_cart_same_cat = $nof_items_in_cart_same_cat - $this->contents[$products_id]['qty'];
				} else {
					$nof_other_items_in_cart_same_cat = 0;
				}
				$products_price = $pf->computePrice($this->contents[$products_id]['qty'], $nof_other_items_in_cart_same_cat);
				// BOF add-weight-to-product-attributes with UPSxml mod

				// determine total weight of attributes to add to weight of product
				$attributes_total_weight = 0;

				if (isset($this->contents[$products_id]['attributes'])) {


					$where = ' AND ((';

					foreach ($this->contents[$products_id]['attributes'] as $option => $value) {

						$where .= 'options_id=' . $option . ' AND options_values_id=' . $value . ') OR (';

					}

					$where = substr($where, 0, -5) . ')';


					$attribute_weight_query = tep_db_query('SELECT options_values_weight FROM ' . TABLE_PRODUCTS_ATTRIBUTES . ' WHERE products_id=' . (int)$products_id . $where);

					if (tep_db_num_rows($attribute_weight_query)) {
						while ($attributes_weight_array = tep_db_fetch_array($attribute_weight_query)) {
							$attributes_total_weight += $attributes_weight_array['options_values_weight'];
						}
					}
				} // end if (isset($this->contents[$products_id]['attributes']))

				// EOF add-weight-to-product-attributes mod
				$nAtribute       = $this->attributes_price($products_id);
				$attributes      = (isset($this->contents[$products_id]['attributes']) ? $this->contents[$products_id]['attributes'] : '');
				$attributes_info = [];
				if (isset($attributes) && is_array($attributes)) {
					foreach ($attributes as $option => $value) {
						$attributes_query  = tep_db_query("select popt.products_options_name, popt.products_options_track_stock, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pa.reference, pa.products_attributes_ean
																					from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa
																					where pa.products_id = '" . (int)$products_id . "'
																						and pa.options_id = '" . (int)$option . "'
																						and pa.options_id = popt.products_options_id
																						" . (!in_array($option, $aOptionsInsertUser) ? "and pa.options_values_id = '" . (int)$value . "'" : "") . "
																						and pa.options_values_id = poval.products_options_values_id
																						and popt.language_id = '" . (int)$languages_id . "'
																						and poval.language_id = '" . (int)$languages_id . "'");
						$attributes_values = tep_db_fetch_array($attributes_query);

						if (in_array($option, $aOptionsInsertUser)) {
							$attributes_values['products_options_values_name'] = nl2br(urldecode($value));
						}

						// Si el atributo tiene referencia lo anidamos el Modelo
						if (isset($attributes_values['reference']) && $attributes_values['reference'] != '') {
							//$products['products_model'] .= ' ' . $attributes_values['reference'];
							$products['products_model'] = $attributes_values['reference'];
						}

						// Comprobamos si hemos podido obtener el atributo o este ha sido eliminado
						// de ser asi, es que se ha producido una modificación
						if ($attributes_values['products_options_name'] == '') {
							$aInfo['has_modified'] = true;
							$this->hasModified     = true;
						}

						$attributes_info[] = [
							'products_options_name'        => $attributes_values['products_options_name'],
							'options_values_id'            => $value,
							'products_options_values_name' => $attributes_values['products_options_values_name'],
							'options_values_price'         => $attributes_values['options_values_price'],
							'price_prefix'                 => $attributes_values['price_prefix'],
							'reference'                    => $attributes_values['reference'],
							'track_stock'                  => $attributes_values['products_options_track_stock'],
						];

						if (!$attributes_values['products_options_track_stock']) {
							$productsId = tep_get_prid($aInfo['id']);

							if (!isset($productsIdTrackStockQuantity[$productsId])) {
								$productsIdTrackStockQuantity[$productsId] = ['indexes' => [], 'quantity' => 0];
							}

							$productsIdTrackStockQuantity[$productsId]['indexes'][] = $index;
							$productsIdTrackStockQuantity[$productsId]['quantity']  += $aInfo['quantity'];
						}
					}
				}

				$products_array[] = ['id'                     => $products_id,
									 'name'                   => $products['products_name'],
									 'model'                  => $products['products_model'],
									 'image'                  => $products['products_image'],
									 'discount_categories_id' => $this->contents[$products_id]['discount_categories_id'] ?? '',
									 'price'                  => $products_price,
									 'price_org'              => $products['products_price'] + $this->attributes_price($products_id),
									 'cost'                   => $products['products_cost'],
									 'quantity'               => $this->contents[$products_id]['qty'],
									 'weight'                 => $products['products_weight'] + $attributes_total_weight,
									 'final_price'            => ($products_price + $nAtribute),
									 'price_format'           => $currencies->display_price(($products_price + $nAtribute), tep_get_tax_rate($products['products_tax_class_id']), $this->contents[$products_id]['qty']),
									 'tax_class_id'           => $products['products_tax_class_id'],
									 'attributes_info'        => $attributes_info,
									 'href'                   => tep_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $products_id),
									 'attributes'             => $attributes];
			}
		}

		return $products_array;
	}

function attributes_price($products_id) {
		global $customer_group_id;

		$this->cg_id = $this->get_customer_group_id();

		if (isset($this->contents[$products_id]['attributes'])) {
			$where = " AND ((";
			foreach ($this->contents[$products_id]['attributes'] as $option => $value) {
				$where .= "options_id = '" . (int)$option . "' AND options_values_id = '" . (int)$value . "') OR (";
			}
			$where = substr($where, 0, -5) . ')';

			$attribute_price_query = tep_db_query("SELECT products_attributes_id, options_values_price, price_prefix FROM " . TABLE_PRODUCTS_ATTRIBUTES . " WHERE products_id = '" . (int)$products_id . "'" . $where . "");

			if (tep_db_num_rows($attribute_price_query)) {
				$list_of_prdcts_attributes_id = '';
				while ($attributes_price_array = tep_db_fetch_array($attribute_price_query)) {
					$attribute_price[]            = $attributes_price_array;
					$list_of_prdcts_attributes_id .= $attributes_price_array['products_attributes_id'] . ",";
				}

				if (tep_not_null($list_of_prdcts_attributes_id) && $this->cg_id != '0') {
					$select_list_of_prdcts_attributes_ids = "(" . substr($list_of_prdcts_attributes_id, 0, -1) . ")";
					$pag_query                            = tep_db_query("select products_attributes_id, options_values_price, price_prefix from " . TABLE_PRODUCTS_ATTRIBUTES_GROUPS . " where products_attributes_id IN " . $select_list_of_prdcts_attributes_ids . " AND customers_group_id = '" . $this->cg_id . "'");
					while ($pag_array = tep_db_fetch_array($pag_query)) {
						$cg_attr_prices[] = $pag_array;
					}

					// substitute options_values_price and prefix for those for the customer group (if available)
					if ($customer_group_id != '0' && tep_not_null($cg_attr_prices)) {
						for ($n = 0; $n < count($attribute_price); $n++) {
							for ($i = 0; $i < count($cg_attr_prices); $i++) {
								if ($cg_attr_prices[$i]['products_attributes_id'] == $attribute_price[$n]['products_attributes_id']) {
									$attribute_price[$n]['price_prefix']         = $cg_attr_prices[$i]['price_prefix'];
									$attribute_price[$n]['options_values_price'] = $cg_attr_prices[$i]['options_values_price'];
								}
							} // end for ($i = 0; $i < count($cg_att_prices) ; $i++)
						}
					} // end if ($customer_group_id != '0' && (tep_not_null($cg_attr_prices))
				} // end if (tep_not_null($list_of_prdcts_attributes_id) && $customer_group_id != '0')
				// now loop through array $attribute_price to add up/substract attribute prices

				$attributes_price = 0; // init: evita "Undefined variable" en PHP 8 (se acumula en el bucle)
				for ($n = 0; $n < count($attribute_price); $n++) {
					if ($attribute_price[$n]['price_prefix'] == '-') {
						$attributes_price -= abs($attribute_price[$n]['options_values_price']);
					} else {
						$attributes_price += ($attribute_price[$n]['options_values_price']);
					}
				} // end for ($n = 0 ; $n < count($attribute_price); $n++)

				return $attributes_price;
			} else { // end if (tep_db_num_rows($attribute_price_query))
				return 0;
			}
		} else { // end if (isset($this->contents[$products_id]['attributes']))
			return 0;
		}
	} // end of function attributes_price, modified for SPPC with attributes

	function descuento_promo() {
		$nResult   = 0;
		$aProducts = $this->get_products();

		// Recorremos productos del carrito
		foreach ($aProducts as $nIdProduct => $aProduct) {
			if (!isset($this->contents[$aProduct['id']]['promotion']))
				continue;

			foreach ($this->contents[$aProduct['id']]['promotion'] as $aPromotion) {
				$nQty      = $aPromotion['qty'];
				$nDiscount = $aPromotion['discount'];

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

		return $nResult;
	}

	function show_weight() {
		$this->calculate();

		return $this->weight;
	}

	function get_content_type() {
		$this->content_type = false;

		if ((DOWNLOAD_ENABLED == 'true') && ($this->count_contents() > 0)) {
			foreach (array_keys($this->contents) as $products_id) {
				if (isset($this->contents[$products_id]['attributes'])) {

					foreach ($this->contents[$products_id]['attributes'] as $value) {
						$virtual_check_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS_ATTRIBUTES . " pa, " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad where pa.products_id = '" . (int)$products_id . "' and pa.options_values_id = '" . (int)$value . "' and pa.products_attributes_id = pad.products_attributes_id");
						$virtual_check       = tep_db_fetch_array($virtual_check_query);

						if ($virtual_check['total'] > 0) {
							switch ($this->content_type) {
								case 'physical':
									$this->content_type = 'mixed';

									return $this->content_type;
									break;
								default:
									$this->content_type = 'virtual';
									break;
							}
						} else {
							switch ($this->content_type) {
								case 'virtual':
									$this->content_type = 'mixed';

									return $this->content_type;
									break;
								default:
									$this->content_type = 'physical';
									break;
							}
						}
					}
				} else {
					switch ($this->content_type) {
						case 'virtual':
							$this->content_type = 'mixed';

							return $this->content_type;
							break;
						default:
							$this->content_type = 'physical';
							break;
					}
				}
			}
		} else {
			$this->content_type = 'physical';
		}

		return $this->content_type;
	}

	function count_contents() {  // get total number of items in cart
		$total_items = 0;
		if (is_array($this->contents)) {
			foreach (array_keys($this->contents) as $products_id) {
				$total_items += $this->get_quantity($products_id);
			}
		}

		return $total_items;
	}

	function get_quantity($products_id) {
		if (isset($this->contents[$products_id])) {
			return $this->contents[$products_id]['qty'];
		} else {
			return 0;
		}
	}

	function unserialize($broken) {
		foreach ($broken->vars as $kv) {
			$key = $kv['key'];
			if (gettype($this->$key) != "user function")
				$this->$key = $kv['value'];
		}
	}

	function addProductAjax() {
		global $_POST;

		$aProductos = tep_db_prepare_input($_POST['products']);

		// Recorremos los productos para comprar
		foreach ($aProductos as $aProducto) {
			// Sampedro: Inicio, Atributos por tipo //
			// Si contenemos atributos
			if (is_array($aProducto['id']) && count($aProducto['id']) > 0) {
				$aAuxs           = $aProducto['id'];
				$aProducto['id'] = [];

				foreach ($aAuxs as $aAux)
					$aProducto['id'][$aAux[0]] = $aAux[1];
			}

			// Si hemos comprado alguna cantidad
			if ($aProducto['cart_quantity'] > 0) {
				if (array_key_exists('email_tarjeta', $aProducto)) {
					$real_ids = ['email' => urldecode($aProducto['email_tarjeta'])];
					$this->add_cart($aProducto['products_id'], $this->get_quantity(tep_get_uprid_tarjeta($aProducto['products_id'], $real_ids)) + 1, $real_ids, true, true);
				} else
					$this->add_cart($aProducto['products_id'], $this->get_quantity(tep_get_uprid($aProducto['products_id'], $aProducto['id'])) + $aProducto['cart_quantity'], $aProducto['id']);
				event::getInstance()->execute('add_to_cart');
			}
		}
	}

	function add_cart($products_id, $qty = '1', $attributes = '', $notify = true) {
		global $new_products_id_in_cart, $customer_id;
		// BOF Separate Pricing Per Customer
		$this->cg_id = $this->get_customer_group_id();
		// EOF Separate Pricing Per Customer

		$products_id_string = tep_get_uprid($products_id, $attributes);
		$products_id        = tep_get_prid($products_id_string);

		if (defined('MAX_QTY_IN_CART') && (MAX_QTY_IN_CART > 0) && ((int)$qty > MAX_QTY_IN_CART)) {
			$qty = MAX_QTY_IN_CART;
		}
		$pf = new PriceFormatter;
		$pf->loadProduct($products_id);
		$qty               = $pf->adjustQty($qty);
		$discount_category = $pf->get_discount_category();

		$attributes_pass_check = true;


		if (is_array($attributes) && !empty($attributes)) {
			reset($attributes);
			foreach ($attributes as $option => $value) {
				if (!is_numeric($option) || !is_numeric($value)) {
					$attributes_pass_check = false;
					break;
				} else {
					$check_query = tep_db_query("select products_attributes_id from " . TABLE_PRODUCTS_ATTRIBUTES . " where products_id = '" . (int)$products_id . "' and options_id = '" . (int)$option . "' and options_values_id = '" . (int)$value . "' limit 1");

					if (tep_db_num_rows($check_query) < 1) {
						$attributes_pass_check = false;
						break;
					}
				}
			}
		} else if (tep_has_product_attributes($products_id)) {
			$attributes_pass_check = false;
		}

		// Sampedro: Editor de pedidos, si mandamos por GET dicha variable, pasamos de los atributos
		if (array_key_exists('curl_oe', $_GET))
			$attributes_pass_check = true;

		if (is_numeric($products_id) && is_numeric($qty) && ($attributes_pass_check == true)) {


			// BOF SPPC attribute hide check, original query expanded to include attributes
			$check_product_query = tep_db_query("select p.products_status, options_id, options_values_id, IF(find_in_set('" . $this->cg_id . "', attributes_hide_from_groups) = 0, '0', '1') as hide_attr_status from " . TABLE_PRODUCTS . " p left join " . TABLE_PRODUCTS_ATTRIBUTES . " using(products_id) where p.products_id = '" . (int)$products_id . "'");
			while ($_check_product = tep_db_fetch_array($check_product_query)) {
				$check_product[] = $_check_product;
			} // end while ($_check_product = tep_db_fetch_array($check_product_query))
			$no_of_check_product = count($check_product);

			if (is_array($attributes)) {
				foreach ($attributes as $attr_option => $attr_option_value) {
					$valid_option = '0';
					for ($x = 0; $x < $no_of_check_product; $x++) {
						if ($attr_option == $check_product[$x]['options_id'] && $attr_option_value == $check_product[$x]['options_values_id']) {
							$valid_option = '1';
							if ($check_product[$x]['hide_attr_status'] == '1') {
								// delete hidden attributes from array attributes
								unset($attributes[$attr_option]);
							}
						} // end if ($attr_option == $check_product[$x]['options_id']....
					} // end for ($x = 0; $x < $no_of_check_product ; $x++)
					if ($valid_option == '0') {
						// after having gone through the options for this product and not having found a matching one
						// we can conclude that apparently this is not a valid option for this product so remove it
						unset($attributes[$attr_option]);
					}
				} // end foreach($attributes as $attr_option => $attr_option_value)
			} // end if (is_array($attributes))
			// now attributes have been checked and hidden and invalid ones deleted make the $products_id_string again
			$products_id_string = tep_get_uprid($products_id, $attributes);

			// Sampedro: Editor de pedidos, si mandamos por GET dicha variable, pasamos de los atributos
			if (array_key_exists('curl_oe', $_GET) && isset($check_product) && tep_not_null($check_product))
				$check_product[0]['products_status'] = 1;

			if ((isset($check_product) && tep_not_null($check_product)) && ($check_product[0]['products_status'] == '1')) {
				// EOF SPPC attribute hide check
				if ($notify == true) {
					$new_products_id_in_cart = $products_id;
					tep_session_register('new_products_id_in_cart');
				}


				// Obtenemos la cantidad de productos del carrito y si queremos controlar el stock
				$aStock = tep_db_query('SELECT products_quantity, check_stock FROM ' . TABLE_PRODUCTS . ' WHERE products_id = "' . $products_id . '";');
				$aStock = tep_db_fetch_array($aStock);

				// Si SI tenemos atributos
				if (is_array($attributes) && count($attributes) > 0) {
					// Variables
					$sAttributes = '';

					// Obtenemos los atributos del producto
					foreach ($attributes as $nAttribute => $aAttribute)
						$sAttributes .= $nAttribute . '-' . $aAttribute . ',';
					$sAttributes = substr($sAttributes, 0, -1);

					// Obtenemos el stock del producto
					$aAux = tep_db_query('SELECT products_stock_quantity FROM products_stock WHERE products_id = "' . $products_id . '" AND products_stock_attributes = "' . $sAttributes . '";');

					// Si tenemos registro
					if (tep_db_num_rows($aAux) > 0) {
						// Registro
						$aAux = tep_db_fetch_array($aAux);

						// Si superamos el número de stock
						if ($qty > $aAux['products_stock_quantity']) {
							// Si queremos controlar el stock
							if ($aStock['check_stock'] == 1) {
								// Establecemos la cantidad como tope del stock
								$qty = $aAux['products_stock_quantity'];

								// Mensaje informativo
								echo '<script type="text/javascript">alert("Solo tenemos ' . $aAux['products_stock_quantity'] . ' unidad/es en stock del producto. Se añadirán ' . $aAux['products_stock_quantity'] . ' unidades a tu carrito." );</script>';
							} // Si NO queremos controlar el stock
							else {
								// Si el cliente ya confirmó vía modal JS (showStockConfirm), no
								// emitimos el alert nativo. Si no, emitimos fallback informativo
								// usando el modal nuevo si está disponible.
								if ($aAux['products_stock_quantity'] > 0 && empty($_REQUEST['stock_confirmed'])) {
									$jsName = json_encode((string)$aAux['products_stock_quantity'], JSON_UNESCAPED_UNICODE);
									$jsQty  = json_encode((string)$qty, JSON_UNESCAPED_UNICODE);
									echo '<script type="text/javascript">if(typeof window.showStockConfirm==="function"){window.showStockConfirm({stock:' . (int)$aAux['products_stock_quantity'] . ',qty:' . (int)$qty . ',mode:"info"});}else{alert("Solo tenemos "+' . $jsName . '+" unidad/es en stock del producto. Comprando "+' . $jsQty . '+" unidades el plazo de entrega es de 7-10 días laborables.");}</script>';
								}
							}
						}
					}
				} // Si NO tenemos atributos
				else {
					// Sampedro: Editor de pedidos, si mandamos por GET dicha variable, pasamos del stock
					if (!array_key_exists('curl_oe', $_GET)) {
						// Si superamos el número de stock
						if ($qty > $aStock['products_quantity']) {
							// Si queremos controlar el stock
							if ($aStock['check_stock'] == 1) {
								// Establecemos la cantidad como tope del stock
								$qty = $aStock['products_quantity'];

								// Mensaje informativo
								echo '<script type="text/javascript">alert("Solo tenemos ' . $aStock['products_quantity'] . ' unidad/es en stock del producto. Se añadirán ' . $aStock['products_quantity'] . ' unidades a tu carrito." );</script>';
							} // Si NO queremos controlar el stock
							else {
								// Mismo patrón: skip si el cliente ya confirmó vía modal JS;
								// fallback al modal info si está disponible, sino alert nativo.
								if ($aStock['products_quantity'] > 0 && empty($_REQUEST['stock_confirmed'])) {
									$jsName = json_encode((string)$aStock['products_quantity'], JSON_UNESCAPED_UNICODE);
									$jsQty  = json_encode((string)$qty, JSON_UNESCAPED_UNICODE);
									echo '<script type="text/javascript">if(typeof window.showStockConfirm==="function"){window.showStockConfirm({stock:' . (int)$aStock['products_quantity'] . ',qty:' . (int)$qty . ',mode:"info"});}else{alert("Solo tenemos "+' . $jsName . '+" unidad/es en stock del producto. Comprando "+' . $jsQty . '+" unidades el plazo de entrega es de 7-10 días laborables.");}</script>';
								}
							}
						}
					}
				}
				if ($this->in_cart($products_id_string)) {
					$this->update_quantity($products_id_string, $qty, $attributes, $discount_category);
				} else {
					$this->contents[$products_id_string] = ['qty' => (int)$qty, 'discount_categories_id' => $discount_category];
					// insert into database
					if (tep_session_is_registered('customer_id')) tep_db_query("insert into " . TABLE_CUSTOMERS_BASKET . " (customers_id, products_id, customers_basket_quantity, customers_basket_date_added) values ('" . (int)$customer_id . "', '" . tep_db_input($products_id_string) . "', '" . (int)$qty . "', '" . date('Ymd') . "')");

					if (is_array($attributes)) {
						foreach ($attributes as $option => $value) {
							$this->contents[$products_id_string]['attributes'][$option] = $value;
							// insert into database
							if (tep_session_is_registered('customer_id')) tep_db_query("insert into " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " (customers_id, products_id, products_options_id, products_options_value_id) values ('" . (int)$customer_id . "', '" . tep_db_input($products_id_string) . "', '" . (int)$option . "', '" . (int)$value . "')");
						}
					}
				}

				$this->cleanup();

				// assign a temporary unique ID to the order contents to prevent hack attempts during the checkout procedure
				$this->cartID = $this->generate_cart_id();

				$this->syncHasCartFlag();
			}
		}
	}

	// added for Separate Pricing Per Customer, returns customer_group_id

	function in_cart($products_id) {
		if (isset($this->contents[$products_id])) {
			return true;
		} else {
			return false;
		}
	}

	function update_quantity($products_id, $quantity = '', $attributes = '') {
		global $customer_id;

		$products_id_string = tep_get_uprid($products_id, $attributes);
		$products_id        = tep_get_prid($products_id_string);

		if (defined('MAX_QTY_IN_CART') && (MAX_QTY_IN_CART > 0) && ((int)$quantity > MAX_QTY_IN_CART)) {
			$quantity = MAX_QTY_IN_CART;
		}

		$attributes_pass_check = true;

		if (is_array($attributes)) {
			foreach ($attributes as $option => $value) {
				if (!is_numeric($option) || !is_numeric($value)) {
					$attributes_pass_check = false;
					break;
				}
			}
		}

		if (is_numeric($products_id) && isset($this->contents[$products_id_string]) && is_numeric($quantity) && ($attributes_pass_check == true)) {
			$this->contents[$products_id_string] = ['qty' => (int)$quantity];
			// update database
			if (tep_session_is_registered('customer_id')) tep_db_query("update " . TABLE_CUSTOMERS_BASKET . " set customers_basket_quantity = '" . (int)$quantity . "' where customers_id = '" . (int)$customer_id . "' and products_id = '" . tep_db_input($products_id_string) . "'");

			if (is_array($attributes)) {
				foreach ($attributes as $option => $value) {
					$this->contents[$products_id_string]['attributes'][$option] = $value;
					// update database
					if (tep_session_is_registered('customer_id')) tep_db_query("update " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " set products_options_value_id = '" . (int)$value . "' where customers_id = '" . (int)$customer_id . "' and products_id = '" . tep_db_input($products_id_string) . "' and products_options_id = '" . (int)$option . "'");
				}
			}

			// assign a temporary unique ID to the order contents to prevent hack attempts during the checkout procedure
			$this->cartID = $this->generate_cart_id();

			$this->syncHasCartFlag();
		}
	}

	function getHtmlCart() {
		global $cart, $currencies;

		// Incluimos el componente carrito
		include(DIR_WS_COMPONENTS . 'shoppingCartDown.php');
		if (tep_session_is_registered('addToCartEvent')) {
			echo $_SESSION['addToCartEvent'];
			tep_session_unregister('addToCartEvent');
		}
	}

	function count_ship_contents() {
		// get total number of items in cart
		$total_items = 0;
		if (is_array($this->contents)) {
			foreach (array_keys($this->contents) as $products_id) {
				/*** BEGIN - Free shipping per product 1.0 ***/
				$check_free_shipping_query   = tep_db_query("select products_ship_free from " . TABLE_PRODUCTS . " where products_id = '" . (int)$products_id . "'");
				$check_free_shipping         = tep_db_fetch_array($check_free_shipping_query);
				$check_free_shipping_array[] = $check_free_shipping['products_ship_free'];
				if (in_array("1", $check_free_shipping_array) && !in_array("0", $check_free_shipping_array)) {
				} else {
					$total_items += $this->get_quantity($products_id);
				}
				/*** END Free - shipping per product 1.0 ***/
			}
		}

		return $total_items;
	}

	public function getUuid() {
		if (is_null($this->uuid) || $this->uuid == '') {
			$this->uuid = Uuid::uuid4()->toString();
		}

		return $this->uuid;
	}

	/**
	 * Sincroniza el flag has_cart según el estado actual de $this->contents.
	 * — Si hay al menos un producto y has_cart = 0, lo pone a 1.
	 * — Si no hay ningún producto y has_cart = 1, lo pone a 0.
	 */
	private function syncHasCartFlag(): void {
		$sesskey = tep_db_input(tep_session_id());
		if ($this->count_contents() > 0) {
			tep_db_query(" UPDATE customers_session SET has_cart = 1 WHERE sesskey = '{$sesskey}' AND has_cart = 0");
		} else {
			tep_db_query(" UPDATE customers_session SET has_cart = 0 WHERE sesskey = '{$sesskey}'  AND has_cart = 1");
		}
	}
}
