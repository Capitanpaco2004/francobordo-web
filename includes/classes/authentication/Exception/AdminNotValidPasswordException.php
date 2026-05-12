<?php

namespace util\authentication\Exception;

use util\exceptions\customException;

class AdminNotValidPasswordException extends customException
{
	public function __construct(?string $message = null, int $code = 0)
	{
		parent::__construct('Password no valido');
	}
}
