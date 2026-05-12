<?php
ini_set('display_errors', 1);
class upsshipping {
	var $code, $title, $description, $icon, $enabled, $sort_order, $tax_class, $_check, $quotes;

	// constructor
	function __construct() {
		$this->title = 'UPS';
	    $this->code = 'upsshipping';
		$this->icon = DIR_WS_ICONS . 'ups.png';
	    $this->description = MODULE_SHIPPING_UPSSHIPPING_TITLE;
	    $this->sort_order = defined( 'MODULE_SHIPPING_UPSSHIPPING_SORT_ORDER' ) ? MODULE_SHIPPING_UPSSHIPPING_SORT_ORDER : 99;
	    $this->tax_class = defined( 'MODULE_SHIPPING_UPSSHIPPING_TAX_CLASS' ) ? MODULE_SHIPPING_UPSSHIPPING_TAX_CLASS : 0;
	    $this->enabled = ((defined( 'MODULE_SHIPPING_UPSSHIPPING_STATUS' ) && MODULE_SHIPPING_UPSSHIPPING_STATUS == 'True') ? true : false);
		
		if( $_SERVER['REMOTE_ADDR'] != '85.94.185.49' && $_SERVER['REMOTE_ADDR'] != '93.9.216.30')
			$this->enabled = false;
	}
	
	function quote($method = '') {
  		global $customer_id, $language, $languages_id, $order, $shipping_weight, $shipping_num_boxes, $cart, $cartID;

  		require_once(DIR_FS_CATALOG.'ext/modules/shipping/upsshipping/UPSInit.php');
  		$newService = !(Tools::getValue('action') == 'process');
  		$defaultServiceArr = UpsService::getDefaultUpsService($order->delivery['country']['iso_code_2']);
		if(!empty($defaultServiceArr)){			
			$defaultService = new UpsService((int) $defaultServiceArr['id_ups_service']);
			if(!isset($_SESSION['upsRate']))			
				$_SESSION['upsRate'] = $defaultService->getQuote($order->delivery, false);
			$quote = $_SESSION['upsRate'];
			if(!$quote)
				$quote['amount'] = 0;
			
  		}
		else
			$quote['amount'] = 0;
  		if($newService){
	  		$services = Tools::jsonEncode(UpsService::getServices());
	  		$delivery = Tools::jsonEncode($order->delivery);
	  		
	  		//define the quote object to return
	    	$src = '<link rel="stylesheet" type="text/css" href="ext/modules/shipping/upsshipping/css/upsAccessPointWidget.css" />
			<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">
			<script src="//code.jquery.com/jquery-1.11.3.min.js"></script>
			<script>jQuery.noConflict();</script>';
			$src .= '<script type="text/javascript" src="ext/modules/shipping/upsshipping/js/upsshipping.js"></script>    	
	    	<link rel="stylesheet" type="text/css" href="ext/modules/shipping/upsshipping/css/upsshipping.css" />
	    	<script type="text/javascript">			
				var UPSAjaxUrl = "'.DIR_WS_EXT_UPS.'ajax.php";    	
				
	    		jQuery( document ).ready(function() {
		    		initUpsServicesContent("'.addslashes($delivery).'");
		    	});
	    	</script>';   	
  		}
  		$this->quotes = array(
    		'id' => $this->code,
            'module' => $this->title,
            'methods' => array(
    			array(
    				'id' => $this->code,
                    'title' => (($newService) ? $this->title.$src : $this->title),
                    'cost' => ((!$newService) ? $_SESSION['upsRate']['amount'] : $quote['amount']),
    			)
    		)
    	);
		//Add the Ups module icon
		if (tep_not_null($this->icon)) $this->quotes['icon'] = tep_image($this->icon, $this->title);
		
		return $this->quotes;
  	}
  	
	function update_order($id_order, $cartID = false){
		global $language, $upsOrderCartID;
		require_once(DIR_FS_CATALOG.'ext/modules/shipping/upsshipping/UPSInit.php');
  		$selectedService = UpsSelectedService::getSelectedServiceByCartID((int) $upsOrderCartID);		
  		tep_session_unregister('upsOrderCartID');
  		
  		if($selectedService && $id_order){
  			$selectedService->id_order = (int) $id_order;
  			return $selectedService->save();
  		}
  		return false;
	}
	
	//check function
    function check() {
		if (!isset($this->_check)) {
		$check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SHIPPING_UPSSHIPPING_STATUS'");
		$this->_check = tep_db_num_rows($check_query);
		}
		return $this->_check;
    }
	
	//Ups module keys
	function keys() {
		return array(
			'MODULE_SHIPPING_UPSSHIPPING_STATUS',
			'MODULE_SHIPPING_UPS_TAX_CLASS',
			'MODULE_SHIPPING_UPSSHIPPING_DEBUG'
		);
	}
	
	//install Ups module
	function install() {
						
		$config_fields = array(
			//Status		
			array('title' => 'Activation status of Ups Point module', 'key' => 'MODULE_SHIPPING_UPSSHIPPING_STATUS', 'value' => 'True', 'description' => 'Activate/Deactivate the Ups Point module', 'set_function' => 'tep_cfg_select_option(array(\'True\', \'False\'), '),
			//Debug		
			array('title' => 'Activation status of Debug Mode', 'key' => 'MODULE_SHIPPING_UPSSHIPPING_DEBUG', 'value' => 'True', 'description' => 'Activate/Deactivate the Ups Debug', 'set_function' => 'tep_cfg_select_option(array(\'True\', \'False\'), '),
			//Tax class		
			array('title' => 'Tax Class', 'key' => 'MODULE_SHIPPING_UPS_TAX_CLASS', 'value' => '0', 'description' => 'Use the following tax class on the shipping fee.', 'use_function' => 'tep_get_tax_class_title', 'set_function' => 'tep_cfg_pull_down_tax_classes('),
			//License agreed
			array('title' => 'License agreed', 'key' => 'MODULE_SHIPPING_UPS_LICENSE_AGREED', 'value' => '0', 'description' => ''),
			//Access Key
			array('title' => 'Access Key', 'key' => 'MODULE_SHIPPING_UPS_ACCESS_KEY', 'value' => '', 'description' => ''),
			//Username
			array('title' => 'Username', 'key' => 'MODULE_SHIPPING_UPS_USERNAME', 'value' => '', 'description' => ''),
			//Password
			array('title' => 'Password', 'key' => 'MODULE_SHIPPING_UPS_PASSWORD', 'value' => '', 'description' => ''),
			//Company Name
			array('title' => 'Company name', 'key' => 'MODULE_SHIPPING_UPS_COMPANY_NAME', 'value' => '', 'description' => 'Please fill in this field with your Company name'),
			//Address
			array('title' => 'Address', 'key' => 'MODULE_SHIPPING_UPS_ADDRESS', 'value' => '', 'description' => 'Please fill in this field with your address'),
			//Address2
			array('title' => 'Address 2', 'key' => 'MODULE_SHIPPING_UPS_ADDRESS2', 'value' => '', 'description' => ''),
			//Address3
			array('title' => 'Address 3', 'key' => 'MODULE_SHIPPING_UPS_ADDRESS3', 'value' => '', 'description' => ''),
			//City
			array('title' => 'City', 'key' => 'MODULE_SHIPPING_UPS_CITY', 'value' => '', 'description' => 'Please fill in this field with your city name'),
			//Postal Code
			array('title' => 'Postal code*', 'key' => 'MODULE_SHIPPING_UPS_POSTAL', 'value' => '', 'description' => 'Please fill in this field with your postal code'),
			//State
			array('title' => 'State', 'key' => 'MODULE_SHIPPING_UPS_STATE', 'value' => '', 'description' => 'Please fill in this field with your state'),
			//Country
			array('title' => 'Country', 'key' => 'MODULE_SHIPPING_UPS_COUNTRY', 'value' => '', 'description' => 'Please fill in this field with your country'),
			//Contact Name
			array('title' => 'Contact name', 'key' => 'MODULE_SHIPPING_UPS_CONTACT_NAME', 'value' => '', 'description' => 'Please fill in this field with your contact name'),
			//Contact Title
			array('title' => 'Contact Title', 'key' => 'MODULE_SHIPPING_UPS_CONTACT_TITLE', 'value' => '', 'description' => 'Please fill in this field with your contact title'),
			//Contact Email
			array('title' => 'Contact email', 'key' => 'MODULE_SHIPPING_UPS_CONTACT_EMAIL', 'value' => '', 'description' => 'Please fill in this field with your contact email'),
			//Contact Phone
			array('title' => 'Contact phone', 'key' => 'MODULE_SHIPPING_UPS_CONTACT_PHONE', 'value' => '', 'description' => 'Please fill in this field with your contact phone'),
			//Company URL
			array('title' => 'Company URL', 'key' => 'MODULE_SHIPPING_UPS_COMPANY_URL', 'value' => '', 'description' => 'Please fill in this field with your company URL'),	
			//Exportable order state
			array('title' => 'Exportable order state', 'key' => 'MODULE_SHIPPING_UPS_EXPORTABLE_ORDER_STATE', 'value' => '0', 'description' => ''),
			//Default location search range
			array('title' => 'Default location search range', 'key' => 'MODULE_SHIPPING_UPS_DEFAULT_LOCATION_SEARCH_RANGE', 'value' => '0', 'description' => ''),			
			//Declared Value
			array('title' => 'Declared Value', 'key' => 'MODULE_SHIPPING_UPS_DECLARED_VALUE', 'value' => '0', 'description' => ''),
			//Allow Access Point COD
			array('title' => 'Allow Access Point COD', 'key' => 'MODULE_SHIPPING_UPS_ALLOW_ACCESS_POINT_COD', 'value' => '0', 'description' => ''),
			//Allow Deliver to Addressee Only
			array('title' => 'Allow Deliver to Addressee Only', 'key' => 'MODULE_SHIPPING_UPS_DELIVER_TO_ADDRESSEE_ONLY', 'value' => '0', 'description' => ''),
			//Allow Delivery Confirmation Signature Required
			array('title' => 'Allow Delivery Confirmation Signature Required', 'key' => 'MODULE_SHIPPING_UPS_CONFIRMATION_SIGNATURE_REQUIRED', 'value' => '0', 'description' => ''),
			//Allow Delivery Confirmation Adult Signature Required
			array('title' => 'Allow Delivery Confirmation Adult Signature Required', 'key' => 'MODULE_SHIPPING_UPS_CONFIRMATION_ADULT_SIGNATURE_REQUIRED', 'value' => '0', 'description' => ''),
			//Order statuses tracked
			array('title' => 'Order statuses tracked', 'key' => 'MODULE_SHIPPING_UPS_ORDER_STATUS_TRACKED', 'value' => '0', 'description' => ''),
			//Delivered Order Status
			array('title' => 'Delivered Order Status', 'key' => 'MODULE_SHIPPING_UPS_DELIVERED_ORDER_STATUS', 'value' => '0', 'description' => ''),
			//In transit Order Status
			array('title' => 'In transit Order Status', 'key' => 'MODULE_SHIPPING_UPS_IN_TRANSIT_ORDER_STATUS', 'value' => '0', 'description' => ''),
			//Tracking by
			array('title' => 'Tracking by', 'key' => 'MODULE_SHIPPING_UPS_TRACKING_BY', 'value' => '0', 'description' => '0:tracking by visitor action - 1:tracking by cron'),
			//Cron link
			array('title' => 'Cron link', 'key' => 'MODULE_SHIPPING_UPS_CRON_LINK', 'value' => '', 'description' => ''),
							
		);
		
		foreach($config_fields as $field){
			tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) VALUES ('".$field['title']."', '".$field['key']."', '".$field['value']."', '".$field['description']."', '6', '0', '".(isset($field['use_function']) ? addslashes($field['use_function']) : '')."', '".(isset($field['set_function']) ? addslashes($field['set_function']) : '')."', now())");		
		}
		
		//creation table ups_account
		tep_db_query("CREATE TABLE IF NOT EXISTS `ups_account` (
		  `id_ups_account` int(11) NOT NULL AUTO_INCREMENT,
		  `account_name` varchar(35) NOT NULL,
		  `account_number` varchar(6) NOT NULL,
		  `shipper_name` varchar(35) NOT NULL,
		  `shipper_attention_name` varchar(35) NOT NULL,
		  `dni_number` varchar(15) NOT NULL,
		  `phone_number` varchar(15) NOT NULL,
		  `address_line_1` varchar(35) NOT NULL,
		  `address_line_2` varchar(35) NOT NULL,
		  `id_country` int(11) NOT NULL,
		  `state` varchar(30) NOT NULL,
		  `city` varchar(30) NOT NULL,
		  `postal_code` varchar(9) NOT NULL,
		  `is_ups_ape` tinyint(1) NOT NULL,
		  `pickup_type` VARCHAR(2) NOT NULL,
		  `date_add` datetime NOT NULL,
		  `date_upd` datetime NOT NULL,
		  PRIMARY KEY (`id_ups_account`)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1 ;");
		
		//creation table ups_exports
		tep_db_query("CREATE TABLE IF NOT EXISTS `ups_exports` (
		  `id_export` int(11) NOT NULL AUTO_INCREMENT,
		  `orders` text NOT NULL,
		  `date_add` datetime NOT NULL,
		  `type` varchar(10) NOT NULL,
		  `image` blob NOT NULL,
		  PRIMARY KEY (`id_export`)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1 ;");
		
		//creation table ups_services
		tep_db_query("CREATE TABLE IF NOT EXISTS `ups_services` (
		  `id_ups_service` int(11) NOT NULL AUTO_INCREMENT,
		  `id_ups_account` int(11) NOT NULL,
		  `id_carrier` int(11) NOT NULL,
		  `service_code` varchar(2) NOT NULL,
		  `service_name` varchar(45) NOT NULL,
		  `dest_countries` varchar(70) NOT NULL,
		  PRIMARY KEY (`id_ups_service`)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1 ;");
		
		
		//creation table ups_selected_service
		tep_db_query("CREATE TABLE IF NOT EXISTS `ups_selected_service` (
		  `id_ups_selected_service` INT(11) NOT NULL AUTO_INCREMENT,
		  `id_cart` INT(11) NOT NULL,
		  `id_order` INT(11) NOT NULL,
		  `id_ups_service` VARCHAR(45) NOT NULL,
		  `location_id` VARCHAR(10) NULL DEFAULT NULL,
		  `public_access_point_id` VARCHAR(15) NULL DEFAULT NULL,
		  `name` VARCHAR(35) NULL DEFAULT NULL COMMENT 'commas separated country code list',
		  `address` VARCHAR(35) NULL DEFAULT NULL,
		  `postal_code` VARCHAR(9) NULL DEFAULT NULL,
		  `city` VARCHAR(30) NULL DEFAULT NULL,
		  `country_code` VARCHAR(2) NULL DEFAULT NULL,
		  `declared_value` TINYINT(1) NOT NULL,
		  `access_point_cod` TINYINT(1) NOT NULL,
		  `to_addressee_only` TINYINT(1) NOT NULL,
		  `signature` INT(1) NOT NULL,
		  `adult_signature` INT(1) NOT NULL,
		  `order_weight` FLOAT NOT NULL,
		  `order_amount` FLOAT NOT NULL,
		  `deleted` TINYINT(1) NOT NULL DEFAULT '0',
		  PRIMARY KEY (`id_ups_selected_service`)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1 ;");
	}
	
	//remove Ups module
	function remove() {
		tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key LIKE ('MODULE_SHIPPING_UPS%')");
		tep_db_query("drop table ups_account");
		tep_db_query("drop table ups_exports");
		tep_db_query("drop table ups_services");
		tep_db_query("drop table ups_selected_service");
	}
}
?>