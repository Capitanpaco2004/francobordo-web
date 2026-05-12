<?php
/**
 * Accion account_2fa_disable — Desactivar 2FA
 * Se incluye desde account/index.php via require
 */

use util\authentication\Admin;
use Oscdenox\Core\Auth\TwoFactor\AdminTwoFactorRepository;

$s2faPathTemplate = 'includes/modules/2fa-admin/template';
$repo = new AdminTwoFactorRepository();

// Verificar que el admin tiene 2FA activo
$status = $repo->getStatus((int)$login_id);
if ($status['admin_2fa_enabled'] != 1) {
    $messageStack->addSession('success', TEXT_2FA_ERROR_NOT_ENABLED, 'error');
    tep_redirect(tep_href_link($sUrlPage));
}

$sSubtitle = TEXT_2FA_DISABLE_TITLE;
$aButtons = [
    ['title' => TEXT_CANCEL, 'href' => tep_href_link($sUrlPage), 'icon' => 'fa-arrow-left'],
    ['title' => TEXT_2FA_DISABLE_SUBMIT, 'icon' => 'fa-shield-xmark', 'extra' => 'id="saveform"', 'anchor_class' => 'rojo'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirmPassword = tep_db_prepare_input($_POST['confirm_password'] ?? '');

    if (!Admin::validatePassword($adminCore->getPassword(), $confirmPassword)) {
        $messageStack->addSession('success', TEXT_2FA_ERROR_WRONG_PASSWORD, 'error');
        tep_redirect(tep_href_link($sUrlPage, 'action=account_2fa_disable'));
    }

    $repo->deactivate((int)$login_id);

    $messageStack->addSession('success', TEXT_2FA_DISABLED_SUCCESS, 'success');
    tep_redirect(tep_href_link($sUrlPage));
}

$sHtmlModule = includeTemplate($s2faPathTemplate . '/disable.php');
