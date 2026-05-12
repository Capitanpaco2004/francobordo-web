<?php
	require_once(DIR_WS_CLASSES . 'language.php');
	require_once(DIR_WS_LANGUAGES.$language.'/ups_configure.php');
    require_once(DIR_WS_MODULES . 'ups/classes/UpsTools.php');
    require_once(DIR_WS_MODULES . 'ups/classes/UpsValidate.php');
	require_once(DIR_WS_MODULES . 'ups/lib/UPSRegistrationApi.php');
    
    global $languages_id, $upsMessageStack, $activeTab;

	if(!Tools::getConfigValue('MODULE_SHIPPING_UPS_LICENSE_AGREED'))
		tep_redirect(tep_href_link('ups_configure.php', '', 'NONSSL'));
		
	$action = Tools::getValue('action');
	
	if($action == 'getServiceDest'){
		$id_account = (int) Tools::getValue('id_account');
		$service_code = Tools::getValue('code');
		$destination_countries_list	= UpsService::getUpsServicesDest($service_code, $id_account);
		$output = '';
		if(!empty($destination_countries_list)){
			foreach($destination_countries_list as $key => $country_code){
				$output .= '<tr class="row'.($key % 2).'"><td>'.$country_code.'</td><td>'.tep_draw_checkbox_field('service_country_code[]', $country_code, '').'</td></tr>';
			}
		}
		echo $output;
		exit;
	}
	elseif($action == 'getServices'){
		$id_account 	= (int) Tools::getValue('id_account');
		$services_list 	= UpsService::getUpsServicesList((int) $id_account);
		$output = '';
		foreach($services_list as $service){
			$output .= '<option value="'.$service['id'].'">'.$service['text'].'</option>';
		}
		echo $output;
		exit;
	}
	elseif($action == 'addService'){
    	$idAccount 	= tep_db_prepare_input(Tools::getValue('id_account'));
    	$idService 	= tep_db_prepare_input(Tools::getValue('id_service'));
    	$serviceCountryCode = tep_db_prepare_input(Tools::getValue('service_country_code'));
    		
		if(empty($serviceCountryCode)){
		 	$upsMessageStack->add(UPS_ENTRY_SERVICE_COUNTRY_CODE_ERROR, 'error');
		}
		else{
			$serviceCountryCode = implode(';', $serviceCountryCode);
    		$service = new UpsService();
			$service->id_ups_account = (int) $idAccount;
			$service->service_code = $idService;
			$service->service_name = UpsService::getUpsServicesName($idService);
			$service->dest_countries = $serviceCountryCode;
			
			if(!$service->save()){
				$upsMessageStack->add(UPS_SAVE_SERVICE_ERROR, 'error');
		 		$error = true;
			}
			else{
				$upsMessageStack->add(UPS_SAVE_SERVICE_SUCCESS, 'success');
			}
		}    	
		$activeTab = 'services';
	}
	elseif($action == 'updateService'){
		
		$idUpsService 	= tep_db_prepare_input(Tools::getValue('id_ups_service'));
    	$idAccount 	= tep_db_prepare_input(Tools::getValue('id_account'));
    	$idService 	= tep_db_prepare_input(Tools::getValue('id_service'));
    	$serviceCountryCode = tep_db_prepare_input(Tools::getValue('service_country_code'));
    		
    	if(empty($serviceCountryCode)){
		 	$upsMessageStack->add(UPS_ENTRY_SERVICE_COUNTRY_CODE_ERROR, 'error');
		}
		else{
			$serviceCountryCode = implode(';', $serviceCountryCode);
    		$service = new UpsService((int) $idUpsService);
			$service->id_ups_account = (int) $idAccount;
			$service->service_code = $idService;
			$service->service_name = UpsService::getUpsServicesName($idService);
			$service->dest_countries = $serviceCountryCode;
			
			if(!$service->save()){
				$upsMessageStack->add(UPS_UPDATE_SERVICE_ERROR, 'error');
		 		$error = true;
			}
			else{
				$upsMessageStack->add(UPS_UPDATE_SERVICE_SUCCESS, 'success');
			}
		}    	
		$activeTab = 'services';
	}
	elseif($action == 'editService'){
		$idService 	= tep_db_prepare_input(Tools::getValue('id_service'));
    	$service = new UpsService((int) $idService);
		$activeTab = 'services';
	}
	elseif($action == 'deleteService'){
		$idService 	= tep_db_prepare_input(Tools::getValue('id_service'));
    	$service = new UpsService((int) $idService);
    	if(!$service->delete())
    		$upsMessageStack->add(UPS_DELETE_SERVICE_ERROR, 'error');
    	else 
			$upsMessageStack->add(UPS_DELETE_SERVICE_SUCCESS, 'success');
		$activeTab = 'services';
	}
	elseif($action == 'updateServicesSettings'){
		$declaredValue = (int) tep_db_prepare_input(Tools::getValue('declared_value'));
	    $allowAccessPointCod = (int) tep_db_prepare_input(Tools::getValue('allow_access_point_cod'));    	
	    $allowDeliverToAddressOnly = (int) tep_db_prepare_input(Tools::getValue('allow_deliver_to_address_only'));    	
	    $allowDeliveryConfirmationSignatureRequired	= (int) tep_db_prepare_input(Tools::getValue('allow_delivery_confirmation_signature_required'));    	
	    $allowDeliveryConfirmationAdultSignatureRequired = (int) tep_db_prepare_input(Tools::getValue('allow_delivery_confirmation_adult_signature_required'));    	
	    	    
	    Tools::updateConfigValue('MODULE_SHIPPING_UPS_DECLARED_VALUE', $declaredValue);
	    Tools::updateConfigValue('MODULE_SHIPPING_UPS_ALLOW_ACCESS_POINT_COD', $allowAccessPointCod);    	
	    Tools::updateConfigValue('MODULE_SHIPPING_UPS_DELIVER_TO_ADDRESSEE_ONLY', $allowDeliverToAddressOnly);
	    Tools::updateConfigValue('MODULE_SHIPPING_UPS_CONFIRMATION_SIGNATURE_REQUIRED', $allowDeliveryConfirmationSignatureRequired);
	    Tools::updateConfigValue('MODULE_SHIPPING_UPS_CONFIRMATION_ADULT_SIGNATURE_REQUIRED', $allowDeliveryConfirmationAdultSignatureRequired);
	    $upsMessageStack->add(UPS_UPDATE_SERVICE_SETTINGS_SUCCESS, 'success');
   		$activeTab = 'services';
	}