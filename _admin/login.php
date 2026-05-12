<?php

use util\authentication\Admin;
use util\authentication\Exception\AdminNotExistException;
use util\authentication\Exception\AdminNotValidPasswordException;
use util\validators\Exception\CaptchaNotValidException;
use util\tools;

include 'includes/application_top.php';

// Si estamos logueados
if ($adminCore->hasLogin()) {
	tep_redirect(tep_href_link('index.php'));
}

if (isset($_GET['action']) && ($_GET['action'] == 'process')) {
	try {
		$requestPostEmailAddress = tep_db_prepare_input($_POST['email_address'] ?? '');
		$requestPostPassword     = tep_db_prepare_input($_POST['password'] ?? '');

		// Seguridad
		$dxSecurity->loginAdminPeriodLockouts();

		// Validamos captcha (si falla, lanza excepción)
		tools::validate(
				'ReCaptcha',
				$_POST['g-recaptcha-token'] ?? '',
				['accountId' => $requestPostEmailAddress, 'email' => $requestPostEmailAddress],
				'login_admin'
		);

		// Admin
		$admin = Admin::createByEmail($requestPostEmailAddress, $requestPostPassword);

		// Comprobar si tiene 2FA activo antes de completar el login
		$_2faCheck = tep_db_fetch_array(tep_db_query(
		    'SELECT admin_2fa_enabled FROM admin WHERE admin_id = "' . (int)$admin->getId() . '"'
		));

		if ($_2faCheck && $_2faCheck['admin_2fa_enabled'] == 1) {
		    // No completar login — guardar ID pendiente y redirigir a verificacion TOTP
		    $_SESSION['twofactor_pending_id'] = $admin->getId();
		    tep_redirect(tep_href_link(FILENAME_TOTP_VERIFY, '', 'SSL'));
		}

		$admin->login();
		$admin->updateNumberOfLogons();
		$cookie->password = $admin->getPassword();

		tep_redirect($admin->hasFirstLogin() ? FILENAME_ADMIN_ACCOUNT : FILENAME_DEFAULT);

	} catch (CaptchaNotValidException $e) {
		$messageStack->add('login', $e->getMessage(), 'error', true);
		} catch (AdminNotValidPasswordException|AdminNotExistException $e) {
		$dxSecurity->loginAdminFailed();
		$messageStack->add('login', TEXT_LOGIN_ERROR, 'error', true);
	}
}

include('theme/solenopsis/html/header.php');

# Messagestack estilo
$messageStack->style = 'solenopsis';

echo $messageStack->output();
?>

<a href="https://www.denox.es/" id="logn-denox"></a>
<form method="post" action="<?php echo tep_href_link(FILENAME_LOGIN, 'action=process'); ?>" id="logn">
	<div id="logn-msct"></div>
	<div id="logn-titu"><?php echo HEADING_TITLE; ?></div>
	<?php echo tep_draw_input_field('email_address', '', 'id="logn-email" placeholder="' . HEADING_INPUT_PLACEHOLDER_MAIL . '"'); ?>
	<?php echo tep_draw_input_field('password', '', 'id="logn-pasw" placeholder="' . HEADING_INPUT_PLACEHOLDER_PASSWORD . '"', false, 'password'); ?>
	<a title="¿Has olvidado tu contraseña?" href="<?php echo tep_href_link(FILENAME_PASSWORD_FORGOTTEN, '', 'SSL'); ?>" id="logn-olvd"><?php echo TEXT_PASSWORD_FORGOTTEN; ?></a>
	<button id="logn-butn" class="bton-dflt logn-acdr" type="submit" name="enviar"><?php echo IMAGE_BUTTON_LOGIN; ?></button>
	<div class="google-widget-captcha"></div>
</form>
<?php if (RECAPTCHA_ENABLE === 'true'): ?>
	<script type="text/javascript">
		var captchaGoogleEnable = true;
		var captchaGoogleSiteKey = "<?php echo RECAPTCHA_PUBLIC_KEY; ?>";
		var captchaGoogleAction = "login_admin";
	</script>
	<script src="https://www.google.com/recaptcha/enterprise.js?render=<?php echo RECAPTCHA_PUBLIC_KEY; ?>"></script>
	<script>
		grecaptcha.enterprise.ready(function () {
			var form = document.getElementById("logn");
			var button = document.getElementById("logn-butn");
			var container = document.querySelector(".google-widget-captcha");

			if (!form || !button || !container) return;

			var reCaptchaId = grecaptcha.enterprise.render(container, {
				'sitekey': captchaGoogleSiteKey,
				'size': 'invisible',
				'action': captchaGoogleAction,
				'callback': function (token) {
					var input = form.querySelector("input[name='g-recaptcha-token']");
					if (!input) {
						input = document.createElement("input");
						input.type = "hidden";
						input.name = "g-recaptcha-token";
						form.appendChild(input);
					}
					input.value = token;
					form.submit();
				}
			});

			button.addEventListener("click", function (e) {
				e.preventDefault();
				grecaptcha.enterprise.execute(reCaptchaId);
			});
		});
	</script>
<?php endif; ?>
</body>
</html>
