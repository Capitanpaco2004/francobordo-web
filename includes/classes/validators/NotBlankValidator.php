<?php
// Alias
namespace util\validators;

/**
 * No puede estar vacio
 */
class NotBlankValidator extends ValidatorBase
{
	/**
	 * Se lanza para validar
	 */
	public function validate($value, $isEmpty = true): bool
	{
		// Si es un campo vacio
		if ($value === false || (empty($value) && '0' != $value)) {
			$this->messageError('VALIDATORS_NOT_BLANK');

			return $this->notValid();
		}

		// Retornamos
		return $this->isValid();
	}
}
