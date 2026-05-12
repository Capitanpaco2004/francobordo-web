<?php
namespace util\validators;

use RuntimeException;

/**
 * Valida longitud máxima de una cadena.
 */
class MaxLengthValidator extends ValidatorBase
{
	private int $limit;

	/**
	 * @param int $limit Número máximo de caracteres
	 */
	public function __construct(int $limit, array $arguments = [])
	{
		$this->limit = $limit;
		parent::__construct($arguments);
	}

	public function validate($value, $bEmpty = true)
	{
		if (!$bEmpty && ($value === null || $value === '')) {
			return $this->isValid();
		}

		if (!is_string($value)) {
			$this->messageError('VALIDATORS_MAX_LENGTH', $this->limit);
			return $this->notValid();
		}

		if (mb_strlen($value) > $this->limit) {
			$this->messageError('VALIDATORS_MAX_LENGTH', $this->limit);
			return $this->notValid();
		}

		return $this->isValid();
	}
}
