<?php

namespace Oscdenox\Core\Cookie\Domain\Exception;

use util\exceptions\customException;

final class CharacterNotAllowedException extends customException
{
	public function __construct(?string $message = null, int $code = 0)
	{
		parent::__construct('Caracter no permitido');
	}
}
