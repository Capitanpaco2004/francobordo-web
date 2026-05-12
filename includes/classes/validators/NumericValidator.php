<?php
// Alias
namespace util\validators;

/**
 * Que sea un numero
 */
class NumericValidator extends ValidatorBase
{
    /**
     * Se lanza para validar
     */
    public function validate($value, $isEmpty = true)
    {
        // Si queremos o no saltarnos que el valor este vacio
        if (!$isEmpty && ($value === null || $value === '')) {
            return $this->isValid();
        }

        // Validamos
        if (!is_numeric($value)) {
            $this->messageError('VALIDATORS_NUMERIC');

            return $this->notValid();
        }

        // Retornamos
        return $this->isValid();
    }
}