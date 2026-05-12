<?php

	require_once(DIR_WS_CLASSES . 'language.php');
    require_once(DIR_WS_MODULES . 'ups/classes/UpsTools.php');
    require_once(DIR_WS_MODULES . 'ups/classes/UpsValidate.php');
    require_once(DIR_WS_MODULES . 'ups/lib/UPSRegistrationApi.php');
    
    
    global $languages_id, $upsMessageStack, $debug;
        
    $action = Tools::getValue('action');
	$error = false;
	$shipperNumber = '';
			
	if($action == 'register'){
		$companyName 	= tep_db_prepare_input(Tools::getValue('company_name'));
		$addressLine1 	= tep_db_prepare_input(Tools::getValue('address'));
		$addressLine2 	= tep_db_prepare_input(Tools::getValue('address2'));
		$addressLine3 	= tep_db_prepare_input(Tools::getValue('address3'));
		$city 			= tep_db_prepare_input(Tools::getValue('city'));
		$postalCode 	= tep_db_prepare_input(Tools::getValue('postal'));
		$stateCode		= tep_db_prepare_input(Tools::getValue('state'));
		$countryCode 	= tep_db_prepare_input(Tools::getValue('country'));
		$contactName 	= tep_db_prepare_input(Tools::getValue('contact_name'));
		$contactTitle 	= tep_db_prepare_input(Tools::getValue('contact_title')); 		 
		$contactEmail 	= tep_db_prepare_input(Tools::getValue('contact_email')); 
		$contactPhone 	= tep_db_prepare_input(Tools::getValue('contact_phone')); 
		$companyUrl 	= tep_db_prepare_input(Tools::getValue('company_url'));
		 
		$upsMessageStack->reset();
        if(empty($companyName)){
		 	$upsMessageStack->add(UPS_ENTRY_COMPANY_NAME_ERROR, 'error');
		 	$error = true;
		}
		if(empty($addressLine1)){
		 	$upsMessageStack->add(UPS_ENTRY_ADDRESS_ERROR, 'error');
		 	$error = true;
		}
		if(empty($city)){
		 	$upsMessageStack->add(UPS_ENTRY_CITY_ERROR, 'error');
		 	$error = true;
		}
		if(empty($postalCode)){
		 	$upsMessageStack->add(UPS_ENTRY_POSTAL_ERROR, 'error');
		 	$error = true;
		}
		if(empty($countryCode)){
		 	$upsMessageStack->add(UPS_ENTRY_COUNTRY_ERROR, 'error');
		 	$error = true;
		}		
		if(empty($contactName)){
		 	$upsMessageStack->add(UPS_ENTRY_CONTACT_NAME_ERROR, 'error');
		 	$error = true;
		}
		if(empty($contactTitle)){
		 	$upsMessageStack->add(UPS_ENTRY_CONTACT_TITLE_ERROR, 'error');
		 	$error = true;
		}
		if(empty($contactEmail)){
		 	$upsMessageStack->add(UPS_ENTRY_CONTACT_EMAIL_ERROR, 'error');
		 	$error = true;
		}
		if (!Validate::isEmail($contactEmail)) {
      		$error = true;
      		$upsMessageStack->add(UPS_ENTRY_EMAIL_ADDRESS_CHECK_ERROR, 'error');
		}
		if(empty($contactPhone)){
		 	$upsMessageStack->add(UPS_ENTRY_CONTACT_PHONE_ERROR, 'error');
		}
		if(empty($companyUrl)){
		 	$upsMessageStack->add(UPS_ENTRY_COMPANY_URL_ERROR, 'error');
		 	$error = true;
		}
		if (!Validate::isUrl($companyUrl)) {
      		$error = true;
      		$upsMessageStack->add(UPS_ENTRY_COMPANY_URL_CHECK_ERROR, 'error');
		}
		if(!$error){
			$fields = array(
				'MODULE_SHIPPING_UPS_LICENSE_AGREED' => '1', //License agreed				
				'MODULE_SHIPPING_UPS_COMPANY_NAME' => $companyName, //Company Name				
				'MODULE_SHIPPING_UPS_ADDRESS' => $addressLine1, //Address				
				'MODULE_SHIPPING_UPS_ADDRESS2' => $addressLine2, //Address2				
				'MODULE_SHIPPING_UPS_ADDRESS3' => $addressLine3, //Address3				
				'MODULE_SHIPPING_UPS_CITY' => $city, //City				
				'MODULE_SHIPPING_UPS_POSTAL' => $postalCode, //Postal Code				
				'MODULE_SHIPPING_UPS_STATE' => $stateCode, //State				
				'MODULE_SHIPPING_UPS_COUNTRY' => $countryCode, //Country				
				'MODULE_SHIPPING_UPS_CONTACT_NAME' => $contactName, //Contact Name				
				'MODULE_SHIPPING_UPS_CONTACT_TITLE' => $contactTitle, //Contact Title				
				'MODULE_SHIPPING_UPS_CONTACT_EMAIL' => $contactEmail, //Contact Email
				'MODULE_SHIPPING_UPS_CONTACT_PHONE' => $contactPhone, //Contact Phone				
				'MODULE_SHIPPING_UPS_COMPANY_URL' => $companyUrl, //Company URL
			);
			
			foreach($fields as $key => $value){
				Tools::updateConfigValue($key, $value);
			}
			if($debug){
				$accessKey = '7CF75FD7BD75A775';
			}
			else{
				$accessKey = Tools::getConfigValue('MODULE_SHIPPING_UPS_ACCESS_KEY');   			
			}
			if(empty($accessKey)){
				//generate AccessKey
				$accessKey =  $UPSAccessLicenseApi->getAccessLicense(
			        $companyName,
			        $addressLine1,
			        $addressLine2,
			        $addressLine3,
			        $city,
			        $stateCode,
			        $postalCode,
			        $countryCode,
			        $contactName,
			        $contactTitle,
			        $contactEmail,
			        $contactPhone,
			        $companyUrl,
			        $shipperNumber,
			        $licenseAgreementCountryCode,
			        $licenseAgreementLanguageCode,
			        $licenseAgreementText,
			        'oscommerce'
	   			 );

	   			 if($accessKey){
	   			 	Tools::updateConfigValue('MODULE_SHIPPING_UPS_ACCESS_KEY', $accessKey);
	   			 }
	   			 else {
	   			 	$upsMessageStack->add(UPS_ENTRY_ACCESS_KEY_ERROR, 'error');
			 		$error = true;
	   			 }
   			 }
   			 
   			 if(!$error){
   			 	if($debug){
   			 		$username = 'AGENCEW360';
   			 		$password = '*UPS*777*';
   			 	}
   			 	else{
   			 		$username = Tools::getConfigValue('MODULE_SHIPPING_UPS_USERNAME');
   			 		$password = Tools::getConfigValue('MODULE_SHIPPING_UPS_PASSWORD');
   			 	}  			 	
   			 	if(empty($username) || empty($password)){  	   			 	
	   			 	//random username
	   			 	$username = chr(rand(65,90)) . chr(rand(65,90)) . chr(rand(65,90)) . chr(rand(65,90)) . chr(rand(65,90)). chr(rand(65,90)). chr(rand(65,90));
   			 		//random password
	   			 	$password = strtolower(chr(rand(65,90))) . rand(1,90) . chr(rand(65,90)) . chr(rand(65,90)) . '#' . rand(1,90). strtolower(chr(rand(65,90))). chr(rand(65,90));
	   			 	//Registration
	   			 	$UPSRegistrationApi = new UPSRegistrationApi($accessKey, $username, $password, $debug);
	   			 	
	   			 	try {
	   			 		$registration = $UPSRegistrationApi->doRegistration(
					        $username,
					        $password,
					        $companyName,
					        $contactName,
					        $contactTitle,
					        UPSBaseApi::formatAddress($addressLine1, $addressLine2, $addressLine3, $city, $stateCode, $postalCode, $countryCode),
					        $contactPhone,
					        $contactEmail,
					        $_SERVER['REMOTE_ADDR']
					    );
	   			 	} catch (Exception $e) {
	   			 		$upsMessageStack->add(UPS_ENTRY_REGISTRATION_ERROR, 'error');
				 		$error = true;
	   			 	}
	   			 	if(!$error && is_array($registration)){
					    Tools::updateConfigValue('MODULE_SHIPPING_UPS_USERNAME', $registration['username']);
					    Tools::updateConfigValue('MODULE_SHIPPING_UPS_PASSWORD', $registration['password']);
	   			 	}
   			 	}
   			 }
   			 if(!$error){
				$upsMessageStack->add(UPS_REGISTER_SUCCESS, 'success');
   			 	
   			 }   			 
		}            
	}
	
	if($action=='printLicense'){ 
		$licenseAgreementLanguageCode = strtoupper(Tools::getLanguageCode($languages_id));
		$licenseAgreementCountryCode = strtoupper(Tools::getCountryCode(STORE_COUNTRY));
		    
		//Generate license agreement
		$UPSAccessLicenseApi = new UPSAccessLicenseApi();	
		$licenseAgreementText = $UPSAccessLicenseApi->getLicenseAgreement($licenseAgreementCountryCode, $licenseAgreementLanguageCode);
		$src = '<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN"><html '.HTML_PARAMS.'>
		<head><meta http-equiv="Content-Type" content="text/html; charset='.CHARSET.'"><title>UPS TECHNOLOGY AGREEMENT</title></head>
		<body>'.nl2br($licenseAgreementText).'<script type="text/javascript">window.print();setTimeout("window.close()", 10000 );</script></body></html>';
		echo $src;	
		exit;
	}
	