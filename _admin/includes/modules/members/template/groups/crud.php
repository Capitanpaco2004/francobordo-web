<form method="post" id="saveform-send" class="form-admin-members" action="<?php echo tep_href_link( $sUrlPage, 'action=groups_crud' ) ?>">
	<div class="oeBox column a12 row ax">
		<div class="oeWrpr">
			<div class="oeTitu"><i class="fa fa-gear"></i> <?php echo ADMIN_MEMBERS_TEXT_CONFIGURATION ?></div>
			<div class="oeCntd row ax xform xform-horizontal">
				<?php echo $sGetId !== false ? '<input type="hidden" name="id" value="' . $sGetId . '" />' : '' ?>
				<input type="submit" style="display: none;" />

				<label for="admin_groups_name" class="column a02 tright"><?php echo ADMIN_MEMBERS_TABLE_HEADING_GROUP ?>:</label>
				<div class="column a10">
					<input type="text" name="admin_groups_name" id="admin_groups_name" value="<?php echo (array_key_exists( 'admin_groups_name', $aRecord ) ? $aRecord['admin_groups_name'] : '') ?>"/>
					<div class="DFhelp"><?php echo ADMIN_MEMBERS_TABLE_HEADING_GROUP_HELP ?></div>
				</div>
			</div>
		</div>

		<div class="groups-grid-selects">
			<?php foreach (array_keys($boxes) as $boxId): ?>
				<div class="oeWrpr groups-grid-item" style="margin-top: 40px;">
					<div class="oeTitu"><i class="fa fa-lock"></i> <?php echo $boxes[$boxId]['group']['name_formatted'] ?></div>
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
