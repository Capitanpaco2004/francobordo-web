<form method="post" id="saveform-send" class="form-customers-note-status" action="<?php echo tep_href_link( $sUrlPage, 'action=crud' ) ?>">
	<div class="oeBox column a12 row ax">
		<div class="oeWrpr">
			<div class="oeTitu"><i class="fa fa-gear"></i> <?php echo ($sGetId !== false ? CUSTOMERS_NOTES_STATUS_EDIT : CUSTOMERS_NOTES_STATUS_NEW) . ' ' . CUSTOMERS_NOTES_STATUS_STATUS ?></div>
			<div class="oeCntd row ax xform xform-horizontal">

				<?php echo $sGetId !== false ? '<input type="hidden" name="id" value="' . $sGetId . '" />' : '' ?>
				<input type="submit" style="display: none;" />

				<label class="column a02 tright"><?php echo CUSTOMERS_NOTES_STATUS_TABLE_STATUS ?>:</label>
				<div class="column a10">
					<input type="text" name="customers_notes_status" id="customers_notes_status" value="<?php echo (array_key_exists( 'customers_notes_status', $aRecord ) ? $aRecord['customers_notes_status'] : '') ?>"/>
				</div>
				<div class="xline xline-dashed"></div>

				<label class="column a02 tright"><?php echo CUSTOMERS_NOTES_STATUS_TABLE_COLOR ?>:</label>
				<div class="column a10">
					<input type="color" name="customers_notes_color" id="customers_notes_color" value="<?php echo (array_key_exists( 'customers_notes_color', $aRecord ) ? $aRecord['customers_notes_color'] : '') ?>"/>
				</div>

			</div>
		</div>
	</div>
</form>
