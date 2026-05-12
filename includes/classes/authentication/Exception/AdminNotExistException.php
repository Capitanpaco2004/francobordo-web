<?php
namespace util\authentication\Exception;

use util\exceptions\customException;

class AdminNotExistException extends customException
{
	public function __construct(?string $message = null, int $code = 0)
	{
		parent::__construct('No existe');
	}
}
