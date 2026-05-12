<?php
// Render HTML — patron identico a login.php (UI-SPEC)
include('theme/solenopsis/html/header.php');

$messageStack->style = 'solenopsis';
echo $messageStack->output();
?>

<link rel="stylesheet" href="includes/modules/2fa-admin/css/otp.css">
<style>
.otp-box {
	width: 47px !important; min-width: 47px; max-width: 47px; height: 47px;
	font-size: 25px !important; line-height: 47px;
}
.otp-sep { line-height: 47px; }
#logn .otp-box { margin-bottom: 0 !important; }
</style>
<a href="https://www.denox.es/" id="logn-denox"></a>
<form method="post" action="<?php echo tep_href_link(FILENAME_TOTP_VERIFY, 'action=process', 'SSL'); ?>" id="logn">
	<input type="hidden" name="totp_code" id="otp-hidden">
	<div id="logn-msct"></div>
	<div id="logn-titu"><?php echo TEXT_TOTP_HEADING; ?></div>

	<div id="logn-totp-wrap" style="display:block; text-align:center; margin: 15px 0 10px;">
		<div class="otp-group">
			<input type="text" class="otp-box" autocomplete="one-time-code" inputmode="numeric" data-i="0" autofocus>
			<input type="text" class="otp-box" maxlength="1" inputmode="numeric" data-i="1">
			<input type="text" class="otp-box" maxlength="1" inputmode="numeric" data-i="2">
			<span class="otp-sep">–</span>
			<input type="text" class="otp-box" maxlength="1" inputmode="numeric" data-i="3">
			<input type="text" class="otp-box" maxlength="1" inputmode="numeric" data-i="4">
			<input type="text" class="otp-box" maxlength="1" inputmode="numeric" data-i="5">
		</div>
	</div>

	<div id="logn-recovery-wrap" style="display:none;">
		<?php echo tep_draw_input_field('recovery_code', '', 'id="logn-recovery" placeholder="' . TEXT_RECOVERY_CODE_PLACEHOLDER . '" autocomplete="off"'); ?>
	</div>

	<a href="javascript:void(0);" id="logn-olvd" onclick="toggleRecovery()"><?php echo TEXT_USE_RECOVERY_CODE; ?></a>
	<button id="logn-butn" class="bton-dflt" type="submit" name="enviar"><?php echo TEXT_TOTP_VERIFY_BUTTON; ?></button>
</form>

<script src="includes/modules/2fa-admin/js/otp.js"></script>
<script>
function toggleRecovery() {
	var totpWrap = document.getElementById('logn-totp-wrap');
	var recoveryWrap = document.getElementById('logn-recovery-wrap');
	var toggleLink = document.getElementById('logn-olvd');
	var boxes = document.querySelectorAll('.otp-box');

	if (recoveryWrap.style.display === 'none') {
		totpWrap.style.display = 'none';
		recoveryWrap.style.display = 'block';
		toggleLink.textContent = '<?php echo TEXT_USE_TOTP_CODE; ?>';
		boxes.forEach(function(b) { b.value = ''; b.classList.remove('filled'); });
		document.getElementById('otp-hidden').value = '';
		document.getElementById('logn-recovery').focus();
	} else {
		recoveryWrap.style.display = 'none';
		totpWrap.style.display = 'block';
		toggleLink.textContent = '<?php echo TEXT_USE_RECOVERY_CODE; ?>';
		document.getElementById('logn-recovery').value = '';
		boxes[0].focus();
	}
}
</script>
</body>
</html>
