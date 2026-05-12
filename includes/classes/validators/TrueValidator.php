<?php
// Alias
namespace util\validators;

/**
 * Que sea true
 */
class TrueValidator extends ValidatorBase
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
        if (true !== $value && 1 !== $value && '1' !== $value) {
            $this->messageError('VALIDATORS_TRUE');

			return $this->notValid();
        }

        // Retornamos
		return $this->isValid();
    }
}
