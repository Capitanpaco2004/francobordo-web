<?php

use util\event;

class order {
	private static $columnExistsChecked = false;
	private static $hasPaypalTransactionIdColumn = false;
	public $info = [];
	public $totals = [];
	public $products = [];
	public $customer = [];
	public $delivery = [];
	public $billing = [];
	public $content_type;
	public $coupon;
	public $invoices = [];

	public function __construct($order_id = '') {
		$this->checkPaypalTransactionColumn();
		$this->query($order_id);
	}

	/**
	 * Verifica si la columna paypal_transaction_id existe en la tabla y almacena el resultado.
	 */
	public function checkPaypalTransactionColumn() {
		if (!self::$columnExistsChecked) {
			$check_column_query                 = tep_db_query("SHOW COLUMNS FROM " . TABLE_ORDERS . " LIKE 'paypal_transaction_id'");
			self::$hasPaypalTransactionIdColumn = tep_db_num_rows($check_column_query) > 0;
			self::$columnExistsChecked          = true;
		}
	}

	function query($order_id) {
		// Seleccionar columnas básicas
		$select_columns = [
			"o.customers_id", "o.amazon_id", "o.ebay_id", "o.id_store",
			"customers_name", "customer_service_id", "customers_company",
			"customers_street_address", "customers_suburb", "customers_city",
			"customers_postcode", "customers_state", "customers_country",
			"o.customers_telephone", "o.customers_email_address",
			"customers_address_format_id", "customers_group_id", "customers_group_name",
			"o.customers_language_id",
			"delivery_name", "delivery_company", "delivery_street_address",
			"delivery_telephone", "delivery_suburb", "delivery_city",
			"delivery_postcode", "delivery_state", "delivery_country",
			"delivery_address_format_id",
			"billing_name", "billing_company", "billing_nif", "billing_street_address",
			"billing_suburb", "billing_city", "billing_postcode", "billing_state",
			"billing_country", "billing_address_format_id",
			"payment_method", "payment_module", "shipping_module",
			"cc_type", "cc_owner", "cc_number", "cc_expires",
			"currency", "currency_value", "date_purchased", "orders_status", "last_modified",
		];

		// Agregar paypal_transaction_id si la columna existe
		if (self::$hasPaypalTransactionIdColumn) {
			$select_columns[] = "paypal_transaction_id";
		}

		$columns_str = implode(", ", $select_columns);
		$order_query = tep_db_query("SELECT $columns_str FROM " . TABLE_ORDERS . " o
                                     LEFT JOIN " . TABLE_CUSTOMERS . " USING(customers_id)
                                     LEFT JOIN " . TABLE_CUSTOMERS_GROUPS . " USING(customers_group_id)
                                     WHERE orders_id = '" . (int)$order_id . "'");
		$order       = tep_db_fetch_array($order_query);

		$totals_query = tep_db_query("select title, text, value, class, sort_order from " . TABLE_ORDERS_TOTAL . " where orders_id = '" . (int)$order_id . "' order by sort_order");
		while ($totals = tep_db_fetch_array($totals_query)) {
			$this->totals[] = ['title'      => $totals['title'],
							   'text'       => $totals['text'],
							   'value'      => $totals['value'],
							   'class'      => $totals['class'],
							   'sort_order' => $totals['sort_order']];
		}

		$this->info = [
			'order_id'        => $order_id,
			'amazon_id'       => $order['amazon_id'],
			'ebay_id'         => $order['ebay_id'],
			'currency'        => $order['currency'],
			'currency_value'  => $order['currency_value'],
			'payment_method'  => $order['payment_method'],
			'cc_type'         => $order['cc_type'],
			'cc_owner'        => $order['cc_owner'],
			'cc_number'       => $order['cc_number'],
			'cc_expires'      => $order['cc_expires'],
			'date_purchased'  => $order['date_purchased'],
			'orders_status'   => $order['orders_status'],
			'admin'           => $order['customer_service_id'],
			'shipping_module' => $order['shipping_module'],
			'id_store'        => $order['id_store'],
			'last_modified'   => $order['last_modified']];

		if (self::$hasPaypalTransactionIdColumn && !empty($order['paypal_transaction_id'])) {
			$this->info['paypal_transaction_id'] = $order['paypal_transaction_id'];
		}
		$this->customer = ['id'                   => $order['customers_id'],
						   'name'                 => $order['customers_name'],
						   'company'              => $order['customers_company'],
						   'street_address'       => $order['customers_street_address'],
						   'suburb'               => $order['customers_suburb'],
						   'city'                 => $order['customers_city'],
						   'postcode'             => $order['customers_postcode'],
						   'state'                => $order['customers_state'],
						   'country'              => $order['customers_country'],
						   'format_id'            => $order['customers_address_format_id'],
						   'customers_group_name' => $order['customers_group_name'],
						   'telephone'            => $order['customers_telephone'],
						   'email_address'        => $order['customers_email_address']];

		$this->delivery = ['name'           => $order['delivery_name'],
						   'company'        => $order['delivery_company'],
						   'street_address' => $order['delivery_street_address'],
						   'telephone'      => $order['delivery_telephone'],
						   'suburb'         => $order['delivery_suburb'],
						   'city'           => $order['delivery_city'],
						   'postcode'       => $order['delivery_postcode'],
						   'state'          => $order['delivery_state'],
						   'country'        => $order['delivery_country'],
						   'format_id'      => $order['delivery_address_format_id']];

		$this->billing = ['name'           => $order['billing_name'],
						  'company'        => $order['billing_company'],
						  'street_address' => $order['billing_street_address'],
						  'suburb'         => $order['billing_suburb'],
						  'city'           => $order['billing_city'],
						  'postcode'       => $order['billing_postcode'],
						  'state'          => $order['billing_state'],
						  'country'        => $order['billing_country'],
						  'nif'            => $order['billing_nif'],
						  'format_id'      => $order['billing_address_format_id']];

		$index                 = 0;
		$orders_products_query = tep_db_query("select products_id, orders_products_id, orders_products_id, products_name, products_model, products_ubicacion, products_price, products_tax, products_quantity, final_price, products_returned, products_id, products_reference, qfac_sync_note, qfac_sync_at from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . (int)$order_id . "'");
		while ($orders_products = tep_db_fetch_array($orders_products_query)) {
			$this->products[$index] = ['id'                 => $orders_products['orders_products_id'],
									   'products_id'        => $orders_products['products_id'],
									   'qty'                => $orders_products['products_quantity'],
									   'name'               => $orders_products['products_name'],
									   'return'             => $orders_products['products_returned'],
									   'model'              => $orders_products['products_model'],
									   'ubicacion'          => $orders_products['products_ubicacion'],
									   'tax'                => $orders_products['products_tax'],
									   'price'              => $orders_products['products_price'],
									   'products_reference' => $orders_products['products_reference'],
									   'final_price'        => $orders_products['final_price'],
									   'qfac_sync_note'     => $orders_products['qfac_sync_note'],
									   'qfac_sync_at'       => $orders_products['qfac_sync_at']];

			$subindex         = 0;
			$attributes_query = tep_db_query("select products_options, products_options_values, options_values_price, price_prefix from " . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . " where orders_id = '" . (int)$order_id . "' and orders_products_id = '" . (int)$orders_products['orders_products_id'] . "'");
			if (tep_db_num_rows($attributes_query)) {
				while ($attributes = tep_db_fetch_array($attributes_query)) {
					$this->products[$index]['attributes'][$subindex] = ['option' => $attributes['products_options'],
																		'value'  => $attributes['products_options_values'],
																		'prefix' => $attributes['price_prefix'],
																		'price'  => $attributes['options_values_price']];

					$subindex++;
				}
			}
			$index++;
		}

		event::getInstance()->execute('back_office_includes_classes_orders_after', [&$this]);
	}
}

