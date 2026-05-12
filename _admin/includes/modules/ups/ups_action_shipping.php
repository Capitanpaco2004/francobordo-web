<?php
	require_once(DIR_WS_CLASSES . 'language.php');
    require_once(DIR_WS_CLASSES . 'order.php');
    require_once(DIR_WS_MODULES . 'ups/classes/UpsTools.php');
    require_once(DIR_WS_MODULES . 'ups/classes/UpsValidate.php');
    require_once(DIR_WS_MODULES . 'ups/lib/UPSShippingApi.php');
	require_once(DIR_WS_MODULES . 'ups/classes/UpsExports.php');
	require_once(DIR_WS_MODULES . 'ups/classes/UpsSelectedService.php');
    require_once(DIR_WS_MODULES . 'ups/classes/UpsIOHelper.php');
    
    global $languages_id, $upsMessageStack, $activeTab;

	if(!Tools::getConfigValue('MODULE_SHIPPING_UPS_LICENSE_AGREED'))
		tep_redirect(tep_href_link('ups_configure.php', '', 'NONSSL'));
		
	$action = Tools::getValue('action');
	$error = false;
	
	if($action == 'export_worldship'){
		$orders = UpsExports::getExportablesOrdersId();
		if(!empty($orders))
			UpsIOHelper::exportOrders($orders, UpsExports::EXPORT_WORLDSHIP, $debug);
   		die;
	}
	elseif($action == 'export_ups'){
		$orders = UpsExports::getExportablesOrdersId();
		if(!empty($orders))
			UpsIOHelper::exportOrders($orders, UpsExports::EXPORT_UPS, $debug);		
   		die;
	}
	elseif($action == 'export_pdf'){
		$orders = UpsExports::getExportablesOrdersId();
		if(!empty($orders))
			UpsIOHelper::exportOrders($orders, UpsExports::EXPORT_PDF, $debug);		
   		die;
	}