<?php
	require_once(DIR_WS_CLASSES . 'language.php');
    require_once(DIR_WS_MODULES . 'ups/classes/UpsTools.php');
    require_once(DIR_WS_MODULES . 'ups/classes/UpsValidate.php');
    
    global $languages_id, $upsMessageStack, $activeTab;

	if(!Tools::getConfigValue('MODULE_SHIPPING_UPS_LICENSE_AGREED'))
		tep_redirect(tep_href_link('ups_configure.php', '', 'NONSSL'));
		
	$action = Tools::getValue('action');
	$error = false;
	
	if($action == 'updateTrackingSettings'){
		
		$orderStateTracked 			= tep_db_prepare_input(Tools::getValue('order_state_tracked'));			
		$upsDeliveredOrderStatus 	= (int) tep_db_prepare_input(Tools::getValue('ups_delivered_order_status'));
		$upsInTransitOrderStatus	= (int) tep_db_prepare_input(Tools::getValue('ups_in_transit_order_status'));
		$trackingBy 				= (int) tep_db_prepare_input(Tools::getValue('tracking_by'));
		$cronLink 					= tep_db_prepare_input(Tools::getValue('cron_link'));
		if(empty($orderStateTracked)){
		 	$upsMessageStack->add(UPS_ENTRY_TRACKING_ORDER_STATES_ERROR, 'error');
		 	$error = true;
		}
		if($trackingBy && (empty($cronLink) || !Validate::isUrl($cronLink))){
			$error = true;
      		$upsMessageStack->add(UPS_ENTRY_TRACKING_CRON_CHECK_ERROR, 'error');
		}
		if(!$error){
			$orderStateTracked = implode(';', $orderStateTracked);
			Tools::updateConfigValue('MODULE_SHIPPING_UPS_ORDER_STATUS_TRACKED', $orderStateTracked);
		    Tools::updateConfigValue('MODULE_SHIPPING_UPS_DELIVERED_ORDER_STATUS', $upsDeliveredOrderStatus);    	
		    Tools::updateConfigValue('MODULE_SHIPPING_UPS_IN_TRANSIT_ORDER_STATUS', $upsInTransitOrderStatus);
		    Tools::updateConfigValue('MODULE_SHIPPING_UPS_TRACKING_BY', $trackingBy);
		    Tools::updateConfigValue('MODULE_SHIPPING_UPS_CRON_LINK', $cronLink);
	    	$upsMessageStack->add(UPS_UPDATE_TRACKING_SETTINGS_SUCCESS, 'success');
		}
   		$activeTab = 'tracking';
	}