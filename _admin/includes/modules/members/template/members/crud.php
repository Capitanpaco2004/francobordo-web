<form method="post" id="saveform-send" class="form-admin-members" action="<?php echo tep_href_link( $sUrlPage, 'action=members_crud' ) ?>">
	<div class="oeBox column a12 row ax">
		<div class="oeWrpr">
			<div class="oeTitu"><i class="fa fa-gear"></i> <?php echo ADMIN_MEMBERS_TEXT_CONFIGURATION ?></div>
			<div class="oeCntd row ax xform xform-horizontal">

				<?php echo $sGetId !== false ? '<input type="hidden" name="id" value="' . $sGetId . '" />' : '' ?>
				<input type="submit" style="display: none;" />

				<label for="admin_firstname" class="column a02 tright"><?php echo ADMIN_MEMBERS_TABLE_HEADING_FIRSTNAME ?>:</label>
				<div class="column a10">
					<input type="text" name="admin_firstname" id="admin_firstname" value="<?php echo (array_key_exists( 'admin_firstname', $aRecord ) ? $aRecord['admin_firstname'] : '') ?>"/>
					<div class="DFhelp"><?php echo ADMIN_MEMBERS_TABLE_HEADING_FIRSTNAME_HELP ?></div>
				</div>
				<div class="xline xline-dashed"></div>

				<label for="admin_lastname" class="column a02 tright"><?php echo ADMIN_MEMBERS_TABLE_HEADING_LASTNAME ?>:</label>
				<div class="column a10">
					<input type="text" name="admin_lastname" id="admin_lastname" value="<?php echo (array_key_exists( 'admin_lastname', $aRecord ) ? $aRecord['admin_lastname'] : '') ?>"/>
					<div class="DFhelp"><?php echo ADMIN_MEMBERS_TABLE_HEADING_LASTNAME_HELP ?></div>
				</div>
				<div class="xline xline-dashed"></div>

				<label for="admin_email_address" class="column a02 tright"><?php echo ADMIN_MEMBERS_TABLE_HEADING_EMAIL ?>:</label>
				<div class="column a10">
					<input type="text" name="admin_email_address" id="admin_email_address" value="<?php echo (array_key_exists( 'admin_email_address', $aRecord ) ? $aRecord['admin_email_address'] : '') ?>"/>
					<div class="DFhelp"><?php echo ADMIN_MEMBERS_TABLE_HEADING_EMAIL_HELP ?></div>
				</div>
				<div class="xline xline-dashed"></div>

				<label for="admin_groups_id" class="column a02 tright"><?php echo ADMIN_MEMBERS_TABLE_HEADING_GROUPS ?>:</label>
				<div class="column a10">
					<?php echo tep_draw_pull_down_menu( 'admin_groups_id', $groups_array, (array_key_exists( 'admin_groups_id', $aRecord ) ? $aRecord['admin_groups_id'] : '') ) ?>
					<div class="DFhelp"><?php echo ADMIN_MEMBERS_TABLE_HEADING_GROUPS_HELP ?></div>
				</div>
			</div>
		</div>

		<div class="oeWrpr" style="margin-top: 40px;">
			<div class="oeTitu"><i class="fa fa-lock"></i> <?php echo ADMIN_MEMBERS_TEXT_PERMISSIONS?></div>
			<div class="oeCntd row ax xform xform-horizontal">
				<label for="admin_right_access" class="column a02 tright"><?php echo ADMIN_MEMBERS_RIGHTS_AVAILABLES ?>:</label>
				<div class="column a10">
					<div class="rows ax xform sp10 groups-select-form">
						<div class="column a05">
							<input class="input-search" placeholder="Buscar" type="text" autocomplete="nope" />
							<?php echo str_replace(['OnMouseWheel="return false;"'], [''], tep_draw_pull_down_menu( 'rights_permissions_from[]', $permissions_access['no_selected'], '', 'class="skip select-search from" multiple="multiple"' )); ?>
						</div>
						<div class="column a02 buttons">
							<div class="add-right hvr7"><i class="fas fa-angle-right"></i></div>
							<div class="add-left hvr7"><i class="fas fa-angle-left"></i></div>
							<div class="add-all-right hvr7"><i class="fas fa-angle-double-right"></i></div>
							<div class="add-all-left hvr7"><i class="fas fa-angle-double-left"></i></div>
						</div>
						<div class="column a05">
							<input class="input-search" placeholder="Buscar" type="text" autocomplete="nope" />
							<?php echo str_replace(['OnMouseWheel="return false;"'], [''], tep_draw_pull_down_menu( 'rights_permissions_to[]', $permissions_access['selected'], 'disabled', 'class="skip select-search to" multiple="multiple"' )); ?>
						</div>
					</div>
				</div>

				<div class="xline xline-dashed"></div><br/><br/>

				<label for="admin_permissions" class="column a02 tright"><?php echo ADMIN_MEMBERS_CATEGORIES_AVAILABLES ?>:</label>
				<div class="column a10">
					<div class="rows ax xform sp10 groups-select-form">
						<div class="column a05">
							<input class="input-search" placeholder="Buscar" type="text" autocomplete="nope" />
							<?php echo str_replace(['OnMouseWheel="return false;"'], [''], tep_draw_pull_down_menu( 'cat_permissions_from[]', $permissions_categories['no_selected'], '', 'class="skip select-search from" multiple="multiple"' )); ?>
						</div>
						<div class="column a02 buttons">
							<div class="add-right hvr7"><i class="fas fa-angle-right"></i></div>
							<div class="add-left hvr7"><i class="fas fa-angle-left"></i></div>
							<div class="add-all-right hvr7"><i class="fas fa-angle-double-right"></i></div>
							<div class="add-all-left hvr7"><i class="fas fa-angle-double-left"></i></div>
						</div>
						<div class="column a05">
							<input class="input-search" placeholder="Buscar" type="text" autocomplete="nope" />
							<?php echo str_replace(['OnMouseWheel="return false;"'], [''], tep_draw_pull_down_menu( 'cat_permissions_to[]', $permissions_categories['selected'], 'disabled', 'class="skip select-search to" multiple="multiple"' )); ?>
						</div>
					</div>
				</div>

			</div>
		</div>

	</div>

</form>
