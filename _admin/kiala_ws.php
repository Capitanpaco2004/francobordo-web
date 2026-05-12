<?php
ini_set('soap.wsdl_cache_enabled', 0);
	
require('includes/application_top.php');
include(DIR_WS_CLASSES . 'order.php');

global $languages_id;

$debug_mode = false;

//get the order object
$order_id = (isset($_GET["oID"])) ? $_GET["oID"] : false;

if($order_id){
	$order = new order((int) $order_id);

	//format the order date
	$date = date("Y-m-d",strtotime($order->info['date_purchased']));

	//get the customer firstname and surname seprately
	$customers_query = tep_db_query("select T2.customers_firstname,T2.customers_lastname from " . TABLE_ORDERS . " T1 , " . TABLE_CUSTOMERS . " T2 where T1.customers_id = T2.customers_id and T1.orders_id = '" . $_GET["oID"] . "'");
	$customer = tep_db_fetch_array($customers_query);
	$customer_firstname = $customer['customers_firstname'];
	$customer_lastname = $customer['customers_lastname'];

	//get the weight
	$products_query = tep_db_query("select T2.products_weight from " . TABLE_ORDERS_PRODUCTS . " T1 , " . TABLE_PRODUCTS . " T2 where T1.products_id = T2.products_id and T1.orders_id = '" . $_GET["oID"] . "'");
	$products_weight = tep_db_fetch_array($products_query);

	//get the site language
	$languages_query = tep_db_query("select languages_id, code from " . TABLE_LANGUAGES . " order by sort_order");
	while ($languages = tep_db_fetch_array($languages_query)) {
		if ($languages['languages_id'] == $languages_id) {
			$lang = $languages['code'];
		} else {
			$lang = '';
		}
	}

	//get the delivery country prefix
	$country_array = array( 'Belgium' => 'BE' , 'Luxembourg'=> 'LU', 'France' => 'FR' , 'Spain' => 'ES' , 'Netherlands' => 'NL');
	$delivery_country_prefix = $country_array[$order->delivery['country']];
	$customer_country_prefix = $country_array[$order->customer['country']];

	//get the config country filled by the site admin
	$site_country_prefix = strtoupper(MODULE_SHIPPING_KIALAPOINT_SENDER_COUNTRY);
	
	//echo in_array($site_country_prefix,$country_array);

	//get the KP id
	$kp_name = $order->delivery['name'];
	if ($delivery_country_prefix != 'FR') {
		preg_match ( '#\((.*)\)#', $kp_name, $extract );
		$kp_id = $extract[1];
	} else {
		$tab_kp_name = explode(',', $kp_name);
		$kp_id = substr($tab_kp_name[0],11);
	}

	//get the sender Id and password related
	$sender_id = MODULE_SHIPPING_KIALAPOINT_SENDER_ID;
	$sender_password = MODULE_SHIPPING_KIALAPOINT_SENDER_PASSWORD;

	//get the reference number
	//$reference = ($debug_mode) ? uniqid() : (int) $order_id;
	$reference = (int)$order_id;

	//calculate the hash
	$hash = hash('sha512', $reference.$sender_id.$sender_password);

	//oscommerce originator
	$originator = 'OSC_2.2';

	// language
	// Les combinaisons de langue-pays suivantes sont autoris�es�: es-ES, fr-FR, fr-BE, nl-BE, nl-NL, fr-LU
	$lang = strtolower(MODULE_SHIPPING_KIALAPOINT_SENDER_COUNTRY);
	if ($lang == 'lu') $lang = 'fr';
	if ($lang == 'be') $lang = 'nl';

	
	//SOAP call parameters
	
	//if ($customer_country_prefix != $delivery_country_prefix) {
	
	if ($site_country_prefix != $delivery_country_prefix) {
	
		$params = array('reference' => $reference,
					'identification' => array('sender' => $sender_id, 'hash' => $hash, 'originator' => $originator),
					'delivery' => array(
									'from' => array('country' => $site_country_prefix,
													'node' => ''),
									'to' => array('country' => $delivery_country_prefix,
												  'node' => $kp_id),
								  ),
					'parcel' => array('description' => '',
									  'weight' => $products_weight['products_weight'],
									  'orderNumber' => $reference,
									  'orderDate' => $date
								),
					'receiver' => array('firstName' => sanitize($customer_firstname),
										'surname' => sanitize($customer_lastname),
										'address' => array(
											'line1' => sanitize($order->delivery['street_address']),
											'line2' => '',
											'postalCode' => $order->delivery['postcode']),
											'city' => sanitize($order->delivery['city']),
											'country' => $delivery_country_prefix,
										'email' => $order->customer['email_address'],
										'language' => $lang)
				  );
	}
	elseif ($customer_country_prefix != $delivery_country_prefix) {
	
		$params = array('reference' => $reference,
					'identification' => array('sender' => $sender_id, 'hash' => $hash, 'originator' => $originator),
					'delivery' => array(
									'from' => array('country' => $site_country_prefix,
													'node' => ''),
									'to' => array('country' => $delivery_country_prefix,
												  'node' => $kp_id),
								  ),
					'parcel' => array('description' => '',
									  'weight' => $products_weight['products_weight'],
									  'orderNumber' => $reference,
									  'orderDate' => $date
								),
					'receiver' => array('firstName' => sanitize($customer_firstname),
										'surname' => sanitize($customer_lastname),
										'address' => array(
											'line1' => sanitize($order->delivery['street_address']),
											'line2' => '',
											'postalCode' => $order->delivery['postcode']),
											'city' => sanitize($order->delivery['city']),
											'country' => $delivery_country_prefix,
										'email' => $order->customer['email_address'],
										'language' => $lang)
				  );
	}	else {
		$params = array('reference' => $reference,
					'identification' => array('sender' => $sender_id, 'hash' => $hash, 'originator' => $originator),
					'delivery' => array(
									'from' => array('country' => $site_country_prefix,
													'node' => ''),
									'to' => array('country' => $delivery_country_prefix,
												  'node' => $kp_id),
								  ),
					'parcel' => array('description' => '',
									  'weight' => $products_weight['products_weight'],
									  'orderNumber' => $reference,
									  'orderDate' => $date
								),
					'receiver' => array('firstName' => sanitize($customer_firstname),
										'surname' => sanitize($customer_lastname),
										'address' => array(
											'line1' =>  sanitize($order->customer['street_address']),
											'line2' => '',
											'postalCode' => $order->customer['postcode'],
											'city' =>  sanitize($order->customer['city']),
											'country' => $customer_country_prefix),
										'email' => $order->customer['email_address'],
										'language' => $lang)
				  );
	}	  

	//make the SOAP call
	$soap_url = ($debug_mode) ? 'http://packandship-ws-test.kiala.com:80/psws/order?wsdl' : 'http://packandship-ws.kiala.com:80/psws/order?wsdl';
	$client = new soapclient($soap_url, array('trace' => true, 'exceptions' => false));

///////////////////////////////////////////////////////////
// DEBUG
///////////////////////////////////////////////////////////
/*
echo '<hr />';
echo $soap_url;
echo '<hr />';
print_r($params);
echo '<hr />';
print_r($order);
echo '<hr />';
print_r($client);
/**/
	
	try {
		$result = $client->createOrder($params);
	} catch (SoapFault $fault) {
		print("ERROR: The Kiala webservice is down! please try later. Thank you.");
	}

	//if the webservice call is properly done
	if (isset($result->trackingNumber))
		echo "KIALA TRACKING NUMBER : ".$result->trackingNumber;
	//if the webservice return an error
	else if ($result->detail->orderFault->faultCode == "AUTHENTICATION_ERROR")
		echo "ERROR: Please check your authentification parameters of the your Kiala module (Sender Country, ID and Password)";
	 else {
		$msg = explode(':',$result->detail->orderFault->message);
		if ($result->detail->orderFault->faultCode == "INVALID_REQUEST" and $msg == "order already exists ")
			echo "ERROR: Order already exists : ".$msg[1];
		else 
			echo "ERROR: ".$result->detail->orderFault->message;
	}
}
else 
	echo "ERROR: no order id";
	
function sanitize($str){
	return mb_convert_encoding(trim($str) ?? '', 'UTF-8', 'ISO-8859-1');
}
?>