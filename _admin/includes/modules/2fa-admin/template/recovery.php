<?php if ($plainCodes === null): ?>
<!-- Formulario de confirmacion de contrasena -->
<form method="post" action="<?php echo tep_href_link($sUrlPage, 'action=account_2fa_recovery_regen') ?>" id="mainForm">
<div class="oeBox column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fas fa-rotate"></i> <?php echo TEXT_2FA_RECOVERY_TITLE ?></div>
		<div class="oeCntd row ax xform xform-horizontal">
			<p style="margin-bottom: 20px;"><?php echo TEXT_2FA_RECOVERY_INTRO ?></p>

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

<?php else: ?>
<!-- Codigos regenerados — se muestran UNA SOLA VEZ -->
<div class="oeBox column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fas fa-key" style="color: #27ae60;"></i> <?php echo TEXT_2FA_RECOVERY_SUCCESS_TITLE ?></div>
		<div class="oeCntd">
			<p style="margin-bottom: 15px;"><?php echo TEXT_2FA_RECOVERY_SUCCESS_INTRO ?></p>

			<div id="recovery-codes-print" style="display: flex; gap: 16px; justify-content: center; margin: 15px 0;">
				<?php foreach (array_chunk($plainCodes, 5) as $group): ?>
				<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 16px 20px;">
					<?php foreach ($group as $code): ?>
					<code style="display: block; font-size: 15px; font-family: 'Courier New', monospace; letter-spacing: 3px; padding: 6px 0; color: #333;"><?php echo $code ?></code>
					<?php endforeach; ?>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>

<script>
function printRecoveryCodes() {
	var content = document.getElementById('recovery-codes-print').innerHTML;
	var win = window.open('', '_blank', 'width=400,height=500');
	win.document.write('<html><head><title><?php echo TEXT_2FA_RECOVERY_TITLE ?></title>');
	win.document.write('<style>body{font-family:monospace;padding:20px}code{display:block;font-size:16px;letter-spacing:3px;padding:6px 0}</style>');
	win.document.write('</head><body>');
	win.document.write('<h3><?php echo STORE_NAME ?> - <?php echo TEXT_2FA_RECOVERY_TITLE ?></h3>');
	win.document.write(content);
	win.document.write('</body></html>');
	win.document.close();
	win.print();
}
</script>
<?php endif; ?>
