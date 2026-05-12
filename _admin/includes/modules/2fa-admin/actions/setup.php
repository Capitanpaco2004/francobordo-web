<?php
/**
 * Accion account_2fa_setup — Configurar 2FA (mostrar QR + validar contrasena)
 * Se incluye desde account/index.php via require
 */

use util\authentication\Admin;
use Oscdenox\Core\Auth\TwoFactor\TotpService;
use Oscdenox\Core\Auth\TwoFactor\AdminTwoFactorRepository;

$aStyle = ['includes/modules/2fa-admin/css/otp.css'];
$aJs = ['includes/modules/2fa-admin/js/otp.js'];
$s2faPathTemplate = 'includes/modules/2fa-admin/template';

$repo = new AdminTwoFactorRepository();
$status = $repo->getStatus((int)$login_id);

// Verificar que el admin NO tiene ya 2FA activo
if ($status['admin_2fa_enabled'] == 1) {
    $messageStack->addSession('success', TEXT_2FA_ALREADY_ENABLED, 'warning');
    tep_redirect(tep_href_link($sUrlPage));
}

// Paso 1: confirmar contrasena antes de mostrar el QR
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $sSubtitle = TEXT_2FA_SETUP_TITLE;
    $aButtons = [
        ['title' => TEXT_CANCEL, 'href' => tep_href_link($sUrlPage), 'icon' => 'fa-arrow-left'],
        ['title' => TEXT_2FA_SETUP_SUBMIT, 'icon' => 'fa-shield-halved', 'extra' => 'id="saveform"', 'anchor_class' => 'verde'],
    ];
    $sHtmlModule = includeTemplate($s2faPathTemplate . '/password.php');
    return;
}

// Validar contrasena
$confirmPassword = tep_db_prepare_input($_POST['confirm_password'] ?? '');
if (!Admin::validatePassword($adminCore->getPassword(), $confirmPassword)) {
    $messageStack->addSession('success', TEXT_2FA_ERROR_WRONG_PASSWORD, 'error');
    tep_redirect(tep_href_link($sUrlPage, 'action=account_2fa_setup'));
}

// Paso 2: contrasena correcta — generar secreto y mostrar QR
$sSubtitle = TEXT_2FA_SETUP_TITLE;
$aButtons = [
    ['title' => TEXT_CANCEL, 'href' => tep_href_link($sUrlPage), 'icon' => 'fa-arrow-left'],
    ['title' => TEXT_2FA_SETUP_SUBMIT, 'icon' => 'fa-shield-halved', 'extra' => 'id="saveform"', 'anchor_class' => 'verde'],
];

unset($_SESSION['2fa_pending_secret']);

$totpService = new TotpService(SECURITY_KEY, APP_2FA_SALT);
$plainSecret = $totpService->generateSecret();
$_SESSION['2fa_pending_secret'] = $plainSecret;

$qrData = $totpService->generateQrData($plainSecret, $repo->getEmail((int)$login_id));

$sHtmlModule = includeTemplate($s2faPathTemplate . '/setup.php', $qrData);
