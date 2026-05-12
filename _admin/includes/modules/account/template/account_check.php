<form method="post" id="saveform-send" action="<?php echo tep_href_link( $sUrlPage, 'action=account_check' ) ?>">
	<div class="oeBox column a12 row ax">
		<div class="oeWrpr">
			<div class="oeTitu"><i class="fas fa-eye"></i> <?php echo TEXT_INFO_HEADING_CONFIRM_PASSWORD; ?></div>
			<div class="oeCntd row ax xform xform-horizontal">
				<?php echo tep_draw_hidden_field('id_info', $account['admin_id']) ?>
				<label class="column a02 tright inline"><strong><?php echo TEXT_INFO_PASSWORD ?></strong></label>
				<div class="column a10">
					<input type="password" name="account_password" id="account_password" value=""/>
				</div>
			</div>
		</div>
	</div>
</form>
