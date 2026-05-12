<?php
/**
 * Accion account_2fa_setup_confirm — Verificar codigo TOTP y activar 2FA
 * Se incluye desde account/index.php via require
 */

use Oscdenox\Core\Auth\TwoFactor\TotpService;
use Oscdenox\Core\Auth\TwoFactor\RecoveryCodeService;
use Oscdenox\Core\Auth\TwoFactor\AdminTwoFactorRepository;

$s2faPathTemplate = 'includes/modules/2fa-admin/template';
$aStyle = ['includes/modules/2fa-admin/css/otp.css'];
$aJs = ['includes/modules/2fa-admin/js/otp.js'];

$plainSecret = $_SESSION['2fa_pending_secret'] ?? null;

if (!$plainSecret) {
    $messageStack->addSession('success', TEXT_2FA_ERROR_NO_PENDING, 'error');
    tep_redirect(tep_href_link($sUrlPage, 'action=account_2fa_setup'));
}

$totpCode = trim($_POST['totp_code'] ?? '');
$totpService = new TotpService(SECURITY_KEY, APP_2FA_SALT);
$repo = new AdminTwoFactorRepository();

if (!$totpService->verifyWithPlainSecret($totpCode, $plainSecret)) {
    // Codigo invalido — regenerar QR desde el secreto en sesion
    $messageStack->add(TEXT_2FA_ERROR_INVALID_CODE, 'error');

    $qrData = $totpService->generateQrData($plainSecret, $repo->getEmail((int)$login_id));

    $sSubtitle = TEXT_2FA_SETUP_TITLE;
    $aButtons = [
        ['title' => TEXT_CANCEL, 'href' => tep_href_link($sUrlPage), 'icon' => 'fa-arrow-left'],
        ['title' => TEXT_2FA_SETUP_SUBMIT, 'icon' => 'fa-shield-halved', 'extra' => 'id="saveform"', 'anchor_class' => 'verde'],
    ];

    $sHtmlModule = includeTemplate($s2faPathTemplate . '/setup.php', $qrData);
    return;
}

// Codigo valido — cifrar y guardar en BD
$repo->activate((int)$login_id, $totpService->encryptSecret($plainSecret));

// Generar recovery codes — se muestran UNA SOLA VEZ
$recoveryService = new RecoveryCodeService();
$plainCodes = $recoveryService->generateCodes((int)$login_id);

unset($_SESSION['2fa_pending_secret']);

$messageStack->add(TEXT_2FA_ACTIVATED_WARNING, 'warning');

$sSubtitle = TEXT_2FA_ACTIVATED_TITLE;
$aButtons = [
    ['title' => TEXT_2FA_ACTIVATED_PRINT, 'href' => 'javascript:void(0)', 'icon' => 'fa-print', 'extra' => 'onclick="printRecoveryCodes()"'],
    ['title' => TEXT_2FA_ACTIVATED_CONFIRM, 'href' => tep_href_link($sUrlPage), 'icon' => 'fa-check', 'anchor_class' => 'verde'],
];

$sHtmlModule = includeTemplate($s2faPathTemplate . '/activated.php', [
    'plainCodes' => $plainCodes,
]);
