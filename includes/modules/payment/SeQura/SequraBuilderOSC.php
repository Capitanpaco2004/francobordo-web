<?php

class SequraBuilderOSC extends SequraBuilderAbstract {
	protected $_shipped_ids = array();
	protected $_current_order_id = null;
	static $empty_cart = array(
                'items'=>array(),
                'order_total_without_tax'=>0,
                'order_total_with_tax'=>0,
                'currency' => 'EUR'
            );

	public function __construct($merchant_id) {
		global $order;
		$this->_current_order = $order;
		parent::__construct($merchant_id);
	}

	public function build($state = null) {
		$data = parent::build($state);
		if (ISUTF8) {
			return $data;
		}

		return $this->fixCodification($data);
	}

	public function buildDeliveryReport() {
		parent::buildDeliveryReport();
		if (!ISUTF8) {
			$this->_delivery_report = $this->fixCodification($this->_delivery_report);
		}
	}

	function fixCodification($array) {

		foreach ($array as $key => $val) {
			if (is_string($val)) {
				$array[$key] = mb_convert_encoding($val ?? '', 'UTF-8', 'ISO-8859-1');
			} else if (is_array($val)) {
				$array[$key] = $this->fixCodification($array[$key]);
			}
		}
		return $array;
	}

	public function merchant() {
		$ret = array();
		$sid = tep_session_id();
		$ret['id'] = $this->merchant_id;
		$ret['notify_url'] = tep_href_link('ipn-sequra.php', null, 'SSL', true, false);
		$ret['notification_parameters'] = array(
			'sid' => $sid,
			'signature'  => SequraHelper::sign($sid)
		);
		$ret['return_url'] = tep_href_link('return-sequra.php','product=SQ_PRODUCT_CODE', 'SSL', true, false);
		$ret['edit_url'] = tep_href_link(FILENAME_CHECKOUT_SHIPPING_ADDRESS, null, 'SSL', true, false);
		$ret['abort_url'] = tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=Ha%20habido%20un%20error', 'SSL', true, false);
		$ret['options']['accept_terms_explicitly'] = false;
		$ret['options']['has_jquery'] = false;
		return $ret;
	}

	public function cartWithItems() {
		$data = array();
		$data['currency'] = $this->_current_order->info['currency'];
		$data['delivery_method'] = $this->deliveryMethod();
		$data['cart_ref'] = self::notNull($_SESSION['cartID']);
		if (!isset($_SESSION['cartCreatedAt'])) {
			$_SESSION['cartCreatedAt'] = date('c');
		}
		$data['created_at'] = $_SESSION['cartCreatedAt'];
		$data['updated_at'] = date('c');
		$data['gift'] = false;

		if (DISPLAY_PRICE_WITH_TAX == 'true') {
			$data['order_total_with_tax'] = self::integerPrice($this->_current_order->info['total']);
			$data['order_total_without_tax'] = self::integerPrice($this->_current_order->info['total'] - $this->_current_order->info['tax']);
		} else {
			$data['order_total_with_tax'] = self::integerPrice($this->_current_order->info['total'] + $this->_current_order->info['tax']);
			$data['order_total_without_tax'] = self::integerPrice($this->_current_order->info['total']);
		}
		$data['order_total_without_tax'] = $data['order_total_with_tax'];

		$data['items'] = $this->items();
		return $data;
	}

	public function productItem() {
		global $languages_id;
		$items = array();
		foreach ($this->_current_order->products as $itemOb) {
			$item = array();
			$item["reference"] = $itemOb['model']?$itemOb['model']:intval($itemOb['id']);
			$item["name"] = self::notNull($itemOb['name']);
			$item["downloadable"] = false; //@TODO: guess if its virtual product
			$item["quantity"] = (int)$itemOb['qty'];
			$item["tax_rate"] = self::integerPrice($itemOb['tax']);
			$item["price_without_tax"] = self::integerPrice($itemOb['price']);
			$item["price_with_tax"] = self::integerPrice($itemOb['price'] * (1 + $itemOb['tax'] / 100));
			$item["total_without_tax"] = self::integerPrice($itemOb['final_price'] * $item["quantity"]);
			$item["total_with_tax"] = self::integerPrice($itemOb['final_price'] * $item["quantity"] * (1 + $itemOb['tax'] / 100));
			//$item["total_without_tax"] = $item["price_without_tax"]*$item["quantity"];
			//$item["total_with_tax"] = $item["price_with_tax"]*$item["quantity"];

			$item["tax_rate"] = 0;
			$item["price_without_tax"] = $item["price_with_tax"];
			$item["total_without_tax"] = $item["total_with_tax"];

			// OPTIONAL
			$sql = "select products_name,products_description, products_url,manufacturers_name from " .
				TABLE_PRODUCTS . " p left join " .
				TABLE_PRODUCTS_DESCRIPTION . " pd on p.products_id=pd.products_id left join " .
				TABLE_MANUFACTURERS . " m on p.manufacturers_id=m.manufacturers_id " .
				" where p.products_id = '" . (int)$itemOb['id'] . "' and language_id = '" . (int)$languages_id . "'";
			$product_query = tep_db_query($sql);
			$product = tep_db_fetch_array($product_query);
			$item["description"] = self::notNull($product['products_description']);
			$item["product_id"] = self::notNull($itemOb['id']);
			$item["url"] = self::notNull($product['products_url']);
			$item["manufacturer"] = self::notNull($product['manufacturers_name']);

			$cat_query = tep_db_query("select cd.categories_name from " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c left join " .
				TABLE_CATEGORIES_DESCRIPTION . " cd on p2c.categories_id=cd.categories_id " .
				" where p2c.products_id = '" . (int)$itemOb['id'] . "' and language_id = '" . (int)$languages_id . "'");
			$cats = array();
			while ($cat = tep_db_fetch_array($cat_query)) {
				$cats[] = $cat['categories_name'];
			};
			$item["category"] = self::notNull(implode(',', $cats));
			$items[] = $item;
		}
		return $items;
	}

	/*** @return array
	 *
	 * Not coupon nor fees are present in default OsCommerce
	 * Probably this method will need to be overidden
	 */
	public function extraItems() {
		$ret = array();
		if(!file_exists(DIR_WS_MODULES.'/order_total/ot_sequra_fee.php')){
			return $ret;
		}

		include_once(DIR_WS_MODULES.'/order_total/ot_sequra_fee.php');
		$ot = new ot_sequra_fee();
		$fee = null;

		if(count($this->_shipped_ids)>0){
			$sql = 'select title,value from '.TABLE_ORDERS_TOTAL.' where class = "ot_sequra_fee" and orders_id='.$this->_current_order_id;
			$query = tep_db_query($sql);
			$fee = tep_db_fetch_array($query);
		}
		if($fee && $fee['value']>0){
			$tax = tep_get_tax_rate($ot->tax_class, $this->_current_order->delivery['country']['id'], $this->_current_order->delivery['zone_id']);
			$ret[] = array(
				'type'              => 'invoice_fee',
				'total_with_tax'    => self::integerPrice($fee['value']),
				'tax_rate'          => 0,//$this->taxRate(),
				'total_without_tax' => self::integerPrice($fee['value']),//$this->withoutTax(),
				'reference'         => trim($fee['title'],':')
			);
		}
		return $ret;
	}

	public function handlingItems() {
		$module = substr($GLOBALS['shipping']['id'], 0, strpos($GLOBALS['shipping']['id'], '_'));
		$shipping_tax = tep_get_tax_rate($GLOBALS[$module]->tax_class, $this->_current_order->delivery['country']['id'], $this->_current_order->delivery['zone_id']);

		if (0 == $this->_current_order->info['shipping_cost']) {
			return array();
		}

		$handling_ref = is_array($this->_current_order->info['shipping_method'])?
			$this->_current_order->info['shipping_method'][0]:
			$this->_current_order->info['shipping_method'];
		$handling = array(
			'type' => 'handling',
			'reference' => self::notNull($handling_ref),
			'name' => 'Gastos de envío',
			'tax_rate' => self::integerPrice($shipping_tax)
		);
		if (DISPLAY_PRICE_WITH_TAX == 'true') {
			$handling['total_without_tax'] = self::integerPrice($this->_current_order->info['shipping_cost'] / (1 + $shipping_tax / 100));
			$handling['total_with_tax'] = self::integerPrice($this->_current_order->info['shipping_cost']);
		} else {
			$handling['total_without_tax'] = self::integerPrice($this->_current_order->info['shipping_cost']);
			$handling['total_with_tax'] = self::integerPrice($this->_current_order->info['shipping_cost'] * (1 + $shipping_tax / 100));
		}

		$handling['tax_rate'] = 0;
		$handling['total_without_tax'] = $handling['total_with_tax'];

		$items[] = $handling;
		return $items;
	}

	public function deliveryAddress() {
		$data = array();
		$data['given_names'] = self::notNull($this->_current_order->delivery['firstname']);
		$data['surnames'] = self::notNull($this->_current_order->delivery['lastname']);
		$data['company'] = self::notNull($this->_current_order->delivery['company']);
		$data['address_line_1'] = self::notNull($this->_current_order->delivery['street_address']);
		$data['address_line_2'] = self::notNull($this->_current_order->delivery['suburb']);
		$data['postal_code'] = self::notNull($this->_current_order->delivery['postcode']);
		$data['city'] = self::notNull($this->_current_order->delivery['city']);
		$data['country_code'] = $this->getCountryIsoCode2($this->_current_order->delivery['country']);
		// OPTIONAL
		$data['state'] = self::notNull($this->_current_order->delivery['state']);
		$data['mobile_phone'] = self::notNull($this->_current_order->customer['telephone']);
		/*TODO: Search vat/nif common plugins*/
		$data['vat_number'] = self::notNull($this->_current_order->delivery['nif']);
		if ('' == $data['vat_number']) $data['vat_number'] = self::notNull($this->_current_order->delivery['vat']);
		return $data;
	}

	public function invoiceAddress() {
		$data = array();
		$data['given_names'] = self::notNull($this->_current_order->billing['firstname']);
		$data['surnames'] = self::notNull($this->_current_order->billing['lastname']);
		$data['company'] = self::notNull($this->_current_order->billing['company']);
		$data['address_line_1'] = self::notNull($this->_current_order->billing['street_address']);
		$data['address_line_2'] = self::notNull($this->_current_order->billing['suburb']);
		$data['postal_code'] = self::notNull($this->_current_order->billing['postcode']);
		$data['city'] = self::notNull($this->_current_order->billing['city']);
		$data['country_code'] = $this->getCountryIsoCode2($this->_current_order->billing['country']);
		// OPTIONAL
		$data['state'] = self::notNull($this->_current_order->billing['state']);
		$data['mobile_phone'] = self::notNull($this->_current_order->customer['telephone']);
		/*TODO: Search vat/nif common plugins*/
		$data['vat_number'] = self::notNull($this->_current_order->billing['nif']);
		if ('' == $data['vat_number']) $data['vat_number'] = self::notNull($this->_current_order->billing['vat']);
		return $data;
	}

	function getCountryIsoCode2($country) {
		if (isset($country['iso_code_2'])) {
			return $country['iso_code_2'];
		}
		$sql = "select countries_iso_code_2 from " . TABLE_COUNTRIES . " where countries_name = '" . $country['title'] . "'";
		$countries = tep_db_query($sql);
		$countries_values = tep_db_fetch_array($countries);
		return self::notNull($countries_values['countries_iso_code_2']);
	}

	public function getQuote() {
		return $this->_quote;
	}

	public function customer() {
		$data = array();
		$data['given_names'] = self::notNull($this->_current_order->customer['firstname']);
		$data['surnames'] = self::notNull($this->_current_order->customer['lastname']);
		$data['email'] = self::notNull($this->_current_order->customer['email_address']);
		$data['language_code'] = self::notNull($GLOBALS['language']);
		if(!$this->_building_report){
			if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
				$data['ip_number'] = $_SERVER['HTTP_CLIENT_IP'];
			} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				$data['ip_number'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} else {
				$data['ip_number'] = $_SERVER['REMOTE_ADDR'];
			}
			$data['user_agent'] = $_SERVER["HTTP_USER_AGENT"];
			$data['logged_in'] = isset($_SESSION['customer_id']);
		}
		$id = $data['logged_in'] ? $_SESSION['customer_id'] : -1;
		// OPTIONAL
    /*TODO: Search vat/nif common plugins*/
    $data['vat_number'] = self::notNull($this->_current_order->billing['nif']);
    if ('' == $data['vat_number']) $data['vat_number'] = self::notNull($this->_current_order->billing['vat']);
    if ('' != $data['vat_number']) $data['nin'] = $data['vat_number'];

		if ($data['logged_in']) {
		  $data['ref'] = self::notNull($id);
			$sql = "select customers_dob from " . TABLE_CUSTOMERS . " where customers_id = " . (int)$id;
			$query = tep_db_query($sql);
			$row = tep_db_fetch_array($query);
			if ($row['customers_dob'] != '0000-00-00 00:00:00') {
				$data['date_of_birth'] = self::dateOrBlank($row['customers_dob']);
			}
			$data['previous_orders'] = self::getPreviousOrders($id);
		}
		return $data;
	}

	public function getPreviousOrders($customerID) {
		$orders = array();
		$sql = 'select orders_id,date_purchased FROM ' . TABLE_ORDERS . ' where customers_id=' . (int)$customerID;
		$query = tep_db_query($sql);
		while ($row = tep_db_fetch_array($query)) {
			$orderObj = new Order($row['orders_id']);
			$order = array();
			$order['amount'] = self::integerPrice($orderObj->info['total']);
			$order['currency'] = self::notNull($orderObj->info['currency']);
			$order['created_at'] = self::dateOrBlank($row['date_purchased']);
			$orders[] = $order;
		}
		return $orders;
	}

	public function platform() {
		$sql = "show variables like 'version';";
		$query = tep_db_query($sql);
		$version = tep_db_fetch_array($query);

		$data = array(
			'name' => 'OsCommerce',
			'version' => PROJECT_VERSION,
			'plugin_version' => sequra::VERSION,
			'php_version' => phpversion(),
			'php_os' => PHP_OS,
			'uname' => php_uname(),
			'db_name' => 'mysql',
			'db_version' => $version['Value']
		);
		return $data;
	}

	public function deliveryMethod() {
		$method = explode('_', $this->_current_order->info['shipping_method']);
		return array(
			'name' => self::notNull($method[0]),
			'provider' => self::notNull($method[1]),
		);
	}

	public function buildShippedOrders() {
		$this->getShippedOrderList();
		foreach ($this->_shipped_ids as $order_id => $original_order) {
			$data = array();
			$this->_current_order_id = $order_id;
			$this->_current_order = new Order($order_id);
			//Some information might be lost in stored orders, lets merge it with the order information we had in the checkout
			$this->_current_order->info = array_merge($original_order->info, $this->_current_order->info);
			$this->_current_order->delivery = array_merge($original_order->delivery, $this->_current_order->delivery);
			$this->_current_order->billing = array_merge($original_order->billing, $this->_current_order->billing);
			$this->_current_order->customer = array_merge($original_order->customer, $this->_current_order->customer);
			for($i = 0; $i++ ; $i<count($this->_current_order->products)){
				for($j=0;$j++;$j<count($original_order->products)){
					if($this->_current_order->products[$i]['model']==$original_order->products[$j]['model']){
						$this->_current_order->products[$i] = array_merge($original_order->products[$j]['model'],$this->_current_order->products[$i]);
						break;
					}
				}
			}

			$sql = "select date_added from " . TABLE_ORDERS_STATUS_HISTORY .
				" where orders_id = ". (int)$order_id .
				" and orders_status_id = " . (int)MODULE_PAYMENT_SEQURA_SHIPPED_STATUS_ID;
			$result = tep_db_query($sql);
			if($row = tep_db_fetch_array($result)){
				$data['sent_at'] = self::dateOrBlank(substr($row['date_added'],0,10));
			}
			else{
				$data['sent_at'] = '';
			}
			$data['state'] = 'delivered';
			$data['delivery_address'] = $this->deliveryAddress();
			$data['invoice_address'] = $this->invoiceAddress();
			$data['customer'] = $this->customer();
			$data['cart'] = $this->shipmentCart();
			$data['remaining_cart'] = self::$empty_cart;
			$data['merchant_reference'] = $this->orderMerchantReference();
			$this->_orders[] = $data;
		}
	}

	public function getOrderRef($num) {
		if (1 == $num && isset($GLOBALS['insert_id']))
			return $GLOBALS['insert_id'];

		if (1 == $num && $this->_current_order_id)
			return $this->_current_order_id;

	}

	public function shipmentCart() {
		$data = array();
		$data['currency'] = $this->_current_order->info['currency'];
		$data['delivery_method'] = $this->deliveryMethod();
		$data['gift'] = false;
		$data['items'] = $this->items();

		if (count($data['items']) > 0) {
			$totals = self::totals($data);
			$data['order_total_without_tax'] = $totals['without_tax'];
			$data['order_total_with_tax'] = $totals['with_tax'];
		}
		return $data;
	}

	public function getShippedOrderIds() {
		return $this->_shipped_ids;
	}

	public function getShippedOrderList() {
		$sql = "select distinct s.orders_id from " . TABLE_ORDERS_STATUS_HISTORY . " h inner join sequra s on ( sent_to_sequra = 0 and h.orders_id=s.orders_id)
		where orders_status_id = " . (int)MODULE_PAYMENT_SEQURA_SHIPPED_STATUS_ID ;

		$result = tep_db_query($sql);
		while ($row = tep_db_fetch_array($result)) {
			$sql_order = "select serialized_order from sequra where orders_id = " . (int)$row['orders_id']. ";";
			$result_order = tep_db_query($sql_order);
			$row_order = tep_db_fetch_array($result_order);
			$this->_shipped_ids[$row['orders_id']] = unserialize(urldecode($row_order['serialized_order']));
		}
	}

	/*
	 * Set orders as shipped_to_sequra and add the event to status_history
	 */
	public function setOrdersAsShipped() {
		if (0 == count($this->_shipped_ids))
			return;
		$sql = "select orders_id, orders_status from " . TABLE_ORDERS . " where orders_id in (" . implode(',', array_keys($this->_shipped_ids)) . ")";
		$query = tep_db_query($sql);
		while ($row = tep_db_fetch_array($query)) {
			$sql = 'select * from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id=' . (int)$row['orders_id'] . ' order by date_added desc limit 1';
			$query = tep_db_query($sql);
			$previous_status = tep_db_fetch_array($query);
			tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, array(
				'orders_id' => $row['orders_id'],
				'orders_status_id' => sequra::INFORMED_STATUS,
				'date_added' => 'now()',
				'comments' => 'Se ha informado del env&iacute;o a SeQura',
				'customer_notified' => 0
			));
			$previous_status['date_added'] = 'now()';
			unset($previous_status['orders_status_history_id']);
			tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $previous_status);
		}
		$sql = "update sequra set sent_to_sequra=1 where orders_id in (" . implode(',', array_keys($this->_shipped_ids)) . ")";
		tep_db_query($sql);
	}

	public function getOrderStats() {
		$stats = array();
		$sql = "select orders_id from " . TABLE_ORDERS . "
		where DATE_SUB(CURDATE(),INTERVAL 7 DAY) <= date_purchased;";
		$this->payment_modules = new payment;
		$result = tep_db_query($sql);
		while ($row = tep_db_fetch_array($result)) {
			$this->_current_order_id = $row['orders_id'];
			$this->_current_order = new Order($row['orders_id']);
			$date = strtotime($this->_current_order->info['date_purchased']);
			$stat = array(
				'completed_at' => self::dateOrBlank(date('c', $date)),
				'merchant_reference' => $this->orderMerchantReference(),
				'currency' => $this->_current_order->info['currency']
			);

			if (true || get_option('sequra_allowstats_amount')) // TODO: Stats config
			{
				$total = preg_replace("/[^0-9]/", "", $this->_current_order->info['total']);
				$stat['amount'] = intval($total);
			}
			if (true || get_option('sequra_allowstats_country')) // TODO: Stats config
			{
				$stat['country'] = $this->getCountryIsoCode2($this->_current_order->delivery['country']);
			}
			if (true || get_option('sequra_allowstats_payment')) { // TODO: Stats config
				$stat['payment_method_raw'] = self::notNull($this->_current_order->info['payment_method']);
				$stat['payment_method'] = $this->mapPaymentMethod($stat['payment_method_raw']);
			}
			if (true || get_option('sequra_allowstats_status')) { // TODO: Stats config
				$stat['raw_status'] = self::notNull($this->_current_order->info['orders_status']);
				$stat['status'] = self::mapStatus($stat['raw_status']);
			}

			$stats[] = $stat;
		}
		return $stats;
	}

	public function mapPaymentMethod($payment_method_raw) {
		global $order;
		$code = strtolower($payment_method_raw);
		if (strpos($code, 'paypal') !== false) {
			return 'PP';
		}
		if (strpos($code, 'tarjeta') !== false) {
			return 'CC';
		}
		if (strpos($code, 'redsys') !== false) {
			return 'CC';
		}
		if (strpos($code, 'bbva') !== false) {
			return 'CC';
		}
		if (strpos($code, 'sequra') !== false) {
			return 'PP';
		}
		if (strpos($code, 'recibe primero') !== false) {
			return 'SQ';
		}
		if (strpos($code, 'fracciona') !== false) {
			return 'SQ';
		}
		if (strpos($code, 'paga en 7') !== false) {
			return 'SQ';
		}
		if (strpos($code, 'transferencia') !== false) {
			return 'TR';
		}
		if (strpos($code, 'reembolso') !== false) {
			return 'PP';
		}
		return 'O/' . $payment_method_raw;
	}

	static function mapStatus($raw_status) {
		switch (strtolower($raw_status)) {
			case 'delivered':
			case 'enviado':
				return 'shipped';
			case 'cancelled':
			case 'cancelado':
			case 'refunded':
			case 'sequra: rechazado':
				return 'cancelled';
			default:
				return 'processing';
		}
	}
}
