<?php
	
    require_once(DIR_WS_MODULES . 'ups/lib/UPSRegistrationApi.php');
      	
    $ups_accounts_list 			= UpsAccount::getAccountsList();
	$id_account 				= ($action == 'editService' && isset($service)) ? $service->id_ups_account : $ups_accounts_list[0]['id'];
    $ups_services_list 			= UpsService::getUpsServicesList($id_account);
	$id_service 				= ($action == 'editService' && isset($service)) ? $service->service_code : $ups_services_list[0]['id'];
	$destination_countries_list	= UpsService::getUpsServicesDest($id_service, $id_account);
	$services_list 				= UpsService::getServices();
	$serviceDestCountries 		= ($action == 'editService') ? explode(';', $service->dest_countries) : array();
	
	$declaredValue										= Tools::getConfigValue('MODULE_SHIPPING_UPS_DECLARED_VALUE');
	$allowAccessPointCod								= Tools::getConfigValue('MODULE_SHIPPING_UPS_ALLOW_ACCESS_POINT_COD');    	
	$allowDeliverToAddressOnly							= Tools::getConfigValue('MODULE_SHIPPING_UPS_DELIVER_TO_ADDRESSEE_ONLY');
	$allowDeliveryConfirmationSignatureRequired 		= Tools::getConfigValue('MODULE_SHIPPING_UPS_CONFIRMATION_SIGNATURE_REQUIRED');
	$allowDeliveryConfirmationAdultSignatureRequired 	= Tools::getConfigValue('MODULE_SHIPPING_UPS_CONFIRMATION_ADULT_SIGNATURE_REQUIRED');						
?>
<div class="tab-content<?php if($activeTab == 'services') echo ' active'; ?>" id="services">				
	<?php if($action != 'editService') { ?>
	<fieldset>
		<legend><?php echo UPS_ADD_SERVICE; ?></legend>
		<form action="ups_configure.php?action=addService" method="POST">
			<span class="msg"></span>
			<table border="0" width="100%" cellspacing="0" cellpadding="2">
				<tr>
					<td colspan="4"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
				</tr>
				<tr>
					<td class="main" valign="top"><?php echo UPS_ACCOUNT_NUMBER; ?></td>
					<td class="main"><?php echo tep_draw_pull_down_menu('id_account', $ups_accounts_list, $id_account, ' id="id_account" class="ups_field_large"'); ?></td>
				</tr>
				<tr>
					<td class="main" valign="top"><?php echo UPS_SERVICE; ?></td>
					<td class="main"><?php echo tep_draw_pull_down_menu('id_service', $ups_services_list, $id_service, ' id="id_service" class="ups_field_large"'); ?></td>
				</tr>
				<tr>
					<td class="main" valign="top"><?php echo UPS_DESTINATION_COUNTRIES; ?></td>
					<td class="main">
						<table id="serviceDestCountriesList" border="0" width="100%" cellspacing="0" cellpadding="2">			
						<?php foreach($destination_countries_list as $key => $country_code){ ?>
							<tr class="row<?php echo $key % 2; ?>">
								<td><?php echo $country_code; ?></td>
								<td><?php echo tep_draw_checkbox_field('service_country_code[]', $country_code, ''); ?></td>
							</tr>
						<?php } ?>
						</table>
					</td>
				</tr>
				<tr>
					<td colspan="4"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
				</tr>
				<tr>
					<td colspan="4" class="right"><input class="ups-btn" type="submit" value="<?php echo UPS_ADD_SERVICE; ?>" /></td>
				</tr>				
			</table>		
		</form>
	</fieldset>
	<?php } else { ?>
	<fieldset>
		<legend><?php echo UPS_UPDATE_SERVICE; ?></legend>
		<form action="ups_configure.php?action=updateService" method="POST">
			<span class="msg"></span>			
			<table border="0" width="100%" cellspacing="0" cellpadding="2">
				<tr>
					<td colspan="4">
						<?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?>
						<?php echo tep_draw_input_field('id_ups_service', $service->id_ups_service, '', false, 'hidden'); ?>
					</td>
				</tr>
				<tr>
					<td class="main" valign="top"><?php echo UPS_ACCOUNT_NUMBER; ?></td>
					<td class="main"><?php echo tep_draw_pull_down_menu('id_account', $ups_accounts_list, $service->id_ups_account, ' id="id_account" class="ups_field_large"'); ?></td>
				</tr>
				<tr>
					<td class="main" valign="top"><?php echo UPS_SERVICE; ?></td>
					<td class="main"><?php echo tep_draw_pull_down_menu('id_service', $ups_services_list, $service->service_code, ' id="id_service" class="ups_field_large"'); ?></td>
				</tr>
				<tr>
					<td class="main" valign="top"><?php echo UPS_DESTINATION_COUNTRIES; ?></td>
					<td class="main">
						<table id="serviceDestCountriesList" border="0" width="100%" cellspacing="0" cellpadding="2">			
						<?php 						
						foreach($destination_countries_list as $key => $country_code){ 				
						?>
							<tr class="row<?php echo $key % 2; ?>">
								<td><?php echo $country_code; ?></td>
								<td><?php echo tep_draw_checkbox_field('service_country_code[]', $country_code, (in_array($country_code, $serviceDestCountries) ? 1 : 0)); ?></td>
							</tr>
						<?php } ?>
						</table>
					</td>
				</tr>
				<tr>
					<td colspan="4"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
				</tr>
				<tr>
					<td colspan="4" class="right"><input class="ups-btn" type="submit" value="<?php echo UPS_UPDATE_SERVICE; ?>" /></td>
				</tr>				
			</table>		
		</form>
	</fieldset>
	<?php } ?>	
	<table style="margin:40px 0" border="0" width="100%" cellspacing="0" cellpadding="2">
		<tr>
			<th><?php echo UPS_SERVICE_CODE; ?></th>
			<th><?php echo UPS_SERVICE_NAME; ?></th>
			<th><?php echo UPS_ACTION; ?></th>
		</tr>
		<?php 
			if(!empty($services_list)){
				foreach($services_list as $key => $service){ ?>
			<tr class="row<?php echo $key % 2; ?>">
				<td class="center"><?php echo $service->service_code; ?></td>
				<td><?php echo $service->service_name; ?></td>
				<td class="center">
					<a href="<?php echo tep_href_link('ups_configure.php', 'action=editService&id_service='.$service->id_ups_service, 'NONSSL'); ?>"><?php echo tep_image_button('button_edit.gif', IMAGE_EDIT); ?></a>
					<a href="<?php echo tep_href_link('ups_configure.php', 'action=deleteService&id_service='.$service->id_ups_service, 'NONSSL'); ?>"><?php echo tep_image_submit('button_delete.gif', IMAGE_DELETE); ?></a>
				</td>
			</tr>
		<?php } } else { ?>	
			<tr class="row0"><td colspan="3" class="center"><?php echo UPS_SHIPPING_NO_SERVICE_REGISTERED; ?></td></tr>
		<?php } ?>			
	</table>
		
	<table border="0" width="100%" cellspacing="0" cellpadding="2">
		<tr>
			<td>
				<form action="ups_configure.php?action=updateServicesSettings" method="POST">
					<fieldset>
						<legend><?php echo UPS_GENERAL_SETTINGS; ?></legend>
						<p><?php echo tep_draw_checkbox_field('declared_value', '1', $declaredValue); ?><label for="declared_value"><?php echo UPS_DECLARED_VALUE; ?></label></p>
						<p><?php echo tep_draw_checkbox_field('allow_access_point_cod', '1', $allowAccessPointCod); ?><label for="allow_access_point_cod"><?php echo UPS_ALLOW_ACCESS_POINT_COD; ?></label></p>
						<p><?php echo tep_draw_checkbox_field('allow_deliver_to_address_only', '1', $allowDeliverToAddressOnly); ?><label for="allow_deliver_to_address_only"><?php echo UPS_ALLOW_DELIVER_TO_ADDRESS_ONLY; ?></label></p>
						<p><?php echo tep_draw_checkbox_field('allow_delivery_confirmation_signature_required', '1', $allowDeliveryConfirmationSignatureRequired); ?><label for="allow_delivery_confirmation_signature_required"><?php echo UPS_ALLOW_DELIVERY_CONFIRMATION_SIGNATURE_REQUIRED; ?></label></p>
						<p><?php echo tep_draw_checkbox_field('allow_delivery_confirmation_adult_signature_required', '1', $allowDeliveryConfirmationAdultSignatureRequired); ?><label for="allow_delivery_confirmation_adult_signature_required"><?php echo UPS_ALLOW_DELIVERY_CONFIRMATION_ADULT_SIGNATURE_REQUIRED; ?></label></p>
						<p class="right"><input class="ups-btn" type="submit" value="<?php echo UPS_SUBMIT_SETTINGS; ?>" /></p>							
					</fieldset>
				</form>
			</td>
		</tr>
	</table>
</div>