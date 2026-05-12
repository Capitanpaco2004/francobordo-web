<form method="post" id="saveform-send" class="form-admin-members" action="<?php echo tep_href_link( $sUrlPage, 'action=members_password&id=' . $sGetId ) ?>">
	<div class="oeBox column a12 row ax">
		<div class="oeWrpr">
			<div class="oeTitu"><i class="fas fa-eye"></i> <?php echo ADMIN_MEMBERS_TABLE_HEADING_PASSWORD; ?></div>
			<div class="oeCntd row ax xform xform-horizontal">
				<?php echo tep_draw_hidden_field('id_info', $sGetId) ?>

				<label class="column a02 tright inline"><strong><?php echo ADMIN_MEMBERS_TEXT_INFO_PASSWORD ?></strong></label>
				<div class="column a10">
					<input type="password" name="password" id="password" autocomplete="off" value=""/>
				</div>
				<div class="xline xline-dashed"></div>

				<label class="column a02 tright inline"><strong><?php echo ADMIN_MEMBERS_TEXT_INFO_PASSWORD_CONFIRM ?></strong></label>
				<div class="column a10">
					<input type="password" name="password_repeat" id="password_repeat" autocomplete="off" value=""/>
				</div>
			</div>
		</div>
	</div>
</form>
