<?php
require('includes/application_top.php');
global $sendto;

$client_order_org = array('firstname' => '',
						  'lastname' => '',
						  'company' => '',
						  'street_address' => '',
						  'suburb' => '',
						  'city' => '',
						  'postcode' => '',
						  'state' => '',
						  'zone_id' => '',
						  'country[id]' => '',
						  'country[title]' => '',
						  'country[iso_code_2]' => '',
						  'country[iso_code_3]' => '',
						  'format_id' => ''
						  );
						  
$client_order = explode('.',$_GET["kp_order"]);
						  
foreach ($client_order as $val) {
	$val = explode('=',$val);
	$key = $val[0];
	$value = $val[1];
	$client_order_org[$key]=$value;
}

if (strpos($_GET["kp_id"], '(')) {
	preg_match ( '#\((.*)\)#', $_GET["kp_id"], $extract );
	$kp = $extract[1];
} else {
	$kp = $_GET["kp_id"] ;
}

$sendto = array('firstname' => 'KIALAPOINT '.$kp.',',
				'lastname' => stripslashes(html_entity_decode(($_GET["kp_name"]))),
				'company' => '' , 
				'street_address' => stripslashes(strip_tags($_GET["kp_address"])),
				'suburb' => '',
				'postcode' => $_GET["kp_zip"],
				'city' => stripslashes($_GET["kp_city"]),
				'zone_id' => $customer_zone_id,
				'zone_name' => '',
				'country_id' => $client_order_org['country[id]'],
				'country_name' => stripslashes($client_order_org['country[title]']),
				'country_iso_code_2' => stripslashes($client_order_org['country[iso_code_2]']),
				'country_iso_code_3' => stripslashes($client_order_org['country[iso_code_3]']),
				'address_format_id' => $client_order_org['format_id']
				);
?>