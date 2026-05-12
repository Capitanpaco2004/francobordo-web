<?php

namespace Oscdenox\Core\Session\Application\RegisterCustomer;

class SessionRegisterCustomerCommand
{
	private $customerId;

	public function __construct(int $customerId)
	{
		$this->customerId = $customerId;
	}

	public function customerId(): int
	{
		return $this->customerId;
	}
}
