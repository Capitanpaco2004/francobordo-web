<?php

namespace Oscdenox\Core\Session\Application\RegisterCustomer;

class SessionRegisterCustomerHandler
{
	private $register;

	public function __construct(SessionRegisterCustomer $register)
	{
		$this->register = $register;
	}

	public function __invoke(SessionRegisterCustomerCommand $command)
	{
		$this->register->__invoke($command->customerId());
	}
}
