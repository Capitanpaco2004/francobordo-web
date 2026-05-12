<div class="oeBox column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fas fa-<?php echo ($sGetId != false ? 'edit' : 'plus'); ?>"></i> <?php echo $sSubtitle; ?></div>
		<form method="post" id="saveform-send" action="<?php echo tep_href_link( $sUrlPage, 'action=group_crud' ); ?>" class="oeCntd row ax xform xform-horizontal">
			<?php echo $sGetId !== false ? '<input type="hidden" name="id" value="' . $sGetId . '" />' : ''; ?>
			<input type="submit" style="display: none;" />

			<label for="configuration_group_title" class="column a02 tright"><?php echo CONFIGURATION_TABLE_CONFIGURATION; ?>:</label>
			<div class="column a10">
				<?php echo array_key_exists( 'configuration_group_title', $aMessageError ) ? $aMessageError['configuration_group_title'] : ''; ?>
				<input type="text" name="configuration_group_title" id="configuration_group_title" value="<?php echo $aRecord['configuration_group_title']; ?>">
				<div class="DFhelp"><?php echo CONFIGURATION_TABLE_CONFIGURATION_HELP; ?></div>
			</div>

			<div class="xline xline-dashed"></div>

			<label for="configuration_group_description" class="column a02 tright"><?php echo CONFIGURATION_TABLE_DESCRIPTION; ?>:</label>
			<div class="column a10">
				<?php echo array_key_exists( 'configuration_group_description', $aMessageError ) ? $aMessageError['configuration_group_description'] : ''; ?>
				<input type="text" name="configuration_group_description" id="configuration_group_description" value="<?php echo $aRecord['configuration_group_description']; ?>">
				<div class="DFhelp"><?php echo CONFIGURATION_TABLE_DESCRIPTION_HELP; ?></div>
			</div>

			<div class="xline xline-dashed"></div>

			<label for="sort_order" class="column a02 tright"><?php echo CONFIGURATION_TABLE_ORDER; ?>:</label>
			<div class="column a10">
				<?php echo array_key_exists( 'sort_order', $aMessageError ) ? $aMessageError['sort_order'] : ''; ?>
				<input type="text" name="sort_order" id="sort_order" value="<?php echo $aRecord['sort_order']; ?>">
				<div class="DFhelp"><?php echo CONFIGURATION_TABLE_ORDER_HELP; ?></div>
			</div>
		</form>
	</div>
</div>
