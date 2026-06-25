<?php

class order {
	public $info;
	public $totals;
	public $products;
	public $customer;
	public $delivery;
	public $billing;
	public $content_type;
	public $coupon;

	public function __construct($order_id = '') {
		$this->info     = [];
		$this->totals   = [];
		$this->products = [];
		$this->customer = [];
		$this->delivery = [];

		if (tep_not_null($order_id)) {
			$this->query($order_id);
		} else {
			$this->cart();
		}
	}

	public function query($order_id) {
		global $languages_id;

		$order_id = tep_db_prepare_input($order_id);

		$order_query = tep_db_query("select customers_id, customers_name, customers_company, customers_street_address, customers_suburb, customers_city, customers_postcode, customers_state, customers_country, customers_telephone, customers_email_address, customers_address_format_id, delivery_name, delivery_company, delivery_street_address, delivery_telephone, delivery_suburb, delivery_city, delivery_postcode, delivery_state, delivery_country, delivery_address_format_id, billing_name, billing_company, billing_nif, billing_street_address, billing_suburb, billing_city, billing_postcode, billing_state, billing_country, billing_address_format_id, payment_method, cc_type, cc_owner, cc_number, cc_expires, currency, currency_value, date_purchased, orders_status, last_modified, orders_status, CFACTUR from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "'");
		$order       = tep_db_fetch_array($order_query);

		$totals_query = tep_db_query("select title, text, value, class from " . TABLE_ORDERS_TOTAL . " where orders_id = '" . (int)$order_id . "' order by sort_order");
		while ($totals = tep_db_fetch_array($totals_query)) {
			$this->totals[] = ['title' => $totals['title'],
							   'text'  => $totals['text'],
							   'value' => $totals['value'],
							   'class' => $totals['class']];
		}

		$order_total_query = tep_db_query("select text from " . TABLE_ORDERS_TOTAL . " where orders_id = '" . (int)$order_id . "' and class = 'ot_total'");
		$order_total       = tep_db_fetch_array($order_total_query);

		$shipping_method_query = tep_db_query("select title from " . TABLE_ORDERS_TOTAL . " where orders_id = '" . (int)$order_id . "' and class = 'ot_shipping'");
		$shipping_method       = tep_db_fetch_array($shipping_method_query);

		$order_status_query = tep_db_query("select orders_status_name from " . TABLE_ORDERS_STATUS . " where orders_status_id = '" . $order['orders_status'] . "' and language_id = '" . (int)$languages_id . "'");
		$order_status       = tep_db_fetch_array($order_status_query);

		$this->info = ['orders_id'             => (int)$order_id,
					   'currency'              => $order['currency'],
					   'currency_value'        => $order['currency_value'],
					   'payment_method'        => $order['payment_method'],
					   'cc_type'               => $order['cc_type'],
					   'cc_owner'              => $order['cc_owner'],
					   'cc_number'             => $order['cc_number'],
					   'cc_expires'            => $order['cc_expires'],
					   'orders_status_id'      => $order['orders_status'],
					   'CFACTUR'               => $order['CFACTUR'],
					   'date_purchased'        => $order['date_purchased'],
					   'orders_status'         => $order_status['orders_status_name'],
					   'last_modified'         => $order['last_modified'],
					   'total'                 => strip_tags((string)($order_total['text'] ?? '')),
					   'customers_language_id' => $order['customers_language_id'],
					   'shipping_method'       => ((substr((string)($shipping_method['title'] ?? ''), -1) == ':') ? substr(strip_tags((string)($shipping_method['title'] ?? '')), 0, -1) : strip_tags((string)($shipping_method['title'] ?? '')))];

		$this->customer = ['id'             => $order['customers_id'],
						   'name'           => $order['customers_name'],
						   'company'        => $order['customers_company'],
						   'street_address' => $order['customers_street_address'],
						   'suburb'         => $order['customers_suburb'],
						   'city'           => $order['customers_city'],
						   'postcode'       => $order['customers_postcode'],
						   'state'          => $order['customers_state'],
						   'country'        => ['title' => $order['customers_country']],
						   'format_id'      => $order['customers_address_format_id'],
						   'telephone'      => $order['customers_telephone'],
						   'email_address'  => $order['customers_email_address']];

		$this->delivery = ['name'           => trim((string)($order['delivery_name'] ?? '')),
						   'company'        => $order['delivery_company'],
						   'street_address' => $order['delivery_street_address'],
						   'telephone'      => $order['delivery_telephone'],
						   'suburb'         => $order['delivery_suburb'],
						   'city'           => $order['delivery_city'],
						   'postcode'       => $order['delivery_postcode'],
						   'state'          => $order['delivery_state'],
						   'country'        => ['title' => $order['delivery_country']],
						   'format_id'      => $order['delivery_address_format_id']];

		if (empty($this->delivery['name']) && empty($this->delivery['street_address'])) {
			$this->delivery = false;
		}

		$this->billing = ['name'           => $order['billing_name'],
						  'company'        => $order['billing_company'],
						  'nif'            => $order['billing_nif'],
						  'street_address' => $order['billing_street_address'],
						  'suburb'         => $order['billing_suburb'],
						  'city'           => $order['billing_city'],
						  'postcode'       => $order['billing_postcode'],
						  'state'          => $order['billing_state'],
						  'country'        => ['title' => $order['billing_country']],
						  'format_id'      => $order['billing_address_format_id']];

		$index                 = 0;
		$orders_products_query = tep_db_query("select orders_products_id, products_returned, products_exchanged, products_exchanged_id, products_id, products_name, products_model, product_ean, products_ubicacion, products_price, products_cost, products_tax, products_quantity, final_price from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . (int)$order_id . "'");
		while ($orders_products = tep_db_fetch_array($orders_products_query)) {
			$this->products[$index] = ['qty'         => $orders_products['products_quantity'],
									   'id'          => $orders_products['products_id'],
									   'name'        => $orders_products['products_name'],
									   'model'       => $orders_products['products_model'],
									   'ean'         => $orders_products['product_ean'],
									   'ubicacion'   => $orders_products['products_ubicacion'],
									   'tax'         => $orders_products['products_tax'],
									   'price'       => $orders_products['products_price'],
									   'cost'        => $orders_products['products_cost'],
									   'final_price' => $orders_products['final_price'],
									   'id'          => $orders_products['products_id'],
									   'return'      => $orders_products['products_returned'],
									   'exchange'    => $orders_products['products_exchanged'],
									   'exchange_id' => $orders_products['products_exchanged_id']];

			$subindex         = 0;
			$attributes_query = tep_db_query("select products_options, products_options_values, products_attributes_ean, options_values_price, price_prefix from " . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . " where orders_id = '" . (int)$order_id . "' and orders_products_id = '" . (int)$orders_products['orders_products_id'] . "'");
			if (tep_db_num_rows($attributes_query)) {
				while ($attributes = tep_db_fetch_array($attributes_query)) {
					$this->products[$index]['attributes'][$subindex] = ['option' => $attributes['products_options'],
																		'value'  => $attributes['products_options_values'],
																		'prefix' => $attributes['price_prefix'],
																		'price'  => $attributes['options_values_price'],
																		'ean'    => $attributes['products_attributes_ean']];
					$subindex++;
				}
			}

			$this->info['tax_groups']["{$this->products[$index]['tax']}"] = '1';

			$index++;
		}
	}

	public function cart(int $sendto_estimator = 0) {
		global $_POST, $customer_id, $sendto, $billto, $cart, $languages_id, $currency, $currencies, $shipping, $payment, $comments, $customer_default_address_id;

		$this->content_type = $cart->get_content_type();

		if (($this->content_type != 'virtual') && ($sendto == false)) {
			$sendto = $customer_default_address_id;
		}

		/**
		 * #XCC-313-91043
		 *
		 * @author Daniel Lucia <daniel.lucia@denox.es>
		 */
		$type_comission = '';
		$affiliate      = Affiliates::getAffiliateByID(intval($_SESSION['id_affiliate'] ?? 0));

		if (!empty($affiliate)) {
			$type_comission = $affiliate['type_comission'];
		}

		$customer_address_query = tep_db_query("select c.customers_firstname, c.customers_lastname, c.customers_telephone, c.customers_email_address, ab.entry_company, ab.entry_street_address, ab.entry_telephone, ab.entry_suburb, ab.entry_postcode, IF(ci.name IS NULL,ab.entry_city , ci.name) AS entry_city, ab.entry_zone_id, z.zone_name, co.countries_id, co.countries_name, co.countries_iso_code_2, co.countries_iso_code_3, co.address_format_id, ab.entry_state from " . TABLE_CUSTOMERS . " c, " . TABLE_ADDRESS_BOOK . " ab
	  left join " . TABLE_ZONES . " z on (ab.entry_zone_id = z.zone_id)
	  left join " . TABLE_COUNTRIES . " co on (ab.entry_country_id = co.countries_id)
	  LEFT JOIN cities ci ON ci.id = ab.entry_city_id
	  where c.customers_id = '" . (int)$customer_id . "' and ab.customers_id = '" . (int)$customer_id . "' and c.customers_default_address_id = ab.address_book_id");
		$customer_address       = tep_db_fetch_array($customer_address_query);
		if (!is_array($customer_address)) $customer_address = [];

		if ($sendto_estimator > 0) {
			$shipping_address_query = tep_db_query("select ab.entry_zone_id, ab.entry_firstname, ab.entry_lastname, ab.entry_company, ab.entry_street_address, ab.entry_telephone, ab.entry_suburb, ab.entry_postcode, ab.entry_city, IF(ci.name IS NULL,ab.entry_city , ci.name) AS entry_city, z.zone_name, ab.entry_country_id, c.countries_id, c.countries_name, c.countries_iso_code_2, c.countries_iso_code_3, c.address_format_id, ab.entry_state from " . TABLE_ADDRESS_BOOK . " ab left join " . TABLE_ZONES . " z on (ab.entry_zone_id = z.zone_id) left join " . TABLE_COUNTRIES . " c on (ab.entry_country_id = c.countries_id) LEFT JOIN cities ci ON ci.id = ab.entry_city_id where ab.customers_id = '" . (int)$customer_id . "' and ab.address_book_id = '" . (int)$sendto_estimator . "'");
			$shipping_address       = tep_db_fetch_array($shipping_address_query);
			if (!is_array($shipping_address)) $shipping_address = [];
		} else if (is_array($sendto) && !empty($sendto)) {
			$shipping_address = ['entry_firstname'      => $sendto['firstname'],
								 'entry_lastname'       => $sendto['lastname'],
								 'entry_company'        => $sendto['company'],
								 'entry_street_address' => $sendto['street_address'],
								 'entry_telephone'      => $sendto['telephone'],
								 'entry_suburb'         => $sendto['suburb'],
								 'entry_postcode'       => $sendto['postcode'],
								 'entry_city'           => $sendto['city'],
								 'entry_zone_id'        => $sendto['zone_id'],
								 'zone_name'            => $sendto['zone_name'],
								 'entry_country_id'     => $sendto['country_id'],
								 'countries_id'         => $sendto['country_id'],
								 'countries_name'       => $sendto['country_name'],
								 'countries_iso_code_2' => $sendto['country_iso_code_2'],
								 'countries_iso_code_3' => $sendto['country_iso_code_3'],
								 'address_format_id'    => $sendto['address_format_id'],
								 'entry_state'          => $sendto['zone_name']];
		} else if (is_numeric($sendto)) {
			$shipping_address_query = tep_db_query("select ab.entry_zone_id, ab.entry_firstname, ab.entry_lastname, ab.entry_company, ab.entry_street_address, ab.entry_telephone, ab.entry_suburb, ab.entry_postcode, ab.entry_city, IF(ci.name IS NULL,ab.entry_city , ci.name) AS entry_city, z.zone_name, ab.entry_country_id, c.countries_id, c.countries_name, c.countries_iso_code_2, c.countries_iso_code_3, c.address_format_id, ab.entry_state from " . TABLE_ADDRESS_BOOK . " ab left join " . TABLE_ZONES . " z on (ab.entry_zone_id = z.zone_id) left join " . TABLE_COUNTRIES . " c on (ab.entry_country_id = c.countries_id) LEFT JOIN cities ci ON ci.id = ab.entry_city_id where ab.customers_id = '" . (int)$customer_id . "' and ab.address_book_id = '" . (int)$sendto . "'");
			$shipping_address       = tep_db_fetch_array($shipping_address_query);
		} else {
			$shipping_address = ['entry_firstname'      => NULL,
								 'entry_lastname'       => NULL,
								 'entry_company'        => NULL,
								 'entry_street_address' => NULL,
								 'entry_telephone'      => NULL,
								 'entry_suburb'         => NULL,
								 'entry_postcode'       => NULL,
								 'entry_city'           => NULL,
								 'entry_zone_id'        => STORE_ZONE,
								 'zone_name'            => NULL,
								 'entry_country_id'     => DEFAULT_COUNTRY,
								 'countries_id'         => DEFAULT_COUNTRY,
								 'countries_name'       => NULL,
								 'countries_iso_code_2' => NULL,
								 'countries_iso_code_3' => NULL,
								 'address_format_id'    => 0,
								 'entry_state'          => NULL];
		}

		if (is_array($billto) && !empty($billto)) {
			$billing_address = ['entry_firstname'      => $billto['firstname'],
								'entry_lastname'       => $billto['lastname'],
								'entry_company'        => $billto['company'],
								'entry_street_address' => $billto['street_address'],
								'entry_suburb'         => $billto['suburb'],
								'entry_postcode'       => $billto['postcode'],
								'entry_city'           => $billto['city'],
								'entry_zone_id'        => $billto['zone_id'],
								'zone_name'            => $billto['zone_name'],
								'entry_country_id'     => $billto['country_id'],
								'countries_id'         => $billto['country_id'],
								'countries_name'       => $billto['country_name'],
								'countries_iso_code_2' => $billto['country_iso_code_2'],
								'countries_iso_code_3' => $billto['country_iso_code_3'],
								'address_format_id'    => $billto['address_format_id'],
								'entry_state'          => $billto['zone_name']];
		} else {
			$billing_address_query = tep_db_query("select ab.entry_firstname, ab.entry_lastname, ab.entry_company, ab.entry_nif, ab.entry_street_address, ab.entry_telephone, ab.entry_suburb, ab.entry_postcode, IF(ci.name IS NULL,ab.entry_city , ci.name) AS entry_city, ab.entry_zone_id, z.zone_name, ab.entry_country_id, c.countries_id, c.countries_name, c.countries_iso_code_2, c.countries_iso_code_3, c.address_format_id, ab.entry_state from " . TABLE_ADDRESS_BOOK . " ab left join " . TABLE_ZONES . " z on (ab.entry_zone_id = z.zone_id) left join " . TABLE_COUNTRIES . " c on (ab.entry_country_id = c.countries_id) LEFT JOIN cities ci ON ci.id = ab.entry_city_id where ab.customers_id = '" . (int)$customer_id . "' and ab.address_book_id = '" . (int)$billto . "'");
			$billing_address       = tep_db_fetch_array($billing_address_query);
		}

		if ($this->content_type == 'virtual') {
			$tax_address = ['entry_country_id' => $billing_address['entry_country_id'],
							'entry_zone_id'    => $billing_address['entry_zone_id']];
		} else {
			$tax_address = ['entry_country_id' => $shipping_address['entry_country_id'],
							'entry_zone_id'    => $shipping_address['entry_zone_id']];
		}

		// #FB-IVA-RECOGIDA (2026-06-24): en "Recogida en tienda" el IVA se calcula
		// segun la ubicacion de la tienda de recogida, no la direccion del cliente.
		// Peninsula/Baleares => IVA normal; Canarias/Ceuta/Melilla => exento.
		if (isset($shipping['id']) && $shipping['id'] === 'retira_retira') {
			$pickup_zone = fb_pickup_store_tax_zone($_SESSION['store_id'] ?? 0);
			$tax_address = ['entry_country_id' => $pickup_zone['country_id'],
							'entry_zone_id'    => $pickup_zone['zone_id']];
		}

		$this->info = ['order_id'        => (int)($orders_id ?? 0),
					   'order_status'    => DEFAULT_ORDERS_STATUS_ID,
					   'currency'        => $currency,
					   'currency_value'  => $currencies->currencies[$currency]['value'],
					   'payment_method'  => $payment,
					   'cc_type'         => '',
					   'cc_owner'        => '',
					   'cc_number'       => '',
					   'cc_expires'      => '',
					   'shipping_method' => ($shipping != NULL && array_key_exists('title', $shipping) ? $shipping['title'] : ''),
					   'shipping_cost'   => ($shipping != NULL && array_key_exists('cost', $shipping) ? $shipping['cost'] : 0),
					   'subtotal'        => 0,
					   'tax'             => 0,
					   'tax_groups'      => [],
					   'comments'        => (tep_session_is_registered('comments') && !empty($comments) ? $comments : ''),
					   'total_affiliate' => 0];

		if (isset($GLOBALS[$payment]) && is_object($GLOBALS[$payment])) {
			if (isset($GLOBALS[$payment]->public_title)) {
				$this->info['payment_method'] = $GLOBALS[$payment]->public_title;
			} else {
				$this->info['payment_method'] = $GLOBALS[$payment]->title;
			}

			if (isset($GLOBALS[$payment]->order_status) && is_numeric($GLOBALS[$payment]->order_status) && ($GLOBALS[$payment]->order_status > 0)) {
				$this->info['order_status'] = $GLOBALS[$payment]->order_status;
			}
		}

		//Parche NIF PayPal
		if (is_array($billing_address) && ($billing_address['entry_nif'] ?? '') == '') {
			$billing_address['entry_nif'] = $_SESSION['customer_NIF'] ?? '';
		}

		$this->customer = [
			'firstname'      => isset($customer_address['customers_firstname']) ? $customer_address['customers_firstname'] : '',
			'lastname'       => isset($customer_address['customers_lastname']) ? $customer_address['customers_lastname'] : '',
			'company'        => isset($customer_address['entry_company']) ? $customer_address['entry_company'] : '',
			'nif'            => (is_array($billing_address) && isset($billing_address['entry_nif'])) ? $billing_address['entry_nif'] : '',
			'street_address' => isset($customer_address['entry_street_address']) ? $customer_address['entry_street_address'] : '',
			'suburb'         => $customer_address['entry_suburb'] ?? '',
			'city'           => isset($customer_address['entry_city']) ? $customer_address['entry_city'] : '',
			'postcode'       => isset($customer_address['entry_postcode']) ? $customer_address['entry_postcode'] : '',
			'state'          => (is_array($customer_address) && isset($customer_address['entry_state'])) ? $customer_address['entry_state'] : (isset($customer_address['zone_name']) ? $customer_address['zone_name'] : ''),
			'zone_id'        => isset($customer_address['entry_zone_id']) ? $customer_address['entry_zone_id'] : '',
			'country'        => [
				'id'         => isset($customer_address['countries_id']) ? $customer_address['countries_id'] : '',
				'title'      => isset($customer_address['countries_name']) ? $customer_address['countries_name'] : '',
				'iso_code_2' => isset($customer_address['countries_iso_code_2']) ? $customer_address['countries_iso_code_2'] : '',
				'iso_code_3' => isset($customer_address['countries_iso_code_3']) ? $customer_address['countries_iso_code_3'] : '',
			],
			'format_id'      => isset($customer_address['address_format_id']) ? $customer_address['address_format_id'] : '',
			'telephone'      => isset($customer_address['customers_telephone']) ? $customer_address['customers_telephone'] : '',
			'email_address'  => isset($customer_address['customers_email_address']) ? $customer_address['customers_email_address'] : '',
		];


		$this->delivery = ['firstname'      => isset($shipping_address['entry_firstname']) ? $shipping_address['entry_firstname'] : '',
						   'lastname'       => isset($shipping_address['entry_lastname']) ? $shipping_address['entry_lastname'] : '',
						   'company'        => isset($shipping_address['entry_company']) ? $shipping_address['entry_company'] : '',
						   'nif'            => isset($shipping_address['entry_nif']) ? $shipping_address['entry_nif'] : '',
						   'street_address' => isset($shipping_address['entry_street_address']) ? $shipping_address['entry_street_address'] : '',
						   'telephone'      => $shipping_address['entry_telephone'],
						   'suburb'         => $shipping_address['entry_suburb'],
						   'city'           => isset($shipping_address['entry_city']) ? $shipping_address['entry_city'] : '',
						   'postcode'       => isset($shipping_address['entry_postcode']) ? $shipping_address['entry_postcode'] : '',
						   'state'          => (is_array($shipping_address) && !empty($shipping_address['entry_state'])) ? $shipping_address['entry_state'] : (isset($shipping_address['zone_name']) ? $shipping_address['zone_name'] : ''),
						   'zone_id'        => isset($shipping_address['entry_zone_id']) ? $shipping_address['entry_zone_id'] : '',
						   'country'        => [
							   'id'         => isset($shipping_address['countries_id']) ? $shipping_address['countries_id'] : '',
							   'title'      => isset($shipping_address['countries_name']) ? $shipping_address['countries_name'] : '',
							   'iso_code_2' => isset($shipping_address['countries_iso_code_2']) ? $shipping_address['countries_iso_code_2'] : '',
							   'iso_code_3' => isset($shipping_address['countries_iso_code_3']) ? $shipping_address['countries_iso_code_3'] : '',
						   ],
						   'country_id'     => isset($shipping_address['entry_country_id']) ? $shipping_address['entry_country_id'] : '',
						   'format_id'      => isset($shipping_address['address_format_id']) ? $shipping_address['address_format_id'] : '',
		];


		if (is_array($billing_address)) {
		$this->billing = ['firstname'      => $billing_address['entry_firstname'],
						  'lastname'       => $billing_address['entry_lastname'],
						  'company'        => $billing_address['entry_company'],
						  'nif'            => $billing_address['entry_nif'],
						  'street_address' => $billing_address['entry_street_address'],
						  'suburb'         => $billing_address['entry_suburb'],
						  'city'           => $billing_address['entry_city'],
						  'postcode'       => $billing_address['entry_postcode'],
						  'state'          => ((tep_not_null($billing_address['entry_state'])) ? $billing_address['entry_state'] : $billing_address['zone_name']),
						  'zone_id'        => $billing_address['entry_zone_id'],
						  'country'        => ['id' => $billing_address['countries_id'], 'title' => $billing_address['countries_name'], 'iso_code_2' => $billing_address['countries_iso_code_2'], 'iso_code_3' => $billing_address['countries_iso_code_3']],
						  'country_id'     => $billing_address['entry_country_id'],
						  'format_id'      => $billing_address['address_format_id']];
		}

		// Editor de peddios
		if (isset($_POST['data_all_oe'])) {
			$this->delivery['zone_id']       = $_POST['data_all_oe']['delivery_zone_id'];
			$this->delivery['country_id']    = $_POST['data_all_oe']['delivery_country_id'];
			$this->delivery['country']['id'] = $_POST['data_all_oe']['delivery_country_id'];
		}
		$index    = 0;
		$products = $cart->get_products();
		//kgt - discount coupons
		global $coupon;
		if (tep_session_is_registered('coupon') && tep_not_null($coupon)) {
			require_once DIR_WS_CLASSES . 'discount_coupon.php';
			$this->coupon = new discount_coupon($coupon, $this->delivery);
			$this->coupon->total_valid_products($products);
			$this->coupon->verify_code();
			$valid_products_count = 0;
		}
		//end kgt - discount coupons
		// BOF Separate Pricing Per Customer
		if (!isset($_SESSION['sppc_customer_group_show_tax'])) {
			$customer_group_show_tax = '1';
		} else {
			$customer_group_show_tax = $_SESSION['sppc_customer_group_show_tax'];
		}
		// EOF Separate Pricing Per Customer
		for ($i = 0, $n = sizeof($products); $i < $n; $i++) {
			$sDiscountPromo = '';

			// Mostramos las promociones aplicadas
			if (isset($cart->contents[$products[$i]['id']]) && isset($cart->contents[$products[$i]['id']]['promotion'])) {

				$sPromo = '';
				foreach ($cart->contents[$products[$i]['id']]['promotion'] as $aPromotion) {
					if (isset($aPromotion['qty']) && $aPromotion['qty'] > 0) {
						$sPromo .= $aPromotion['qty'] . ' ud' . ($aPromotion['qty'] == 1 ? '' : 's') . '. al <b>' . $aPromotion['discount'] . '% dto.</b>, ';
					}

				}

				if ($sPromo != '') {
					$sDiscountPromo = '<br><small>(' . substr($sPromo, 0, -2) . ')</small>';
				}

			}

			$this->products[$index] = ['qty'             => $products[$i]['quantity'],
									   'name'            => $products[$i]['name'] . $sDiscountPromo,
									   'model'           => $products[$i]['model'],
									   'ubicacion'       => $products[$i]['ubicacion'] ?? '',
									   'tax'             => tep_get_tax_rate($products[$i]['tax_class_id'], $tax_address['entry_country_id'], $tax_address['entry_zone_id']),
									   'tax_description' => tep_get_tax_description($products[$i]['tax_class_id'], $tax_address['entry_country_id'], $tax_address['entry_zone_id']),
									   'price'           => $products[$i]['price'],
									   'cost'            => $products[$i]['cost'],
									   'final_price'     => $products[$i]['price'] + $cart->attributes_price($products[$i]['id']),
									   'weight'          => $products[$i]['weight'],
									   'id'              => $products[$i]['id'],
									   'image'           => $products[$i]['image']];

			if ($products[$i]['attributes']) {
				$subindex = 0;
				foreach ($products[$i]['attributes'] as $option => $value) {
					$attributes_query = tep_db_query("select popt.products_options_name, popt.products_options_track_stock, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pa.reference from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa where pa.products_id = '" . (int)$products[$i]['id'] . "' and pa.options_id = '" . (int)$option . "' and pa.options_id = popt.products_options_id and pa.options_values_id = '" . (int)$value . "' and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . (int)$languages_id . "' and poval.language_id = '" . (int)$languages_id . "'");
					$attributes       = tep_db_fetch_array($attributes_query);
					// BOF Separate Pricing Per Customer attribute_groups mod
					if (isset($_SESSION['sppc_customer_group_id']) && $_SESSION['sppc_customer_group_id'] != '0') {
						$attributes_group_query = tep_db_query("select pag.options_values_price, pag.price_prefix from " . TABLE_PRODUCTS_ATTRIBUTES . " pa left join " . TABLE_PRODUCTS_ATTRIBUTES_GROUPS . " pag using(products_attributes_id) where pa.products_id = '" . tep_get_prid($products[$i]['id']) . "' and pa.options_id = '" . (int)$option . "' and pa.options_values_id = '" . (int)$value . "' and pag.customers_group_id = '" . (int)$_SESSION['sppc_customer_group_id'] . "'");
						if ($attributes_group = tep_db_fetch_array($attributes_group_query)) {
							$attributes['options_values_price'] = $attributes_group['options_values_price'];
							$attributes['price_prefix']         = $attributes_group['price_prefix'];
						}
					}
					// EOF Separate Pricing Per Customer attribute_groups mod

					$this->products[$index]['attributes'][$subindex] = ['option'      => $attributes['products_options_name'],
																		'value'       => $attributes['products_options_values_name'],
																		'option_id'   => $option,
																		'value_id'    => $value,
																		'prefix'      => $attributes['price_prefix'],
																		'price'       => $attributes['options_values_price'],
																		'reference'   => $attributes['reference'],
																		'track_stock' => $attributes['products_options_track_stock']];
					$subindex++;
				}
			}

			//kgt - discount coupons
			if (is_object($this->coupon)) {
				$applied_discount = 0;
				$discount         = $this->coupon->calculate_discount($this->products[$index], $valid_products_count);
				if ($discount['applied_discount'] > 0) {
					$valid_products_count++;
				}

				$shown_price = $this->coupon->calculate_shown_price($discount, $this->products[$index]);

				/**
				 * Calculamos el beneficio
				 * #XCC-313-91043
				 *
				 * @author Daniel Lucia <daniel.lucia@denox.es>
				 */
				$this->products[$index]['profit']             = Affiliates::calculateProductProfit($shown_price['actual_shown_price'], $this->products[$index]);
				$this->products[$index]['comission']          = Affiliates::calculateProductComission($this, $shown_price['actual_shown_price'], $this->products[$index]);
				$this->products[$index]['actual_shown_price'] = $shown_price['actual_shown_price'];

				$this->info['subtotal'] += $shown_price['shown_price'];
				$shown_price            = $shown_price['actual_shown_price'];
			} else {
				$shown_price = tep_add_tax($this->products[$index]['final_price'], $this->products[$index]['tax']) * $this->products[$index]['qty'];

				/**
				 * Calculamos el beneficio
				 * #XCC-313-91043
				 *
				 * @author Daniel Lucia <daniel.lucia@denox.es>
				 */
				$this->products[$index]['profit']             = Affiliates::calculateProductProfit($shown_price, $this->products[$index]);
				$this->products[$index]['comission']          = Affiliates::calculateProductComission($this, $shown_price, $this->products[$index]);
				$this->products[$index]['actual_shown_price'] = $shown_price;

				$this->info['subtotal'] += $shown_price;
			}

			$this->info['total_affiliate'] += $this->products[$index]['comission'];
			//end kgt - discount coupons

			$products_tax             = $this->products[$index]['tax'];
			$products_tax_description = $this->products[$index]['tax_description'];

			if ((DISPLAY_PRICE_WITH_TAX == 'true') && ($customer_group_show_tax == '1')) { // SPPC, show_tax modification
				$this->info['tax'] += $shown_price - ($shown_price / (($products_tax < 10) ? "1.0" . str_replace('.', '', $products_tax) : "1." . str_replace('.', '', $products_tax)));
				if (isset($this->info['tax_groups']["$products_tax_description"])) {
					$this->info['tax_groups']["$products_tax_description"] += $shown_price - ($shown_price / (($products_tax < 10) ? "1.0" . str_replace('.', '', $products_tax) : "1." . str_replace('.', '', $products_tax)));
				} else {
					$this->info['tax_groups']["$products_tax_description"] = $shown_price - ($shown_price / (($products_tax < 10) ? "1.0" . str_replace('.', '', $products_tax) : "1." . str_replace('.', '', $products_tax)));
				}
			} else {
				$this->info['tax'] += ($products_tax / 100) * $shown_price;
				if (isset($this->info['tax_groups']["$products_tax_description"])) {
					$this->info['tax_groups']["$products_tax_description"] += ($products_tax / 100) * $shown_price;
				} else {
					$this->info['tax_groups']["$products_tax_description"] = ($products_tax / 100) * $shown_price;
				}
			}

			$index++;
		}

		if ((DISPLAY_PRICE_WITH_TAX == 'true') && ($customer_group_show_tax == '1')) {
			$this->info['total'] = $this->info['subtotal'] + $this->info['shipping_cost'];
		} else {
			$this->info['total'] = $this->info['subtotal'] + $this->info['tax'] + $this->info['shipping_cost'];
		}

		if (is_object($this->coupon)) {
			$this->info['total'] = $this->coupon->finalize_discount($this->info);
		}
	}
}
