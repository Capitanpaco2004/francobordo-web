<form method="post" id="saveform-send" class="form-admin-members" action="<?php echo tep_href_link( $sUrlPage, 'action=submodules_add' ) ?>">
	<div class="oeBox column a12 row ax">
		<div class="oeWrpr">
			<div class="oeTitu"><i class="fa fa-gear"></i> <?php echo ADMIN_MEMBERS_TEXT_CONFIGURATION ?></div>
			<div class="oeCntd row ax xform xform-horizontal">
				<?php echo $sGetId !== false ? '<input type="hidden" name="id" value="' . $sGetId . '" />' : '' ?>
				<input type="submit" style="display: none;" />

				<label for="submodule_arg" class="column a02 tright"><?php echo ADMIN_MEMBERS_SUBMODULE_TABLE_HEADING_SUBMODULE_ARG_NAME ?>:</label>
				<div class="column a10">
					<input type="text" name="submodule_arg" id="submodule_arg" value="<?php echo (array_key_exists( 'admin_files_name', $aRecord ) ? $aRecord['admin_files_name'] : '') ?>"/>
					<div class="DFhelp"><?php echo ADMIN_MEMBERS_SUBMODULE_TABLE_HEADING_SUBMODULE_ARG_HELP ?></div>
				</div>

				<label for="admin_file" class="column a02 tright"><?php echo ADMIN_MEMBERS_SUBMODULE_TABLE_HEADING_SUBMODULE_ADMIN_FILE_NAME ?>:</label>
				<div class="column a10">
					<?php echo $dropdownFiles;?>
					<div class="DFhelp"><?php echo ADMIN_MEMBERS_SUBMODULE_TABLE_HEADING_SUBMODULE_ADMIN_FILE_HELP ?></div>
				</div>

			</div>
		</div>
	</div>
</form>
