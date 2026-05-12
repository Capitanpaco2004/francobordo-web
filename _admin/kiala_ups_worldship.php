<?php
require('includes/application_top.php');
include(DIR_WS_CLASSES . 'order.php');

global $languages_id;

$csvstring = '';

//get the order object
$get_order_ids = (isset($_GET["oID"])) ? $_GET["oID"] : false;
$tabOrders = explode(',' , $get_order_ids);

// create the CSV file
$csvName = 'exportworldship.csv';
$fp = fopen('csvKiala/'.$csvName, 'w');

foreach ($tabOrders as $order_id) {
	if ($order_id != '') {
	$order = new order((int)$order_id);

	//get the customer firstname and surname seprately
	$customers_query = tep_db_query("select T2.customers_id, T2.customers_gender, T2.customers_firstname,T2.customers_lastname, T1.customers_country from " . TABLE_ORDERS . " T1 , " . TABLE_CUSTOMERS . " T2 where T1.customers_id = T2.customers_id and T1.orders_id = '" . $order_id . "'");
	$customer = tep_db_fetch_array($customers_query);
	$customer_id = $customer['customers_id'];
	$customers_gender = $customer['customers_gender']; // customerTitle
	$customer_firstname = $customer['customers_firstname'];
	$customer_lastname = $customer['customers_lastname'];

	//get the weight
	$products_query = tep_db_query("select T2.products_weight from " . TABLE_ORDERS_PRODUCTS . " T1 , " . TABLE_PRODUCTS . " T2 where T1.products_id = T2.products_id and T1.orders_id = '" . $order_id . "'");
	$products_weight = tep_db_fetch_array($products_query);

	$languages_query = tep_db_query("select languages_id, code from " . TABLE_LANGUAGES . " order by sort_order");
	while ($languages = tep_db_fetch_array($languages_query)) {
		if ($languages['languages_id'] == $languages_id)
			$lang = $languages['code'];
	}
	
	// shipment number
	$select_qry = tep_db_query('select status from kiala_orders_status where id='. $order_id);
	$kiala_status = tep_db_fetch_array($select_qry);
	$kialaStatusTab = explode (' : ', $kiala_status['status']);
	$shipmentNumber = substr($kialaStatusTab[1],2);

	// commercial amount
	$select_qry = tep_db_query('select value from '. TABLE_ORDERS_TOTAL .' where orders_id='. $order_id .' AND class="ot_total"');
	$orders_value = tep_db_fetch_array($select_qry);

	//get the delivery country prefix
	$country_array = array( 'Belgium' => 'BE' , 'Luxembourg' => 'LU' , 'France' => 'FR' , 'Spain' => 'ES' , 'Netherlands' => 'NL');
	$customerLocality = $country_array[$order->customer['country']];
	$kpCountry = $country_array[$order->delivery['country']];
	
	//get the KP id
	if ($customerLocality != 'FR') {
		$kp_name = $order->delivery['name'];
		preg_match ( '#\((.*)\)#', $kp_name, $extract );
		$kp_id = $extract[1];
	} else {
		$kp_name = $order->delivery['name'];
		$tab_kp_name = explode(',', $kp_name);
		$kp_id = substr($tab_kp_name[0],11);
	}
	$kp_id = str_replace('K', '', $kp_id);
	
	$tab_kp_name = explode(',', $order->delivery['name']);
	$kp_name = ltrim($tab_kp_name[1]);
	
	//get the site language if $customerLocality is Belgium
	$lang = strtolower($customerLocality);
	if ($lang == 'be') {
		$languages_query = tep_db_query("select languages_id, code from " . TABLE_LANGUAGES . " order by sort_order");
		while ($languages = tep_db_fetch_array($languages_query)) {
			if ($languages['languages_id'] == $languages_id)
				$lang = $languages['code'];
		}
	} elseif ($lang == 'lu') {
		$lang = 'fr';
	}
	
	// get the kp address
	$url = 'http://locateandselect.kiala.com/details?countryid=' . $kpCountry . '&language=' . $kpCountry . '&map=on&align=left&shortID=' . $kp_id;
	$kpdata = file_get_contents($url);
	$premierexplode = explode ('<div class="address">',$kpdata);
	$secondexplode = explode ('</div>',$premierexplode[1]);
	$troisiemeexplode = explode ('<br/>',$secondexplode[0]);
	$quatriemeexplode = explode (',',$troisiemeexplode[0]);
	$kpAddress = $quatriemeexplode[0];
	$kpHouseNumber = ltrim($quatriemeexplode[1]);
	
	$configuration_key = 'MODULE_SHIPPING_KIALAPOINT_DSPID_' . $customerLocality;
	$select_qry = tep_db_query('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE `configuration_key` = "MODULE_SHIPPING_KIALAPOINT_DSPID_BE"');
	$config_value = tep_db_fetch_array($select_qry);
	$dspid = $config_value['configuration_value'];
	
	$data = array(
		'partnerID' => $dspid,

		'partnerBarcode' => '',
		'parcelNumber' => $dspid . $shipmentNumber,
		'orderNumber' => $order_id,
        'orderDate' => date("Ymd",strtotime($order->info['date_purchased'])),
		'invoiceNumber' => $order_id,
		'invoiceDate' => '',
		'shipmentNumber' => $shipmentNumber,
		'CODAmount' => '0.00',
		'commercialValue' => round($orders_value['value'], 2),
		'parcelWeight' => $products_weight['products_weight'],
		'parcelVolume' => '',
		'parcelDescription' => 'OSC_2.2',

		'customerId' => $order->customer['email_address'],
		'customerName' => mb_convert_encoding($customer_lastname ?? '', 'UTF-8', 'ISO-8859-1'),
		'customerFirstName' => mb_convert_encoding($customer_firstname ?? '', 'UTF-8', 'ISO-8859-1'),
		'customerTitle' => '',
		'customerStreet' => mb_convert_encoding($order->customer['street_address'] ?? '', 'UTF-8', 'ISO-8859-1'),
		'customerStreetNumber' => '',
		'customerExtraAddressLine' => '',
		'customerZip' => $order->customer['postcode'],
		'customerCity' => mb_convert_encoding($order->customer['city'] ?? '', 'UTF-8', 'ISO-8859-1'),
		'customerLocality' => $customerLocality,
		'customerLanguage' => $lang,
		'customerPhone1' => $order->customer['telephone'],
		'customerPhone2' => '',
		'customerPhone3' => '',
		'customerEmail1' => $order->customer['email_address'],
		'customerEmail2' => '',
		'customerEmail3' => '',
		
		'positiveNotificationRequested' => 'Y',
		'kialaPoint' => $kp_id,
		'backupKialaPoint' => '',
		
		'APstoreName' => $kp_name,
		'APaddress' => trim($kpAddress),
		'APcity' => trim(mb_convert_encoding($order->delivery['city'] ?? '', 'UTF-8', 'ISO-8859-1')),
		'APpostalCode' => $order->delivery['postcode'],
		'APcountry' => $kpCountry,
		'ConsigneeHouseNumber' => $kpHouseNumber
	);

/*
	// echo $order->delivery['name'];
 	print_r ($data);
	die();
/**/
	
	foreach ($data as $key => $val){
		$csvstring .= $val . '|';
	}
	$csvstring = substr($csvstring,0,-1);
	$csvstring .= "\n";
}}

$headerstring = '';
foreach ($data as $key => $val){
	$headerstring .= $key . '|';
}
$headerstring = substr($headerstring,0,-1);
$headerstring .= "\n";

fputs($fp, $headerstring.$csvstring);
fclose($fp);

echo $csvName;
?>