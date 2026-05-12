<?php
/**
 * Accion members_2fa_reset — Resetear 2FA de un miembro (solo superadmin)
 * Se incluye desde members/members.php via require
 */

use Oscdenox\Core\Auth\TwoFactor\AdminTwoFactorRepository;

// Cargar traducciones del modulo
$_2faLangDir = __DIR__ . '/../languages/' . (defined('DEFAULT_LANGUAGE') ? DEFAULT_LANGUAGE : 'espanol') . '/';
if (file_exists($_2faLangDir . 'guard.php')) include_once($_2faLangDir . 'guard.php');

// Solo superadmin puede resetear 2FA
if ($login_groups_id != 1) {
    tep_redirect(tep_href_link($sUrlPage));
}

$adminId = (int)tep_db_prepare_input($_GET['id'] ?? $_POST['id'] ?? 0);
if ($adminId === 0) {
    tep_redirect(tep_href_link($sUrlPage, 'action=members'));
}

// No permitir auto-reset
if ($adminId === (int)$login_id) {
    $messageStack->addSession('success', TEXT_2FA_MEMBERS_RESET_SELF, 'error');
    tep_redirect(tep_href_link($sUrlPage, 'action=members'));
}

$repo = new AdminTwoFactorRepository();
$repo->reset($adminId);

$messageStack->addSession('success', TEXT_2FA_MEMBERS_RESET_SUCCESS, 'success');
tep_redirect(tep_href_link($sUrlPage, 'action=members'));
