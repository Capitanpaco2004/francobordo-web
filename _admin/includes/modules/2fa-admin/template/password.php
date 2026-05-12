<form method="post" action="<?php echo tep_href_link($sUrlPage, 'action=account_2fa_setup') ?>" id="mainForm">
<div class="oeBox column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fas fa-shield-halved"></i> <?php echo TEXT_2FA_SETUP_TITLE ?></div>
		<div class="oeCntd row ax xform xform-horizontal">
			<p style="margin-bottom: 20px;"><?php echo TEXT_2FA_SETUP_PASSWORD_INTRO ?></p>

			<div class="xline xline-dashed"></div>

			<label class="column a03 tright inline"><strong><?php echo TEXT_2FA_DISABLE_PASSWORD_LABEL ?></strong></label>
			<div class="column a09">
				<input type="password" name="confirm_password" required autofocus style="width: 300px;">
			</div>
		</div>
	</div>
</div>
</form>

<script>
document.getElementById('saveform').addEventListener('click', function(e) {
	e.preventDefault();
	document.getElementById('mainForm').submit();
});
</script>
