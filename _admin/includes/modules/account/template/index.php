<?php use util\event; ?>
<?php echo $messageFirstTime ?>

<div class="oeBox column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fas fa-eye"></i> <?php echo TABLE_HEADING_ACCOUNT; ?></div>
		<div class="oeCntd row ax xform xform-horizontal">
			<label class="column a02 tright inline"><strong><?php echo TEXT_INFO_FIRSTNAME ?></strong></label>
			<div class="column a10">
				<p><?php echo $account['admin_firstname'] . ' ' . $account['admin_lastname'] ?></p>
			</div>
			<div class="xline xline-dashed"></div>

			<label class="column a02 tright inline"><strong><?php echo TEXT_INFO_EMAIL ?></strong></label>
			<div class="column a10">
				<p><?php echo $account['admin_email_address'] ?></p>
			</div>
			<div class="xline xline-dashed"></div>

			<label class="column a02 tright inline"><strong><?php echo TEXT_INFO_GROUP ?></strong></label>
			<div class="column a10">
				<p><?php echo $account['admin_groups_name'] ?></p>
			</div>
			<div class="xline xline-dashed"></div>

			<label class="column a02 tright inline"><strong><?php echo TEXT_INFO_LOGNUM ?></strong></label>
			<div class="column a10">
				<p><?php echo $account['admin_lognum'] ?></p>
			</div>
		</div>
	</div>

	<div class="oeWrpr" style="margin-top: 40px;">
		<div class="oeTitu"><i class="fas fa-calendar"></i> <?php echo TABLE_HEADING_DATES; ?></div>
		<div class="oeCntd row ax xform xform-horizontal">
			<label class="column a02 tright inline"><strong><?php echo TEXT_INFO_CREATED ?></strong></label>
			<div class="column a10">
				<p><?php echo $account['admin_created'] ?></p>
			</div>
			<div class="xline xline-dashed"></div>

			<label class="column a02 tright inline"><strong><?php echo TEXT_INFO_LOGDATE ?></strong></label>
			<div class="column a10">
				<p><?php echo $account['admin_logdate'] ?></p>
			</div>
			<div class="xline xline-dashed"></div>

			<label class="column a02 tright inline"><strong><?php echo TEXT_INFO_MODIFIED ?></strong></label>
			<div class="column a10">
				<p><?php echo $account['admin_modified'] ?></p>
			</div>
		</div>
	</div>

	<?php echo join('', event::getInstance()->execute('back_office_account_index_2fa')); ?>
</div>
