<?php use util\tools as tools; ?>

<form method="post" id="saveform-send" class="form-module" action="<?php echo tep_href_link( $sUrlPage, 'action=crud' ) ?>">
	<div class="oeBox column a12 row ax">
		<div class="oeWrpr">
			<div class="oeTitu"><i class="fa fa-gear"></i> <?php echo ($sGetId !== false ? TEXT_STATUS_EDIT : TEXT_STATUS_NEW) . ' ' . TEXT_STATUS_STATUS ?></div>
			<div class="oeCntd row ax xform xform-horizontal">

				<?php echo $sGetId !== false ? '<input type="hidden" name="id" value="' . $sGetId . '" />' : '' ?>
				<input type="submit" style="display: none;" />

				<label class="column a02 tright"><?php echo TEXT_INFO_ORDERS_STATUS_NAME ?></label>
				<div class="column a10">
					<?php echo tools::getInputLanguages( 'orders_status_name', '', $statusLanguages, '' ); ?>
				</div>
				<div class="xline xline-dashed"></div>

				<label class="column a02 tright"><?php echo TABLE_HEADING_PUBLIC_STATUS ?>:</label>
				<div class="column a10">
					<?php echo tep_draw_checkbox_field('public_flag', '1', $aRecord['public_flag'] ?? 0) . ' ' . TEXT_SET_PUBLIC_STATUS ?>
				</div>
				<div class="xline xline-dashed"></div>

				<label class="column a02 tright"><?php echo ORDERS_STATUS_TEXT_ORDER ?>:</label>
				<div class="column a10">
					<?php echo tep_draw_input_field('sort_order', $aRecord['sort_order'] ?? '') ?>
				</div>
				<div class="xline xline-dashed"></div>

				<label class="column a02 tright"><?php echo ORDERS_STATUS_TEXT_COLOR ?>:</label>
				<div class="column a10">
					<?php echo tep_draw_input_field('color', $aRecord['color'] ?? '#282A3C', '', false, 'color') ?>
				</div>
				<div class="xline xline-dashed"></div>

				<label class="column a02 tright"><?php echo TEXT_SET_DEFAULT ?>:</label>
				<div class="column a10">
					<?php echo tep_draw_checkbox_field('default', 'on', $sGetId !== false && DEFAULT_ORDERS_STATUS_ID == $sGetId) ?>
				</div>

			</div>
		</div>
	</div>
</form>
