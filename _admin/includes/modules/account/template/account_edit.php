<?php
	echo $messageStack->show(['text' => TEXT_WRNG_PASSWORD, 'class' => 'info']);
?>

<form method="post" id="saveform-send" action="<?php echo tep_href_link( $sUrlPage, 'action=account_edit' ) ?>">
	<div class="oeBox column a12 row ax">
		<div class="oeWrpr">
			<div class="oeTitu"><i class="fas fa-eye"></i> <?php echo TABLE_HEADING_ACCOUNT; ?></div>
			<div class="oeCntd row ax xform xform-horizontal">
				<?php echo tep_draw_hidden_field('id_info', $account['admin_id']) ?>
				<?php echo tep_draw_hidden_field('group_id', $account['admin_groups_id']) ?>

				<label class="column a02 tright inline"><strong><?php echo TEXT_INFO_FIRSTNAME ?></strong></label>
				<div class="column a10">
					<input type="text" name="account_firstname" id="account_firstname" value="<?php echo $account['admin_firstname'] ?>"/>
				</div>
				<div class="xline xline-dashed"></div>

				<label class="column a02 tright inline"><strong><?php echo TEXT_INFO_LASTNAME ?></strong></label>
				<div class="column a10">
					<input type="text" name="account_lastname" id="account_lastname" value="<?php echo $account['admin_lastname'] ?>"/>
				</div>
				<div class="xline xline-dashed"></div>

				<label class="column a02 tright inline"><strong><?php echo TEXT_INFO_EMAIL ?></strong></label>
				<div class="column a10">
					<input type="text" name="account_email" id="account_email" value="<?php echo $account['admin_email_address'] ?>"/>
				</div>
			</div>
		</div>

		<div class="oeWrpr" style="margin-top: 40px;">
			<div class="oeTitu"><i class="fas fa-eye"></i> <?php echo TABLE_HEADING_PASSWORD; ?></div>
			<div class="oeCntd row ax xform xform-horizontal">
				<label class="column a02 tright inline"><strong><?php echo TEXT_INFO_PASSWORD ?></strong></label>
				<div class="column a10">
					<input type="password" name="password" id="password" autocomplete="off" value=""/>
				</div>
				<div class="xline xline-dashed"></div>

				<label class="column a02 tright inline"><strong><?php echo TEXT_INFO_PASSWORD_CONFIRM ?></strong></label>
				<div class="column a10">
					<input type="password" name="password_repeat" id="password_repeat" autocomplete="off" value=""/>
				</div>
			</div>
		</div>
	</div>
</form>
