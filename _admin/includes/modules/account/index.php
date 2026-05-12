<?php

// Tools
use util\tools as tools;
use util\date as date;

use util\event;

// Incluimos el application_top
require('includes/application_top.php');

// 2FA Admin: instalacion idempotente
include __DIR__ . '/../2fa-admin/install.php';

// Evento: seccion 2FA en la vista principal de Mi Cuenta
event::getInstance()->add('back_office_account_index_2fa', function () {
    return include __DIR__ . '/../2fa-admin/template/account_section.php';
});

// Variables
$sUrlPage = 'admin_account.php';
$sPathModule = 'includes/modules/account';
$sPathTemplate = $sPathModule . '/template';
$sTitle = HEADING_TITLE;
$sSubtitle = '';
$aButtons = [];
$sPostAction = array_key_exists('action', $_POST) ? tep_db_input($_POST['action']) : (array_key_exists('action', $_GET) ? tep_db_input($_GET['action']) : false);

# Messagestack estilo
$messageStack->style = 'solenopsis';

// Acciones
switch ($sPostAction) {
	case 'account_edit':
		$sSubtitle = HEADING_SUBTITLE;
		$aButtons = [
			['title' => TEXT_CANCEL, 'href' => tep_href_link($sUrlPage), 'icon' => 'fa-arrow-left'],
			['title' => TEXT_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde']
		];

		if (!tep_session_is_registered('confirm_account')) {
			tep_redirect(tep_href_link($sUrlPage, 'action=account_check'));
		}

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$admin_id = tep_db_prepare_input($_POST['id_info']);
			$group_id = tep_db_prepare_input($_POST['group_id']);
			$admin_firstname = tep_db_prepare_input($_POST['account_firstname']);
			$admin_lastname = tep_db_prepare_input($_POST['account_lastname']);
			$admin_email_address = tep_db_prepare_input($_POST['account_email']);

			$stored_email[] = '';

			$check_email_query = tep_db_query(
				"SELECT admin_email_address
						FROM admin
						WHERE admin_id <> " . (int)$admin_id . "");

			while ($check_email = tep_db_fetch_array($check_email_query)) {
				$stored_email[] = $check_email['admin_email_address'];
			}

			if (in_array($admin_email_address, $stored_email)) {
				$messageStack->addSession('success', TEXT_ERROR_EMAIL_EXISTS, 'error');
				tep_redirect(tep_href_link($sUrlPage, 'action=account_edit'));
			} else {
				$password = tep_db_prepare_input($_POST['password']);
				$password_repeat = tep_db_prepare_input($_POST['password_repeat']);
				$hasPasswordChanged = $password != '' && $password_repeat != '';
				if (!$hasPasswordChanged && $password != $password_repeat) {
					$messageStack->addSession('success', TEXT_ERROR_PASSWORD_CONFIRM, 'error');
					tep_redirect(tep_href_link($sUrlPage, 'action=account_edit'));
				}

				if($hasPasswordChanged) {
					// Si la contraseña es identica
					if ($adminCore->checkPassword($password)) {
						$messageStack->addSession('success', TEXT_ERROR_PASSWORD_SAME, 'error');
						tep_redirect(tep_href_link($sUrlPage, 'action=account_edit'));
					}

					// Si la contraseña no es alfanumerica
					if (!preg_match('/^(?=.*\d)(?=.*[@#\-_$%^&+=ยง!\?])(?=.*[a-z])(?=.*[A-Z])[0-9A-Za-z@#\-_$%^&+=ยง!\?]{8,20}$/', $password)) {
						$messageStack->addSession('success', TEXT_ERROR_PASSWORD_REGEX, 'error');
						tep_redirect(tep_href_link($sUrlPage, 'action=account_edit'));
					}

					// Eliminamos session de modificar contraseña
					tep_db_query('UPDATE admin SET admin_reset_password = 0 WHERE admin_id = "' . (int)$admin_id . '"');
				}

				$sql_data_array = [
					'admin_firstname' => $admin_firstname,
					'admin_lastname' => $admin_lastname,
					'admin_email_address' => $admin_email_address,
					'admin_modified' => 'now()'
				];

				tep_db_perform("admin", $sql_data_array, 'update', 'admin_id = \'' . $admin_id . '\'');

				if($hasPasswordChanged) {
					$adminCore->setPassword($password);
				}
			}

			tep_redirect(tep_href_link($sUrlPage));
		}

		$account_query = tep_db_query("SELECT a.admin_id, a.admin_firstname, a.admin_lastname, a.admin_email_address, a.admin_created, a.admin_modified, a.admin_logdate, a.admin_lognum, g.admin_groups_id, g.admin_groups_name
				FROM admin a, admin_groups g WHERE a.admin_id= " . $login_id . " AND g.admin_groups_id= " . $login_groups_id);
		$account = tep_db_fetch_array($account_query);

		// Modulo
		$sHtmlModule = includeTemplate($sPathTemplate . '/account_edit.php');
		break;

	case 'account_check':
		$sSubtitle = HEADING_SUBTITLE;
		$aButtons = [
			['title' => TEXT_BACK, 'href' => tep_href_link($sUrlPage), 'icon' => 'fa-arrow-left'],
			['title' => TEXT_BUTTON_CHECK, 'icon' => 'fa-right-to-bracket', 'extra' => 'id="saveform"', 'anchor_class' => 'verde']
		];

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$formPassword = $_POST['account_password'];

			$check_pass_query = tep_db_query("SELECT admin_password
						FROM admin
						WHERE admin_id = '" . $_POST['id_info'] . "'");
			$check_pass = tep_db_fetch_array($check_pass_query);

			if (!tep_validate_password($formPassword, $check_pass['admin_password'])) {
				$messageStack->addSession('success', TEXT_ERROR_INCORRECT_PASSWORD, 'error');
				tep_redirect(tep_href_link($sUrlPage, 'action=account_check'));
			} else {
				tep_session_register('confirm_account');
				$GLOBALS['confirm_account'] = 1;
				tep_redirect(tep_href_link(FILENAME_ADMIN_ACCOUNT, 'action=account_edit'));
			}
		}

		$account_query = tep_db_query(
			"SELECT a.admin_id
					FROM admin a, admin_groups g
					WHERE a.admin_id= " . $login_id . " AND g.admin_groups_id= " . $login_groups_id
		);
		$account = tep_db_fetch_array($account_query);

		// Modulo
		$sHtmlModule = includeTemplate($sPathTemplate . '/account_check.php');
		break;

	// Acciones 2FA — delegadas al modulo 2fa-admin
	case 'account_2fa_setup':
		require __DIR__ . '/../2fa-admin/actions/setup.php';
		break;

	case 'account_2fa_setup_confirm':
		require __DIR__ . '/../2fa-admin/actions/setup_confirm.php';
		break;

	case 'account_2fa_disable':
		require __DIR__ . '/../2fa-admin/actions/disable.php';
		break;

	case 'account_2fa_recovery_regen':
		require __DIR__ . '/../2fa-admin/actions/recovery_regen.php';
		break;

	default:
		$sSubtitle = HEADING_SUBTITLE;
		$messageFirstTime = '';
		$aButtons = [
			['title' => TEXT_BUTTON_EDIT, 'href' => tep_href_link($sUrlPage, 'action=account_check'), 'icon' => 'fa-gear']
		];

		// Limpiar secreto pendiente si el admin volvio al index sin completar el setup
		unset($_SESSION['2fa_pending_secret']);

		$account_query = tep_db_query(
			"SELECT a.admin_id, a.admin_firstname, a.admin_lastname, a.admin_email_address, a.admin_created, a.admin_modified, a.admin_logdate, a.admin_lognum, a.admin_2fa_enabled, g.admin_groups_name
				FROM admin a, admin_groups g WHERE a.admin_id= " . $login_id . " AND g.admin_groups_id= " . $login_groups_id
		);
		$account = tep_db_fetch_array($account_query);

		if (tep_session_is_registered('confirm_account')) {
			tep_session_unregister('confirm_account');
		}

		if ($account['admin_modified'] == '0000-00-00 00:00:00' || $account['admin_logdate'] <= 1) {
			$messageFirstTime = $messageStack->show(['text' => sprintf(TEXT_INFO_INTRO_DEFAULT_FIRST_TIME, $account['admin_firstname']), 'class' => 'info']);
		}

		// Modulo
		$sHtmlModule = includeTemplate($sPathTemplate . '/index.php', [
			'messageFirstTime' => $messageFirstTime
		]);
		break;
}

// Pintamos
echo includeTemplate($sPathTemplate . '/base.php');

?>
