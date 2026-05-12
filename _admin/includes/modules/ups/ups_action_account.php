<?php
require_once(DIR_WS_MODULES . 'ups/classes/UpsTools.php');
require_once(DIR_WS_MODULES . 'ups/classes/UpsValidate.php');
require_once(DIR_WS_MODULES . 'ups/classes/UpsAccount.php');
require_once(DIR_WS_MODULES . 'ups/lib/UPSRegistrationApi.php');

global $languages_id, $upsMessageStack;

if(!Tools::getConfigValue('MODULE_SHIPPING_UPS_LICENSE_AGREED'))
	tep_redirect(tep_href_link('ups_configure.php', '', 'NONSSL'));
	
$action = Tools::getValue('action');

if($action == 'createAccount'){
    $accountName 			= tep_db_prepare_input(Tools::getValue('account_name'));
    $accountNumber 			= tep_db_prepare_input(Tools::getValue('account_number'));
    $accountPostal 			= tep_db_prepare_input(Tools::getValue('account_postal'));
    $accountCountry			= tep_db_prepare_input(Tools::getValue('account_country'));
	$dniNumber 				= tep_db_prepare_input(Tools::getValue('dni_number'));
	$invoiceNumber 			= (tep_db_prepare_input(Tools::getValue('invoice_number', false))) ? tep_db_prepare_input(Tools::getValue('invoice_number', false)) : null;
	$invoiceDate 			= (tep_db_prepare_input(Tools::getValue('invoice_date', false))) ? tep_db_prepare_input(Tools::getValue('invoice_date', false)) : null;
	$invoiceCurrencyCode 	= (tep_db_prepare_input(Tools::getValue('invoice_currency_code', false))) ? tep_db_prepare_input(Tools::getValue('invoice_currency_code', false)) : null;
	$invoiceAmount 			= (tep_db_prepare_input(Tools::getValue('invoice_amount', false))) ? tep_db_prepare_input(Tools::getValue('invoice_amount', false)) : null;
	$invoiceControlId 		= (tep_db_prepare_input(Tools::getValue('invoice_control_id', false))) ? tep_db_prepare_input(Tools::getValue('invoice_control_id', false)) : null;
		
    $upsMessageStack->reset();
    if(empty($accountName)){
		$upsMessageStack->add(UPS_ENTRY_ACCOUNT_NAME_ERROR, 'error');
		$error = true;
	}
    if(empty($accountNumber)){
		 $upsMessageStack->add(UPS_ENTRY_ACCOUNT_NUMBER_ERROR, 'error');
		 $error = true;
	}
    if(empty($accountPostal)){
		 $upsMessageStack->add(UPS_ENTRY_ACCOUNT_POSTAL_ERROR, 'error');
		 $error = true;
	}
    if(empty($accountCountry)){
	 	$upsMessageStack->add(UPS_ENTRY_ACCOUNT_COUNTRY_ERROR, 'error');
	 	$error = true;
	}
    	
	if(!$error){
		if($debug){
			$accessKey = '7CF75FD7BD75A775';
   			$username = 'AGENCEW360';
   			$password = '*UPS*777*';
		}
		else{
			$accessKey = Tools::getConfigValue('MODULE_SHIPPING_UPS_ACCESS_KEY');
   			$username = Tools::getConfigValue('MODULE_SHIPPING_UPS_USERNAME');
   			$password = Tools::getConfigValue('MODULE_SHIPPING_UPS_PASSWORD');
		}
		
		$UPSRegistrationApi = new UPSRegistrationApi($accessKey, $username, $password, $debug);
   			
   		try {
   		 	if($debug){
   		 		$registration = $UPSRegistrationApi->doAddAccount(
					'test',
					"061Y63",
					"06220",
					"FR",
					$invoiceNumber,
					$invoiceDate,
					$invoiceCurrencyCode,
					$invoiceAmount,
					$invoiceControlId
				 );		
   		 	}
			else{
				$registration = $UPSRegistrationApi->doAddAccount(
					$accountName ,
					$accountNumber,
					$accountPostal,
					$accountCountry,
					$invoiceNumber,
					$invoiceDate,
					$invoiceCurrencyCode,
					$invoiceAmount,
					$invoiceControlId
				 );  		 	
   		 	}	
		} catch(Exception $e) {
		    nl2br(print_r($e));
		     $error = true;
		}
		if($error || !$registration){	
			$upsMessageStack->add(UPS_ADD_ACCOUNT_ERROR, 'error');
		    $error = true;
		}
		if(!$error){		
			$accountIdCountry = Tools::getCountryId($accountCountry);   	
			
			$account = new UpsAccount();
			$account->account_name 				= $accountName;
			$account->account_number 			= $accountNumber;
			$account->shipper_name 				= Tools::getConfigValue('MODULE_SHIPPING_UPS_COMPANY_NAME');
			$account->shipper_attention_name 	= Tools::getConfigValue('MODULE_SHIPPING_UPS_CONTACT_NAME');
			$account->phone_number 				= Tools::getConfigValue('MODULE_SHIPPING_UPS_CONTACT_PHONE');			
			$account->id_country 				= $accountIdCountry;			
			$account->address_line_1 			= Tools::getConfigValue('MODULE_SHIPPING_UPS_ADDRESS');
			$account->address_line_2 			= Tools::getConfigValue('MODULE_SHIPPING_UPS_ADDRESS2');
			$account->state 					= Tools::getConfigValue('MODULE_SHIPPING_UPS_STATE');
			$account->city 						= Tools::getConfigValue('MODULE_SHIPPING_UPS_CITY');
			$account->postal_code 				= Tools::getConfigValue('MODULE_SHIPPING_UPS_POSTAL');
			$account->is_ups_ape 				= false;
			$account->pickup_type 				= '01';						
			
			if(!$account->save()){
				$upsMessageStack->add(UPS_SAVE_ACCOUNT_ERROR, 'error');
		 		$error = true;
			}
			else{
				$upsMessageStack->add(UPS_SAVE_ACCOUNT_SUCCESS, 'success');
			}				
		}
	}
   	$activeTab = 'account';
    
}
elseif($action == 'updateAccount'){
    $accountId 	= tep_db_prepare_input(Tools::getValue('id_ups_account'));
    if(!$accountId)
    	return;
    	
    $shipperName 			= tep_db_prepare_input(Tools::getValue('shipper_name_'.$accountId));
    $shipperAttentionName 	= tep_db_prepare_input(Tools::getValue('shipper_attention_name_'.$accountId));    	
    $phoneNumber 			= tep_db_prepare_input(Tools::getValue('phone_number_'.$accountId));
    $addressLine1 			= tep_db_prepare_input(Tools::getValue('address_line_1_'.$accountId));    	
    $addressLine2 			= tep_db_prepare_input(Tools::getValue('address_line_2_'.$accountId));
    $shopIdCountry 			= tep_db_prepare_input(Tools::getValue('shop_id_country_'.$accountId));
    $shopState 				= tep_db_prepare_input(Tools::getValue('shop_state_2_'.$accountId));
    $shopCity 				= tep_db_prepare_input(Tools::getValue('shop_city_'.$accountId));
    $shopPostal 			= tep_db_prepare_input(Tools::getValue('shop_postal_'.$accountId));
    $isUpsApe	 			= (int) tep_db_prepare_input(Tools::getValue('is_ups_ape_'.$accountId));
    $pickupType				= tep_db_prepare_input(Tools::getValue('pickup_type_'.$accountId));
    $dniNumber				= tep_db_prepare_input(Tools::getValue('dni_number_'.$accountId));

    $upsMessageStack->reset();
    if(empty($shipperName)){
	 	$upsMessageStack->add(UPS_ENTRY_ACCOUNT_SHIPPER_NAME_ERROR, 'error');
	 	$error = true;
	}
    if(empty($shipperAttentionName)){
	 	$upsMessageStack->add(UPS_ENTRY_ACCOUNT_SHIPPER_ATTENTION_NAME_ERROR, 'error');
	 	$error = true;
	}
    if(empty($phoneNumber)){
	 	$upsMessageStack->add(UPS_ENTRY_ACCOUNT_SHIPPER_PHONE_NUMBER_ERROR, 'error');
	 	$error = true;
	}
    if(empty($addressLine1)){
	 	$upsMessageStack->add(UPS_ENTRY_ACCOUNT_SHIPPER_ADDRESS_LINE1_ERROR, 'error');
	 	$error = true;
	}
    if(empty($shopCity)){
	 	$upsMessageStack->add(UPS_ENTRY_ACCOUNT_SHIPPER_SHOP_CITY_ERROR, 'error');
	 	$error = true;
	}
    if(empty($shopPostal)){
	 	$upsMessageStack->add(UPS_ENTRY_ACCOUNT_SHIPPER_SHOP_POSTAL_ERROR, 'error');
	 	$error = true;
	}
	if(empty($pickupType)){
	 	$upsMessageStack->add(UPS_ENTRY_ACCOUNT_PICKUP_TYPE_ERROR, 'error');
	 	$error = true;
	}	
	
	if(!$error){
		$account = new UpsAccount((int) $accountId);
		$account->shipper_name 				= $shipperName;
		$account->shipper_attention_name 	= $shipperAttentionName;
		$account->dni_number				= $dniNumber;
		$account->phone_number 				= $phoneNumber;			
		$account->id_country 				= $shopIdCountry;			
		$account->address_line_1 			= $addressLine1;
		$account->address_line_2 			= $addressLine2;
		$account->state 					= $shopState;
		$account->city 						= $shopCity;
		$account->postal_code 				= $shopPostal;
		$account->is_ups_ape 				= $isUpsApe;
		$account->pickup_type 				= $pickupType;
		
		if(!$account->save()){
			$upsMessageStack->add(UPS_UPDATE_ACCOUNT_ERROR, 'error');
	 		$error = true;
		}
		else{
			$upsMessageStack->add(UPS_UPDATE_ACCOUNT_SUCCESS, 'success');
			//tep_redirect(tep_href_link('ups_configure.php', '', 'NONSSL'));
		}
   		$activeTab = 'account';
	}
}
elseif($action == 'updateAccountsSettings'){
	$exportableOrderStateId	= (int) tep_db_prepare_input(Tools::getValue('exportable_order_state'));
    $locationSearchRange 	= (int) tep_db_prepare_input(Tools::getValue('search_range'));    	
    Tools::updateConfigValue('MODULE_SHIPPING_UPS_EXPORTABLE_ORDER_STATE', $exportableOrderStateId);
    Tools::updateConfigValue('MODULE_SHIPPING_UPS_DEFAULT_LOCATION_SEARCH_RANGE', $locationSearchRange);
    $upsMessageStack->add(UPS_UPDATE_ACCOUNT_SETTINGS_SUCCESS, 'success');
}