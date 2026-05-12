<?php
/**
 * Accion account_2fa_recovery_regen — Regenerar codigos de recuperacion
 * Se incluye desde account/index.php via require
 */

use util\authentication\Admin;
use Oscdenox\Core\Auth\TwoFactor\RecoveryCodeService;
use Oscdenox\Core\Auth\TwoFactor\AdminTwoFactorRepository;

$s2faPathTemplate = 'includes/modules/2fa-admin/template';
$repo = new AdminTwoFactorRepository();

// Verificar que el admin tiene 2FA activo
$status = $repo->getStatus((int)$login_id);
if ($status['admin_2fa_enabled'] != 1) {
    $messageStack->addSession('success', TEXT_2FA_ERROR_NOT_ENABLED, 'error');
    tep_redirect(tep_href_link($sUrlPage));
}

$sSubtitle = TEXT_2FA_RECOVERY_TITLE;
$aButtons = [
    ['title' => TEXT_CANCEL, 'href' => tep_href_link($sUrlPage), 'icon' => 'fa-arrow-left'],
];

$plainCodes = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirmPassword = tep_db_prepare_input($_POST['confirm_password'] ?? '');

    if (!Admin::validatePassword($adminCore->getPassword(), $confirmPassword)) {
        $messageStack->addSession('success', TEXT_2FA_ERROR_WRONG_PASSWORD, 'error');
        tep_redirect(tep_href_link($sUrlPage, 'action=account_2fa_recovery_regen'));
    }

    $recoveryService = new RecoveryCodeService();
    $plainCodes = $recoveryService->generateCodes((int)$login_id);

    $messageStack->add(TEXT_2FA_ACTIVATED_WARNING, 'warning');

    $sSubtitle = TEXT_2FA_RECOVERY_SUCCESS_TITLE;
    $aButtons = [
        ['title' => TEXT_2FA_ACTIVATED_PRINT, 'href' => 'javascript:void(0)', 'icon' => 'fa-print', 'extra' => 'onclick="printRecoveryCodes()"'],
        ['title' => TEXT_2FA_ACTIVATED_CONFIRM, 'href' => tep_href_link($sUrlPage), 'icon' => 'fa-check', 'anchor_class' => 'verde'],
    ];
}

$sHtmlModule = includeTemplate($s2faPathTemplate . '/recovery.php', [
    'plainCodes' => $plainCodes,
]);
