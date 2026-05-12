<?php 
	$exportableOrderStateId = (int) Tools::getConfigValue('MODULE_SHIPPING_UPS_EXPORTABLE_ORDER_STATE');	
	$locationSearchRange	= (int) Tools::getConfigValue('MODULE_SHIPPING_UPS_DEFAULT_LOCATION_SEARCH_RANGE');
    $orders_states_list = tep_get_orders_status();
	$accounts_list = UpsAccount::getAccounts();
	$pickup_types = UpsAccount::getUPSPickupTypesList();
?>
<div class="tab-content<?php if($activeTab == 'account') echo ' active'; ?>" id="account">	
	<?php
	if ($messageStack->size > 0) echo $messageStack->output();
	if(count($accounts_list < 99)){
	?>			
	<form action="ups_configure.php?action=createAccount" method="POST">
		<table border="0" width="100%" cellspacing="0" cellpadding="2">
			<tr>
				<td colspan="4"><p><span><?php echo UPS_REGISTER; ?></span><a href="http://www.ups.com" target="_blank">www.ups.com</a></p></td>
			</tr>
			<tr>
				<td colspan="4"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
			</tr>
			<tr>
				<td colspan="2"></td>
				<td colspan="2"><?php echo UPS_INVOICE_LABEL; ?></td>
			</tr>
			<tr>
				<td class="label" valign="top"><?php echo UPS_ACCOUNT_NAME; ?></td>
				<td class="main"><?php echo tep_draw_input_field('account_name', $accountName, '', true, 'text'); ?></td>
				<td class=label valign="top"><?php echo UPS_INVOICE_NUMBER; ?></td>
				<td class="main"><?php echo tep_draw_input_field('invoice_number', $invoiceNumber, '', false, 'text'); ?></td>
			</tr>
			<tr>
				<td class="label" valign="top"><?php echo UPS_ACCOUNT_NUMBER; ?></td>
				<td class="main"><?php echo tep_draw_input_field('account_number', $accountNumber, '', true, 'text'); ?></td>
				<td class="label" valign="top"><?php echo UPS_INVOICE_DATE; ?></td>				
				<td class="main"><?php echo tep_draw_input_field('invoice_date', $invoiceDate, '', false, 'text'); ?></td>
			</tr>
			<tr>
				<td class="label" valign="top"><?php echo UPS_POSTAL; ?></td>
				<td class="main"><?php echo tep_draw_input_field('account_postal', $accountPostal, '', true, 'text'); ?></td>
				<td class="label" valign="top"><?php echo UPS_CURRENCY_CODE; ?></td>			
				<td class="main"><?php echo tep_draw_input_field('invoice_currency_code', $invoiceCurrencyCode, '', false, 'text'); ?></td>
			</tr>
			<tr>
				<td class="label" valign="top"><?php echo UPS_COUNTRY; ?></td>
				<td class="main"><?php echo tep_draw_pull_down_menu('account_country', $country_array, $accountCountry); ?></td>
				<td class="label" valign="top"><?php echo UPS_INVOICE_AMOUNT; ?></td>		
				<td class="main"><?php echo tep_draw_input_field('invoice_amount', $invoiceAmount, '', false, 'text'); ?></td>
			</tr>
			<tr>
				<td colspan="2"></td>
				<td class="label" valign="top"><?php echo UPS_CONTROL_ID; ?></td>		
				<td class="main"><?php echo tep_draw_input_field('invoice_control_id', $invoiceControlId, '', false, 'text'); ?></td>
			</tr>
			<tr>
				<td colspan="2" class="right" valign="top"><input class="ups-btn" type="submit" value="<?php echo UPS_ADD_ACCOUNT; ?>" /></td>									
				<td colspan="2"></td>
			</tr>
			<tr>
				<td colspan="4"><?php echo tep_draw_separator('pixel_trans.gif', '1', '20'); ?></td>
			</tr>
		</table>
	</form>
	<?php } ?>
	<table border="0" width="100%" cellspacing="0" cellpadding="2">
		<?php if (!empty($accounts_list)) { ?>								
		<tr>
			<td colspan="4">
				<ul class="account-nav-tabs">
					<?php 
					foreach($accounts_list as $key => $account)
						echo '<li><a href="#account'.($key+1).'"'.(($key==0) ? ' class="active"' : '').'>'.$account->account_name.'</a></li>';
					?>								
				</ul>
				<?php foreach($accounts_list as $key => $account){ ?>
				<div class="account-tab-content<?php if(!$key) echo ' active'; ?>" id="account<?php echo ($key+1);?>">
					<form action="ups_configure.php?action=updateAccount" method="POST">
						<table border="0" width="100%" cellspacing="0" cellpadding="2">
							<tr>
								<td colspan="2"><?php echo UPS_SHIPPER_INFORMATION; ?></td>
								<td colspan="2"><?php echo tep_draw_input_field('id_ups_account', $account->id_ups_account, '', false, 'hidden'); ?></td>
							</tr>
							<tr>
								<td colspan="4"><?php echo tep_draw_separator('pixel_trans.gif', '1', '20'); ?></td>
							</tr>
							<tr>
								<td class="label" valign="top"><?php echo UPS_SHIPPER_NAME; ?></td>
								<td class="main"><?php echo tep_draw_input_field('shipper_name_'.$account->id_ups_account, $account->shipper_name, '', true, 'text'); ?></td>
								<td class="label" valign="top"><?php echo UPS_ADDRESS_LINE2; ?></td>
								<td class="main"><?php echo tep_draw_input_field('address_line_2_'.$account->id_ups_account, $account->address_line_2, '', false, 'text'); ?></td>
							</tr>
							<tr>
								<td class="label" valign="top"><?php echo UPS_SHIPPER_ATTENTION_NAME; ?></td>
								<td class="main"><?php echo tep_draw_input_field('shipper_attention_name_'.$account->id_ups_account, $account->shipper_attention_name, '', true, 'text'); ?></td>
								<td class="label" valign="top"><?php echo UPS_SHOP_COUNTRY; ?></td>
								<td class="main">
									<?php echo tep_draw_pull_down_menu('shop_country_'.$account->id_ups_account, $country_array, Tools::getCountryCode($account->id_country), 'disabled="true"'); ?>
									<?php echo tep_draw_input_field('shop_id_country_'.$account->id_ups_account, $account->id_country, '', false, 'hidden'); ?>
								</td>						
							</tr>
							<tr>
								<td class="label" valign="top"><?php echo UPS_DNI_NUMBER; ?></td>
								<td class="main"><?php echo tep_draw_input_field('dni_number_'.$account->id_ups_account, $account->dni_number, '', false, 'text'); ?></td>				
								<?php if( Tools::getCountryCode($account->id_country) != 'FR') { ?>
								<td class="label" valign="top"><?php echo UPS_SHOP_STATE; ?></td>
								<td class="main"><?php echo tep_draw_input_field('shop_state_'.$account->id_ups_account, $account->state, '', false, 'text'); ?></td>
								<?php } else { ?>
								<td colspan="2"></td>
								<?php } ?>					
							</tr>
							<tr>
								<td class="label" valign="top"><?php echo UPS_PICKUP_TYPE; ?></td>
								<td class="main"><?php echo tep_draw_pull_down_menu('pickup_type_'.$account->id_ups_account, $pickup_types, $account->pickup_types); ?></td>
								<td class="label" valign="top"><?php echo UPS_SHOP_CITY; ?></td>
								<td class="main"><?php echo tep_draw_input_field('shop_city_'.$account->id_ups_account, $account->city, '', true, 'text'); ?></td>		
							</tr>
							<tr>							
								<td class="label" valign="top"><?php echo UPS_PHONE_NUMBER; ?></td>
								<td class="main"><?php echo tep_draw_input_field('phone_number_'.$account->id_ups_account, $account->phone_number, '', true, 'text'); ?></td>
								<td class="label" valign="top"><?php echo UPS_SHOP_POSTAL; ?></td>
								<td class="main"><?php echo tep_draw_input_field('shop_postal_'.$account->id_ups_account, $account->postal_code, '', true, 'text'); ?></td>																			
							</tr>
							<tr>							
								<td class="label" valign="top"><?php echo UPS_ADDRESS_LINE1; ?></td>
								<td class="main"><?php echo tep_draw_input_field('address_line_1_'.$account->id_ups_account, $account->address_line_1, '', true, 'text'); ?></td>
								<td colspan="2"></td>																								
							</tr>
							<tr>
								<td colspan="4"><?php echo tep_draw_separator('pixel_trans.gif', '1', '20'); ?></td>
							</tr>
							<tr>
								<td class="main" valign="top" colspan="2">
									<p><?php echo tep_draw_checkbox_field('is_ups_ape_'.$account->id_ups_account, '1', $account->is_ups_ape); ?> <label for="is_ups_ape_<?php echo $account->id_ups_account; ?>"><?php echo UPS_ACCESS_POINT_ECO; ?></label></p>
								</td>
								<td class="right" valign="top" colspan="2"><input class="ups-btn" type="submit" value="<?php echo UPS_UPDATE_ACCOUNT; ?>" /></td>														
							</tr>
						</table>
					</form>											
				</div>
				<?php } ?>
			</td>
		</tr>
		<?php } ?>	
		<tr>
			<td colspan="4"><?php echo tep_draw_separator('pixel_trans.gif', '1', '20'); ?></td>
		</tr>
		<tr>
			<td colspan="4">
				<fieldset>
					<legend><?php echo UPS_GENERAL_SETTINGS; ?></legend>
					<form action="ups_configure.php?action=updateAccountsSettings" method="POST">
						<table border="0" width="100%" cellspacing="0" cellpadding="2">	
							<tr>
								<td valign="top"><?php echo UPS_EXPORTABLE_ORDER_STATE; ?></td>
								<td><?php echo tep_draw_pull_down_menu('exportable_order_state', $orders_states_list, $exportableOrderStateId); ?></td>	
							</tr>
							<tr>
								<td valign="top"><?php echo UPS_DEFAULT_LOCATION_SEARCH_RANGE; ?></td>
								<td><?php echo tep_draw_input_field('search_range', $locationSearchRange, ' size="3" maxlength="3"', false, 'text'); ?> km</td>	
							</tr>
							<tr>
								<td colspan="2" class="right"><input class="ups-btn" type="submit" value="<?php echo UPS_SUBMIT_SETTINGS; ?>" /></td>
							</tr>
						</table>									
					</form>
				</fieldset>
			</td>
		</tr>
	</table>
</div>