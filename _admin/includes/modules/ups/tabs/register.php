<?php

  	
?>
<div class="ups_content" id="company_settings">	
	<form action="ups_configure.php?action=register" method="POST">							
		<fieldset>
			<legend><?php echo UPS_REGISTER_LABEL; ?></legend>							
			<table border="0" width="100%" cellspacing="0" cellpadding="2">
				<tr>
					<td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
				</tr>
				<tr>
					<td class="main" valign="top"><?php echo UPS_COMPANY_NAME; ?></td>
					<td class="main"><?php echo tep_draw_input_field('company_name', $companyName, 'class="ups_field_xlarge"', true, 'text'); ?></td>
				</tr>
				<tr>
					<td class="main" valign="top"><?php echo UPS_ADDRESS; ?></td>
					<td class="main"><?php echo tep_draw_input_field('address', $addressLine1, 'class="ups_field_xlarge"', true, 'text'); ?></td>
				</tr>
				<tr>
					<td class="main" valign="top"><?php echo UPS_ADDRESS2; ?></td>
					<td class="main"><?php echo tep_draw_input_field('address2', $addressLine2, 'class="ups_field_xlarge"', false, 'text'); ?></td>
				</tr>
				<tr>
					<td class="main" valign="top"><?php echo UPS_ADDRESS3; ?></td>
					<td class="main"><?php echo tep_draw_input_field('address3', $addressLine3, 'class="ups_field_xlarge"', false, 'text'); ?></td>
				</tr>
				<tr>
					<td class="main" valign="top"><?php echo UPS_CITY; ?></td>
					<td class="main"><?php echo tep_draw_input_field('city', $city, 'class="ups_field_xlarge"', true, 'text'); ?></td>
				</tr>
				<tr>
					<td class="main" valign="top"><?php echo UPS_POSTAL; ?></td>
					<td class="main"><?php echo tep_draw_input_field('postal', $postalCode, 'class="ups_field_xlarge"', true, 'text'); ?></td>
				</tr>
				<?php if($licenseAgreementCountryCode != 'FR') { ?>
				<tr>
					<td class="main" valign="top"><?php echo UPS_STATE; ?></td>
					<td class="main"><?php echo tep_draw_input_field('state', $state, 'class="ups_field_xlarge"', false, 'text'); ?></td>
				</tr>
				<?php } ?>
				<tr>
					<td class="main" valign="top"><?php echo UPS_COUNTRY; ?></td>
					<td class="main"><?php echo tep_draw_pull_down_menu('country', $country_array, $countryCode, 'class="ups_field_xlarge"', true); ?></td>
				</tr>
				<tr>
					<td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
				</tr>
				<tr>
					<td class="main" valign="top"><?php echo UPS_CONTACT_NAME; ?></td>
					<td class="main"><?php echo tep_draw_input_field('contact_name', $contactName, 'class="ups_field_xlarge"', true, 'text'); ?></td>
				</tr>
				<tr>
					<td class="main" valign="top"><?php echo UPS_CONTACT_TITLE; ?></td>
					<td class="main"><?php echo tep_draw_input_field('contact_title', $contactTitle, 'class="ups_field_xlarge"', true, 'text'); ?></td>
				</tr>
				<tr>
					<td class="main" valign="top"><?php echo UPS_CONTACT_EMAIL; ?></td>
					<td class="main"><?php echo tep_draw_input_field('contact_email', $contactEmail, 'class="ups_field_xlarge"', true, 'text'); ?></td>
				</tr>
				<tr>
					<td class="main" valign="top"><?php echo UPS_CONTACT_PHONE; ?></td>
					<td class="main"><?php echo tep_draw_input_field('contact_phone', $contactPhone, 'class="ups_field_xlarge"', true, 'text'); ?></td>
				</tr>
				<tr>
					<td class="main" valign="top"><?php echo UPS_COMPANY_URL; ?></td>
					<td class="main"><?php echo tep_draw_input_field('company_url', $companyUrl, 'class="ups_field_xlarge"', true, 'text'); ?></td>
				</tr>
				<tr>
					<td class="main" valign="top"><?php echo UPS_LICENSE_AGREEMENT; ?></td>
					<td class="main">
						<?php echo tep_draw_textarea_field('license_agreement', '', '50', '10', $licenseAgreementText, 'class="ups_field_xlarge" id="license_agreement" readonly="readonly"'); ?>
						<span class="print_license"><a target="_blank" href="ups_configure.php?action=printLicense"><img src="includes/modules/ups/assets/img/print_icon.gif" alt="<?php echo UPS_PRINT; ?>" width="16" height="16" /></a></span>
					</td>
				</tr>
				<tr>
					<td class="main" valign="top"></td>
					<td class="main" valign="top"><?php echo tep_draw_checkbox_field('agree_license_agreement', '1', ''); ?><label for="declared_value"><?php echo UPS_LICENSE_AGREEMENT_INFO; ?></label></td>
				</tr>						
				<tr>
					<td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
				</tr>
				<tr>
					<td colspan="2"><center><input id="submit_agree" class="ups-btn" type="submit" value="<?php echo UPS_AGREE_AND_SUBMIT; ?>" /></center></td>
				</tr>
			</table>
		</fieldset>
	</form>
</div>
<script type="text/javascript">
	var UPS_LICENSE_AGREEMENT_ERROR = '<?php echo UPS_LICENSE_AGREEMENT_ERROR; ?>';
</script>