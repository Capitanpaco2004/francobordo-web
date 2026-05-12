<?php

namespace Oscdenox\Core\Session\Domain;

interface SessionCustomerInterface extends SessionHandlerInterface
{
	public function registerUser(int $userId): void;
}
