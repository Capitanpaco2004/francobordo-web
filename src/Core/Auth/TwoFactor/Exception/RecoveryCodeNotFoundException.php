<?php

namespace Oscdenox\Core\Auth\TwoFactor\Exception;

/**
 * Excepcion lanzada cuando el codigo de recuperacion no existe o ya fue utilizado.
 *
 * Los codigos de recuperacion son single-use: se eliminan de la BD al consumirlos.
 * Si no se encuentra coincidencia, no se puede distinguir entre "no existio nunca"
 * y "ya fue usado" — ambos escenarios son equivalentes desde la perspectiva de seguridad.
 */
class RecoveryCodeNotFoundException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Codigo de recuperacion no encontrado o ya utilizado');
    }
}
