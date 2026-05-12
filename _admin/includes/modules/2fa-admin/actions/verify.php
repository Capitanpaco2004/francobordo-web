<?php
/**
 * Logica de verificacion TOTP (segundo factor) en login
 * Se incluye desde _admin/login_2fa.php via require
 */

use util\authentication\Admin;
use Oscdenox\Core\Auth\TwoFactor\TotpService;
use Oscdenox\Core\Auth\TwoFactor\RecoveryCodeService;
use Oscdenox\Core\Auth\TwoFactor\AdminTwoFactorRepository;
use Oscdenox\Core\Auth\TwoFactor\Exception\TotpReplayAttackException;

// Si ya esta logueado, limpiar estado pendiente y redirigir
if ($adminCore->hasLogin()) {
    if (isset($_SESSION['twofactor_pending_id'])) {
        tep_session_unregister('twofactor_pending_id');
        unset($_SESSION['twofactor_pending_id']);
    }
    tep_redirect(tep_href_link('index.php'));
}

// Si no hay sesion pendiente de 2FA, redirigir a login
if (!isset($_SESSION['twofactor_pending_id'])) {
    tep_redirect(tep_href_link(FILENAME_LOGIN_ADMIN, '', 'SSL'));
}

$pendingAdminId = (int)$_SESSION['twofactor_pending_id'];

// Procesar verificacion
if (isset($_GET['action']) && $_GET['action'] == 'process') {
    $dxSecurity->loginAdminPeriodLockouts();

    $totpCode = trim($_POST['totp_code'] ?? '');
    $recoveryCode = trim($_POST['recovery_code'] ?? '');
    $verified = false;

    $repo = new AdminTwoFactorRepository();

    try {
        if ($totpCode !== '') {
            $data = $repo->getVerificationData($pendingAdminId);
            $totpService = new TotpService(SECURITY_KEY, APP_2FA_SALT);
            $verified = $totpService->verify(
                $totpCode,
                $data['admin_2fa_secret'],
                $data['admin_2fa_last_used'],
                $data['admin_2fa_last_used_at']
            );

            if ($verified) {
                $repo->updateLastUsed($pendingAdminId, $totpCode);
            } else {
                $messageStack->add(TEXT_TOTP_ERROR_INVALID, 'error');
            }
        } elseif ($recoveryCode !== '') {
            $recoveryService = new RecoveryCodeService();
            $verified = $recoveryService->consume($recoveryCode, $pendingAdminId);

            if (!$verified) {
                $messageStack->add(TEXT_TOTP_ERROR_RECOVERY_INVALID, 'error');
            }
        }
    } catch (TotpReplayAttackException) {
        $messageStack->add(TEXT_TOTP_ERROR_REPLAY, 'error');
    }

    if ($verified) {
        $admin = Admin::createById($pendingAdminId);
        $admin->login();
        $admin->updateNumberOfLogons();

        global $cookie;
        $cookie->password = $admin->getPassword();

        tep_session_unregister('twofactor_pending_id');
        unset($_SESSION['twofactor_pending_id']);

        tep_redirect($admin->hasFirstLogin() ? FILENAME_ADMIN_ACCOUNT : FILENAME_DEFAULT);
    } else {
        $dxSecurity->loginAdminFailed();
    }
}
