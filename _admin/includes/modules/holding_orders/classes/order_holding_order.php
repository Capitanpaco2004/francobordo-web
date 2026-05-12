<?php

class order_holding_order {
	public $info = [];
	public $totals = [];
	public $products = [];
	public $customer = [];
	public $delivery = [];
	public $billing;
	public $content_type;
	public $coupon;
	public $redsys;

	public function __construct($order_id = '') {
		$this->query($order_id);
	}

	function query($order_id) {
		$order_query = tep_db_query("select orders_id, customers_name, customers_company, customers_street_address, customers_city, customers_postcode, customers_state, customers_country, customers_telephone, customers_email_address, customers_address_format_id, delivery_name, delivery_company, delivery_street_address, delivery_city, delivery_postcode, delivery_state, delivery_country, delivery_address_format_id, billing_name, billing_company, billing_street_address, billing_city, billing_postcode, billing_state, billing_country, billing_address_format_id, billing_nif, payment_method, currency, currency_value, date_purchased, orders_status, last_modified from " . TABLE_HOLDING_ORDERS . " where orders_id = '" . tep_db_input($order_id) . "'");
		$order       = tep_db_fetch_array($order_query);

		$totals_query = tep_db_query("select title, text from " . TABLE_HOLDING_ORDERS_TOTAL . " where orders_id = '" . tep_db_input($order_id) . "' order by sort_order");

		while ($totals = tep_db_fetch_array($totals_query)) {
			$this->totals[] = [
				'title' => $totals['title'],
				'text'  => $totals['text'],
			];
		}

		$this->info = [
			'orders_id'      => $order['orders_id'],
			'currency'       => $order['currency'],
			'currency_value' => $order['currency_value'],
			'payment_method' => $order['payment_method'],
			'date_purchased' => $order['date_purchased'],
			'orders_status'  => $order['orders_status'],
			'last_modified'  => $order['last_modified']];

		$this->customer = [
			'name'           => $order['customers_name'],
			'company'        => $order['customers_company'],
			'nif'            => $order['billing_nif'],
			'street_address' => $order['customers_street_address'],
			'city'           => $order['customers_city'],
			'postcode'       => $order['customers_postcode'],
			'state'          => $order['customers_state'],
			'country'        => $order['customers_country'],
			'format_id'      => $order['customers_address_format_id'],
			'telephone'      => $order['customers_telephone'],
			'email_address'  => $order['customers_email_address']];

		$this->delivery = [
			'name'           => $order['delivery_name'],
			'company'        => $order['delivery_company'],
			'street_address' => $order['delivery_street_address'],
			'city'           => $order['delivery_city'],
			'postcode'       => $order['delivery_postcode'],
			'state'          => $order['delivery_state'],
			'country'        => $order['delivery_country'],
			'format_id'      => $order['delivery_address_format_id'],
			'nif'            => $order['billing_nif']];

		$this->billing = [
			'name'           => $order['billing_name'],
			'company'        => $order['billing_company'],
			'nif'            => $order['billing_nif'],
			'street_address' => $order['billing_street_address'],
			'city'           => $order['billing_city'],
			'postcode'       => $order['billing_postcode'],
			'state'          => $order['billing_state'],
			'country'        => $order['billing_country'],
			'format_id'      => $order['billing_address_format_id']];

		$index                 = 0;
		$orders_products_query = tep_db_query("select orders_products_id, products_name, products_model, products_ubicacion, products_price, final_price, products_tax, products_quantity from " . TABLE_HOLDING_ORDERS_PRODUCTS . " where orders_id = '" . tep_db_input($order_id) . "'");

		while ($orders_products = tep_db_fetch_array($orders_products_query)) {
			$this->products[$index] = [
				'qty'         => $orders_products['products_quantity'],
				'name'        => $orders_products['products_name'],
				'model'       => $orders_products['products_model'],
				'ubicacion'   => $orders_products['products_ubicacion'],
				'tax'         => $orders_products['products_tax'],
				'price'       => $orders_products['products_price'],
				'final_price' => $orders_products['final_price'],
			];

			$subindex         = 0;
			$attributes_query = tep_db_query("select products_options, products_options_values from " . TABLE_HOLDING_ORDERS_PRODUCTS_ATTRIBUTES . " where orders_id = '" . tep_db_input($order_id) . "' and orders_products_id = '" . $orders_products['orders_products_id'] . "'");

			if (tep_db_num_rows($attributes_query)) {
				while ($attributes = tep_db_fetch_array($attributes_query)) {
					$this->products[$index]['attributes'][$subindex] = [
						'option' => $attributes['products_options'],
						'value'  => $attributes['products_options_values']];

					$subindex++;
				}
			}
			$index++;
		}

		// Información Redsys
		$redsys_query = tep_db_query(" SELECT ds_order, ds_response, ds_response_msg, ds_state, ds_state_msg, ds_processed_at FROM holding_orders_redsys WHERE orders_id = '" . tep_db_input($order_id) . "' ");

		if (tep_db_num_rows($redsys_query)) {
			$redsys_info = tep_db_fetch_array($redsys_query);

			$this->redsys = [
				'ds_order'        => $redsys_info['ds_order'],
				'ds_response'     => $redsys_info['ds_response'],
				'ds_response_msg' => $redsys_info['ds_response_msg'],
				'ds_state'        => $redsys_info['ds_state'],
				'ds_state_msg'    => $redsys_info['ds_state_msg'],
				'ds_processed_at' => ($redsys_info['ds_processed_at'] && $redsys_info['ds_processed_at'] !== '0000-00-00 00:00:00') ? date('d/m/Y H:i:s', strtotime($redsys_info['ds_processed_at'])) : '-',
			];
		} else {
			$this->redsys = null;
		}

	}
}
