<?php


namespace Oscdenox\Adapter\Session;


use Oscdenox\Core\Session\Application\RegisterCustomer\SessionRegisterCustomer;
use Oscdenox\Core\Session\Application\RegisterCustomer\SessionRegisterCustomerCommand;
use Oscdenox\Core\Session\Application\RegisterCustomer\SessionRegisterCustomerHandler;

class SessionRegisterCustomerBuild
{
	public function __invoke(int $customerID): void
	{
		global $sessionCore;

		$sessionRegister = new SessionRegisterCustomer($sessionCore->sessionHandler());

		$sessionRegisterCustomerHandler = new SessionRegisterCustomerHandler($sessionRegister);
		$sessionRegisterCustomerHandler->__invoke(new SessionRegisterCustomerCommand($customerID));
	}
}
