<?php

namespace Oscdenox\Core\Auth\TwoFactor;

/**
 * Servicio para gestion de codigos de recuperacion 2FA.
 *
 * Responsabilidades:
 * - Generar 10 codigos de recuperacion de 10 caracteres hex (TOTP-04)
 * - Almacenar los codigos hasheados con Argon2id (nunca en texto plano)
 * - Consumir codigos de forma single-use (eliminacion inmediata al verificar)
 * - Consultar el numero de codigos restantes
 *
 * Los codigos se muestran al usuario UNA SOLA VEZ en el setup.
 * Formato: 10 caracteres hexadecimales en mayusculas (ej: "A1B2C3D4E5")
 *
 * Requiere funciones BD del proyecto:
 * - pharaonix_query(string $sql): stdClass con ->records y ->num_rows
 * - tep_db_perform(string $table, array $data, string $action, string $where)
 * - tep_db_query(string $sql): resource
 */
class RecoveryCodeService
{
    /**
     * Genera N codigos de recuperacion, los hashea con Argon2id y los almacena en BD.
     *
     * Elimina todos los codigos previos del admin antes de generar los nuevos.
     * Los codigos en claro se devuelven UNA SOLA VEZ — no se pueden recuperar despues.
     *
     * @param int $adminId ID del miembro admin
     * @param int $count   Numero de codigos a generar (por defecto 10 per TOTP-04)
     * @return array Array de codigos en texto plano (mostrar al usuario una sola vez)
     */
    public function generateCodes(int $adminId, int $count = 10): array
    {
        // Eliminar codigos previos para este admin
        tep_db_query('DELETE FROM admin_2fa_recovery_codes WHERE admin_id = "' . (int)$adminId . '"');

        $plainCodes = [];

        for ($i = 0; $i < $count; $i++) {
            // Generar codigo de 10 chars hexadecimales en mayusculas (5 bytes = 10 hex chars)
            $plain = strtoupper(bin2hex(random_bytes(5)));

            // Hashear con Argon2id — mas seguro que bcrypt para codigos cortos
            $hash = password_hash($plain, PASSWORD_ARGON2ID);

            // Insertar en BD con codigo hasheado (nunca en texto plano)
            tep_db_perform(
                'admin_2fa_recovery_codes',
                [
                    'admin_id'   => $adminId,
                    'code_hash'  => $hash,
                    'created_at' => date('Y-m-d H:i:s'),
                ],
                'insert'
            );

            $plainCodes[] = $plain;
        }

        return $plainCodes;
    }

    /**
     * Consume un codigo de recuperacion (single-use).
     *
     * Itera todos los codigos del admin y verifica con password_verify.
     * Si encuentra coincidencia, elimina el codigo de la BD inmediatamente.
     *
     * Iteramos todos los codigos del admin en lugar de hacer un lookup por hash
     * porque los hashes Argon2id son computacionalmente costosos de comparar
     * y la comparacion directa por hash requeriria conocer el hash de antemano.
     *
     * @param string $inputCode Codigo introducido por el usuario (case-insensitive)
     * @param int    $adminId   ID del miembro admin
     * @return bool true si el codigo era valido y ha sido consumido, false si no
     */
    public function consume(string $inputCode, int $adminId): bool
    {
        $result = pharaonix_query(
            'SELECT id, code_hash FROM admin_2fa_recovery_codes WHERE admin_id = "' . (int)$adminId . '"'
        );

        foreach ($result->records as $row) {
            if (password_verify(strtoupper($inputCode), $row['code_hash'])) {
                // Codigo valido — eliminar inmediatamente (single-use)
                tep_db_query(
                    'DELETE FROM admin_2fa_recovery_codes WHERE id = "' . (int)$row['id'] . '"'
                );
                return true;
            }
        }

        return false;
    }

    /**
     * Devuelve el numero de codigos de recuperacion restantes para un admin.
     *
     * Util para alertar al usuario cuando le quedan pocos codigos (ej: <3).
     *
     * @param int $adminId ID del miembro admin
     * @return int Numero de codigos de recuperacion disponibles
     */
    public function getRemainingCount(int $adminId): int
    {
        $result = tep_db_fetch_array(tep_db_query(
            'SELECT COUNT(*) as cnt FROM admin_2fa_recovery_codes WHERE admin_id = "' . (int)$adminId . '"'
        ));

        return (int)($result['cnt'] ?? 0);
    }
}
