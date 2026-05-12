<?php

namespace Oscdenox\Core\Auth\TwoFactor;

/**
 * Repositorio para operaciones de BD del 2FA de administradores.
 *
 * Centraliza las queries directas a las tablas admin y admin_2fa_recovery_codes
 * que antes estaban desperdigadas por las acciones del modulo 2fa-admin.
 *
 * Principio: SRP — las acciones se ocupan del flujo HTTP, el repositorio de los datos.
 */
class AdminTwoFactorRepository
{
    /**
     * Obtiene el estado 2FA de un admin.
     *
     * @return array{admin_2fa_enabled: string, admin_2fa_secret: string|null, admin_2fa_grace_start: string|null}
     */
    public function getStatus(int $adminId): array
    {
        return tep_db_fetch_array(tep_db_query(
            'SELECT admin_2fa_enabled, admin_2fa_secret, admin_2fa_grace_start
             FROM admin WHERE admin_id = "' . $adminId . '"'
        ));
    }

    /**
     * Obtiene el email de un admin.
     */
    public function getEmail(int $adminId): string
    {
        $row = tep_db_fetch_array(tep_db_query(
            'SELECT admin_email_address FROM admin WHERE admin_id = "' . $adminId . '"'
        ));
        return $row['admin_email_address'];
    }

    /**
     * Activa el 2FA de un admin con el secreto cifrado.
     */
    public function activate(int $adminId, string $encryptedSecret): void
    {
        tep_db_perform('admin', [
            'admin_2fa_enabled'      => 1,
            'admin_2fa_secret'       => $encryptedSecret,
            'admin_2fa_last_used'    => null,
            'admin_2fa_last_used_at' => null,
        ], 'update', 'admin_id = "' . $adminId . '"');
    }

    /**
     * Desactiva y limpia todos los datos 2FA de un admin.
     * Usado tanto por disable (cuenta propia) como por reset (superadmin).
     */
    public function deactivate(int $adminId): void
    {
        tep_db_query(
            'UPDATE admin SET admin_2fa_enabled = 0, admin_2fa_secret = NULL,
             admin_2fa_last_used = NULL, admin_2fa_last_used_at = NULL
             WHERE admin_id = "' . $adminId . '"'
        );
        tep_db_query(
            'DELETE FROM admin_2fa_recovery_codes WHERE admin_id = "' . $adminId . '"'
        );
    }

    /**
     * Reset 2FA de un miembro (reinicia grace period).
     */
    public function reset(int $adminId): void
    {
        $this->deactivate($adminId);
        tep_db_perform('admin', [
            'admin_2fa_grace_start' => date('Y-m-d H:i:s'),
        ], 'update', 'admin_id = "' . $adminId . '"');
    }

    /**
     * Registra el ultimo codigo TOTP usado (anti-replay).
     */
    public function updateLastUsed(int $adminId, string $code): void
    {
        tep_db_query(
            'UPDATE admin SET admin_2fa_last_used = "' . tep_db_input($code) . '",
             admin_2fa_last_used_at = "' . date('Y-m-d H:i:s') . '"
             WHERE admin_id = "' . $adminId . '"'
        );
    }

    /**
     * Inicializa la fecha de gracia si no existe (lazy init).
     */
    public function initGracePeriod(int $adminId): string
    {
        $graceStart = date('Y-m-d H:i:s');
        tep_db_perform('admin', [
            'admin_2fa_grace_start' => $graceStart,
        ], 'update', 'admin_id = "' . $adminId . '"');
        return $graceStart;
    }

    /**
     * Obtiene los datos de verificacion TOTP de un admin.
     *
     * @return array{admin_2fa_secret: string, admin_2fa_last_used: string|null, admin_2fa_last_used_at: string|null}
     */
    public function getVerificationData(int $adminId): array
    {
        $result = pharaonix_queryOne(
            'SELECT admin_2fa_secret, admin_2fa_last_used, admin_2fa_last_used_at
             FROM admin WHERE admin_id = "' . $adminId . '"'
        );
        return $result->records;
    }
}
