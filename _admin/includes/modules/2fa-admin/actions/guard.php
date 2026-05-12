<?php
/**
 * Guard: 2FA obligatorio con periodo de gracia
 * Se incluye desde application_top.php via require
 */

use Oscdenox\Core\Auth\TwoFactor\AdminTwoFactorRepository;

// Cargar traducciones del modulo
$_2faLangDir = __DIR__ . '/../languages/' . (defined('DEFAULT_LANGUAGE') ? DEFAULT_LANGUAGE : 'espanol') . '/';
if (file_exists($_2faLangDir . 'guard.php')) include_once($_2faLangDir . 'guard.php');

if (
    defined('2FA_MANDATORY') && constant('2FA_MANDATORY') === 'true'
    && isset($login_id)
    && !in_array(basename($_SERVER['SCRIPT_FILENAME']), isset($excludedFilesFromLogin) ? $excludedFilesFromLogin : [FILENAME_LOGIN, FILENAME_PASSWORD_FORGOTTEN, FILENAME_TOTP_VERIFY])
    && basename($_SERVER['SCRIPT_FILENAME']) !== FILENAME_ADMIN_ACCOUNT
) {
    $repo = new AdminTwoFactorRepository();
    $status = $repo->getStatus((int)$login_id);

    if ((int)$status['admin_2fa_enabled'] === 0) {
        $graceDays = defined('2FA_GRACE_PERIOD_DAYS') ? (int)constant('2FA_GRACE_PERIOD_DAYS') : 2;
        $graceStart = $status['admin_2fa_grace_start'];

        // Lazy initialization
        if ($graceStart === null || $graceStart === '') {
            $graceStart = $repo->initGracePeriod((int)$login_id);
        }

        $daysUsed = (int)floor((time() - strtotime($graceStart)) / 86400);
        $daysLeft = max(0, $graceDays - $daysUsed);

        if ($daysLeft <= 0) {
            tep_redirect(tep_href_link(FILENAME_ADMIN_ACCOUNT, 'action=account_2fa_setup', 'SSL'));
        } else {
            $messageStack->add(
                sprintf(TEXT_2FA_GRACE_WARNING, $daysLeft, tep_href_link(FILENAME_ADMIN_ACCOUNT, 'action=account_2fa_setup')),
                'warning'
            );
        }
    }
}
