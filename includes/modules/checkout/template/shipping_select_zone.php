 <form name="checkout_select_zone" action="<?php echo tep_href_link(FILENAME_CHECKOUT_SELECT_ZONE); ?>" method="post">
    <div class="rows dx xform sp10 amiddle">
        <div class="col d12 m12">
            <?php echo $messageStack->show('message_error'); ?>
            <?php echo $messageStack->show(array('text' => CHECKOUT_SELECT_ZONE_INFO, 'class' => 'warning')); ?></p>
        </div>

		<label class="col d03 m12 tright" for="postcode"><span class="trojo">*</span><?php echo ENTRY_POST_CODE; ?></label>
		<div class="col d09 m12">
			<?php echo tep_draw_input_field('postcode', $aZoneID['entry_postcode'], 'data-ajax-postcode required="" type="text" data-parsley-minlength="' . ENTRY_POSTCODE_MIN_LENGTH . '" data-parsley-trigger="change"'); ?>
			<?php echo (defined('ENTRY_POST_CODE_TEXT') && ENTRY_POST_CODE_TEXT != '*' && ENTRY_POST_CODE_TEXT != '') ? '<div class="DFhelp">' . ENTRY_POST_CODE_TEXT . '</div>' : ''; ?>
		</div>

		<label class="col d03 m12 tright" for="country"><span class="trojo">*</span> <?php echo ENTRY_COUNTRY; ?></label>
		<div class="col d09 m12">
			<div id="ajax-country" class="column"><?php echo getCountries(array('country' => $customer_country_id)); ?></div>
			<?php echo (defined('ENTRY_COUNTRY_TEXT') && ENTRY_COUNTRY_TEXT != '*' && ENTRY_COUNTRY_TEXT != '') ? '<div class="DFhelp">' . ENTRY_COUNTRY_TEXT . '</div>' : ''; ?>
		</div>

		<?php if( ACCOUNT_STATE == 'true' ): ?>
			<label class="col d03 m12 tright" for="city"><span class="trojo">*</span> <?php echo ENTRY_STATE; ?></label>
			<div class="col d09 m12" id="states">
				<div id="ajax-zone" class="column"><?php echo getZonesByCountry(array('country' => $customer_country_id, 'zone' => ($aZoneID['entry_zone_id'] == 0 ? $aDato['entry_state'] : $aZoneID['entry_zone_id']))); ?></div>
				<?php echo (defined('ENTRY_STATE_TEXT') && ENTRY_STATE_TEXT != '*' && ENTRY_STATE_TEXT != '') ? '<div class="DFhelp">' . ENTRY_STATE_TEXT . '</div>' : ''; ?>
			</div>
		<?php endif; ?>

		<label class="col d03 m12 tright" for="city"><span class="trojo">*</span> <?php echo ENTRY_CITY; ?></label>
		<div class="col d09 m12">
			<div id="ajax-city" class="column"><?php echo getCitiesByCountryByZone(array('country' => $customer_country_id, 'zone' => ($aZoneID['entry_zone_id'] == 0 ? $aDato['entry_state'] : $aZoneID['entry_zone_id']))); ?></div>
			<?php echo (defined('ENTRY_CITY_TEXT') && ENTRY_CITY_TEXT != '*' && ENTRY_CITY_TEXT != '') ? '<div class="DFhelp">' . ENTRY_CITY_TEXT . '</div>' : ''; ?>
		</div>

        <div class="col d12 tright">
        	<input class="xbutton verde tblanco hv9" id="TheSubmitButton" type="submit" value="<?php echo IMAGE_BUTTON_CONTINUE; ?>" />
        </div>
    </div>
</form>
