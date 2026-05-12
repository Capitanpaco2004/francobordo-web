<form method="post" id="saveform-send" class="form-admin-s2p" action="<?php echo tep_href_link( $sUrlPage, 'action=s2p_crud' ) ?>">
	<div class="oeBox column a12 row ax">
		<div class="oeWrpr">
			<div class="oeTitu"><i class="fa fa-gear"></i> <?php echo SHIP_TO_PAY_TEXT_CONFIGURATION ?></div>
			<div class="oeCntd row ax xform xform-horizontal">
				<?php echo $sGetId !== false ? '<input type="hidden" name="id" value="' . $sGetId . '" />' : '' ?>
				<input type="submit" style="display: none;" />

				<label for="admin_ship_name" class="column a02 tright"><?php echo SHIP_TO_PAY_TABLE_HEADING_GROUP ?>:</label>
				<div class="column a10">
					<?php echo  $cShip->shipping_select('name="shp_id"', $language, $shippingMethod, $alreadySelectedShipps); ?>
					<div class="DFhelp"><?php echo SHIP_TO_PAY_TABLE_HEADING_GROUP_HELP ?></div>
				</div>
			<div class="xline xline-dashed"></div>
				<label for="admin_ship_status" class="column a02 tright"><?php echo SHIP_TO_PAY_TEXT_ENABLED_METHOD ?>:</label>
				<div class="column a10">
					<input type="checkbox" name="admin_ship_status" id="admin_ship_status" <?php echo ($statusMethod == '1' ? 'checked="checked"' : ''); ?> value="1"/><label for="admin_ship_status"><span></span></label>
					<div class="DFhelp"><?php echo SHIP_TO_PAY_TEXT_ENABLED_METHOD_HELP ?></div>
				</div>
			</div>
		</div>


		<div class="oeBox column a12 row ax">
			<?php foreach (array_keys($boxes) as $boxId): ?>
				<div class="oeWrpr groups-grid-item" style="margin-top: 40px;">
					<div class="oeTitu"><i class="fa fa-dollar-sign"></i> <?php echo $boxes[$boxId]['group']['name_formatted'] ?></div>
					<div class="oeCntd row ax xform xform-horizontal">
						<div class="column a12">
							<div class="rows ax xform sp10 groups-select-form">
								<div class="column a05">
									<input class="input-search" placeholder="Buscar" type="text" autocomplete="nope" />
									<?php echo str_replace(['OnMouseWheel="return false;"'], [''], tep_draw_pull_down_menu( 'boxes_from[]', $boxes[$boxId]['subgroups']['no_selected'], '', 'class="skip select-search from" multiple="multiple"' )); ?>
								</div>
								<div class="column a02 buttons">
									<div class="add-right hvr7"><i class="fas fa-angle-right"></i></div>
									<div class="add-left hvr7"><i class="fas fa-angle-left"></i></div>
									<div class="add-all-right hvr7"><i class="fas fa-angle-double-right"></i></div>
									<div class="add-all-left hvr7"><i class="fas fa-angle-double-left"></i></div>
								</div>
								<div class="column a05">
									<input class="input-search" placeholder="Buscar" type="text" autocomplete="nope" />
									<?php echo str_replace(['OnMouseWheel="return false;"'], [''], tep_draw_pull_down_menu( 'boxes_to[]', $boxes[$boxId]['subgroups']['selected'], 'selected', 'class="skip select-search to" multiple="multiple"' )); ?>
								</div>
							</div>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</form>
