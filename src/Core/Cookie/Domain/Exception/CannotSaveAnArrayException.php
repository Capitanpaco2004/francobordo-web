<?php

namespace Oscdenox\Core\Cookie\Domain\Exception;

use util\exceptions\customException;

final class CannotSaveAnArrayException extends customException
{
	public function __construct(?string $message = null, int $code = 0)
	{
		parent::__construct('No se puede guardar un array');
	}
}
