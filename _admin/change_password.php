<?php
require('includes/application_top.php');

$customer_id = (int)tep_db_prepare_input( $_GET['cID'] );
$messageStack->style = 'solenopsis';
if ($_POST['customer_id'] != '') {
	$pass = 0;
	$customer_id = (int)tep_db_prepare_input( $_POST['customer_id'] );

	if ($_POST['new_password'] == '' && $_POST['repeat_password'] == '') {
		$pass = 0;
		$messageStack->addSession( 'error', PLEASE_NEW_PASSWORD, 'error');
	}
	elseif ($_POST['new_password'] == $_POST['repeat_password']) {
		$pass = 1;
		$new_password = $_POST['new_password'];
	}
	elseif (empty($_POST['new_password']) || empty($_POST['repeat_password'])) {
		$pass = 0;
		$messageStack->addSession( 'error', PLEASE_NEW_PASSWORD . PLEASE_REPEAT, 'error');
	}
	elseif ($_POST['new_password'] != $_POST['repeat_password']) {
		$pass = 0;
		$messageStack->addSession( 'error', ERROR_NEW_PASSWORD, 'error');
	}

	if ($pass == 1) {

		tep_db_query("update " . TABLE_CUSTOMERS . " set customers_password='" . tep_encrypt_password ($new_password) . "' where customers_id='" . $customer_id . "'");

		$customer_name_query = tep_db_query("SELECT customers_firstname, customers_lastname FROM " . TABLE_CUSTOMERS . " WHERE customers_id='" . $customer_id . "'");
		$customer_name = tep_db_fetch_array($customer_name_query);

		$message .= CUSTOMER_PASSWORD . PASSWORD_UPDATED . '' . $new_password . '<br>' . PASSWORD_UPDATED_REMINDER;

		$messageStack->addSession( 'success', $message, 'success' );
	}

	tep_redirect(tep_href_link('change_password.php', 'cID=' . $customer_id,'SSL'));

}
elseif( ! isset( $_GET['cID'] ) ) {
	tep_redirect(FILENAME_CUSTOMERS);
}

$customer_data_query = tep_db_query( "select customers_id, customers_firstname, customers_lastname, customers_email_address from " . TABLE_CUSTOMERS . " where customers_id = " . tep_db_prepare_input( $customer_id ) );
$customer_data = tep_db_fetch_array( $customer_data_query );

$auto_password = tep_create_random_value(ENTRY_PASSWORD_MIN_LENGTH);
$auto_form = tep_draw_hidden_field('auto_password', $auto_password) . $auto_password;
$generated_password = tep_create_random_value(12);

// MessageStack
$sMessageStack = $messageStack->output(false);
$messageStack->reset();

require(THEME . 'html/header.php');
?>

<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
    <td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_TITLE . ' (' . $customer_data['customers_email_address'] . ')'; ?></td>
          </tr>
        </table></td>
      </tr>
			<tr><td><?php echo $sMessageStack; ?></td></tr>
      <tr>
        <td><?php
			echo tep_draw_separator('pixel_trans.png', '100%', '10'); ?></td>
      </tr>
			<tr>
				<td><?php echo tep_draw_form('password', 'change_password.php', 'cID=' . $customer_id, 'POST'); ?>
					<table border=0 width="90%" cellspacing="0" cellpadding="2">
						<tr>
            <td class="main" width="200px">Usuario</td>
            <td class="main">
				<?php
					echo $customer_data['customers_firstname'] . ' ' . $customer_data['customers_lastname'] . ' (' . $customer_data['customers_email_address'] . ')';
								echo tep_draw_hidden_field('customer_id', $customer_id);
								?>
							</td>
						</tr>
						<tr>
							<td class="main"><?php echo AUTO_PASSWORD; ?></td>
							<td class="main">
								<span id="generated-password"><?php echo $generated_password; ?></span>
								<button type="button" class="buttonS bLightBlue use-password-button"><i class="fa-solid fa-key"></i> Usar esta contraseña</button>
								<button type="button" class="buttonS bDefault generate-new-password-button"><i class="fa-solid fa-rotate"></i> Generar nueva</button>
							</td>
						</tr>

						<tr>
							<td class="main"><?php echo NEW_PASSWORD; ?></td>
							<td class="main"><?php echo tep_draw_password_field('new_password'); ?></td>
						</tr>
						<tr>
							<td class="main"><?php echo REPEAT_NEW_PASSWORD; ?></td>
							<td class="main"><?php echo tep_draw_password_field('repeat_password'); ?></td>
						</tr>
						<tr>
							<td class="main">&nbsp;</td>
							<td><input type="submit" class="buttonS bGreen" value="<?php echo CHANGE_PASSWORD; ?>"/></td>
						</tr>
						</form></table>
				</td>
			</tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.png', '100%', '10'); ?></td>
      </tr>
    </table></td>
  </tr>
</table>
<script>
	document.addEventListener('DOMContentLoaded', function() {
		const usePasswordButton = document.querySelector('.use-password-button');
		usePasswordButton.addEventListener('click', fillPasswordFieldsAndCopyToClipboard);

		const generateNewPasswordButton = document.querySelector('.generate-new-password-button');
		generateNewPasswordButton.addEventListener('click', generateNewPasswordAndDisplay);

		function fillPasswordFieldsAndCopyToClipboard() {
			const newPassword = document.getElementById('generated-password').textContent;
			document.querySelector('[name="new_password"]').value = newPassword;
			document.querySelector('[name="repeat_password"]').value = newPassword;

			// Copiar contraseña al portapapeles
			navigator.clipboard.writeText(newPassword).then(() => {
				console.log('Contraseña copiada al portapapeles');
			}).catch(err => {
				console.error('Error al copiar la contraseña al portapapeles:', err);
			});
		}

		function generateNewPasswordAndDisplay() {
			const newPassword = createRandomPassword(12); // Adjust the length as needed
			document.getElementById('generated-password').textContent = newPassword;
			fillPasswordFieldsAndCopyToClipboard();
		}
	});

	function createRandomPassword(length, type = 'mixed') {
		const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		const digits = '0123456789';

		switch (type) {
			case 'chars':
				base = chars;
				break;
			case 'digits':
				base = digits;
				break;
			default:
				base = chars + digits;
				break;
		}

		const baseLength = base.length;
		let password = '';

		for (let i = 0; i < length; i++) {
			const randomIndex = Math.floor(Math.random() * baseLength);
			password += base[randomIndex];
		}

		return password;
	}
</script>
<?php
	require(THEME . 'html/footer.php');
	require(DIR_WS_INCLUDES . 'application_bottom.php');
?>
