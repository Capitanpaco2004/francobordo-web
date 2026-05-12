<?php

namespace Oscdenox\Core\Session\Application\RegisterCustomer;

use Oscdenox\Core\Session\Domain\SessionHandlerInterface;

class SessionRegisterCustomer
{
	private $sessionHandler;

	public function __construct(SessionHandlerInterface $sessionHandler)
	{
		$this->sessionHandler = $sessionHandler;
	}

	public function __invoke(int $customerId)
	{
		$this->sessionHandler->registerUser($customerId);
	}
}
