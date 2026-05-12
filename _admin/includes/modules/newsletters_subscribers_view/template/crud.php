<?php
	// Tools
	use util\tools as tools;
?>

<div class="oeBox column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fas fa-edit"></i> <?php echo $sSubtitle; ?></div>
		<form method="post" id="saveform-send" action="<?php echo tep_href_link( $sUrlPage, 'action=crud' ); ?>" class="oeCntd row ax xform xform-horizontal">
			<?php echo $sGetId !== false ? '<input type="hidden" name="id" value="' . $sGetId . '" />' : ''; ?>
			<input type="submit" style="display: none;" />
		
			<label for="subscribers_firstname" class="column a02 tright"><?php echo NEWSLETTERS_SUBSCRIBERS_TABLE_NAME; ?>:</label>
			<div class="column a10">
				<?php echo array_key_exists( 'subscribers_firstname', $aMessageError ) ? $aMessageError['subscribers_firstname'] : ''; ?>
				<input type="text" name="subscribers_firstname" id="subscribers_firstname" value="<?php echo (array_key_exists( 'subscribers_firstname', $aRecord ) ? $aRecord['subscribers_firstname'] : ''); ?>">
				<div class="DFhelp"><?php echo NEWSLETTERS_SUBSCRIBERS_TABLE_NAME_HELP; ?></div>
			</div>
			
			<div class="xline xline-dashed"></div>
			
			<label for="subscribers_lastname" class="column a02 tright"><?php echo NEWSLETTERS_SUBSCRIBERS_TABLE_SURNAME; ?>:</label>
			<div class="column a10">
				<?php echo array_key_exists( 'subscribers_lastname', $aMessageError ) ? $aMessageError['subscribers_lastname'] : ''; ?>
				<input type="text" name="subscribers_lastname" id="subscribers_lastname" value="<?php echo (array_key_exists( 'subscribers_lastname', $aRecord ) ? $aRecord['subscribers_lastname'] : ''); ?>">
				<div class="DFhelp"><?php echo NEWSLETTERS_SUBSCRIBERS_TABLE_SURNAME_HELP; ?></div>
			</div>
			
			<div class="xline xline-dashed"></div>
			
			<label for="subscribers_email_address" class="column a02 tright"><?php echo NEWSLETTERS_SUBSCRIBERS_TABLE_EMAIL; ?>:</label>
			<div class="column a10">
				<?php echo array_key_exists( 'subscribers_email_address', $aMessageError ) ? $aMessageError['subscribers_email_address'] : ''; ?>
				<input type="text" name="subscribers_email_address" id="subscribers_email_address" value="<?php echo (array_key_exists( 'subscribers_email_address', $aRecord ) ? $aRecord['subscribers_email_address'] : ''); ?>">
				<div class="DFhelp"><?php echo NEWSLETTERS_SUBSCRIBERS_TABLE_EMAIL_HELP; ?></div>
			</div>
			
			<div class="xline xline-dashed"></div>
			
			<label for="customers_newsletter" class="column a02 tright inline"><?php echo NEWSLETTERS_SUBSCRIBERS_TABLE_ENABLED; ?>:</label>
			<div class="column a10">
				<input type="checkbox" name="customers_newsletter" id="customers_newsletter" <?php echo ((isset($aRecord['customers_newsletter']) && $aRecord['customers_newsletter'] == '1') || !isset($aRecord['customers_newsletter']) ? 'checked=""' : ''); ?> value="1"/><label for="customers_newsletter"><span></span></label>
				<div class="DFhelp"><?php echo NEWSLETTERS_SUBSCRIBERS_TABLE_ENABLED_HELP; ?></div>
			</div>
		</form>
	</div>
</div>