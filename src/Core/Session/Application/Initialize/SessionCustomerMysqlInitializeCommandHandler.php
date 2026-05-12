<?php

namespace Oscdenox\Core\Session\Application\Initialize;

use Oscdenox\Adapter\Configuration\Configuration;
use Oscdenox\Core\Cookie\Domain\Cookie;
use Oscdenox\Core\Session\Domain\EntryIdentifier\CookieEntryIdentifierHandler;
use Oscdenox\Core\Session\Domain\EntryIdentifier\EntryIdentifierInterface;
use Oscdenox\Core\Session\Domain\EntryIdentifier\RequestGetEntryIdentifierHandler;
use Oscdenox\Core\Session\Domain\EntryIdentifier\RequestPostEntryIdentifierHandler;
use Oscdenox\Core\Session\Domain\SessionCustomerInterface;
use Oscdenox\Core\Session\Domain\SessionHandler;

final class SessionCustomerMysqlInitializeCommandHandler
{
	private $sessionCustomerMysqlInitialize;
	private $configuration;
	private $sessionCustomer;
	private $cookie;

	public function __construct(SessionCustomerMysqlInitialize $sessionCustomerMysqlInitialize, SessionCustomerInterface $sessionCustomer, Configuration $configuration, Cookie $cookie)
	{
		$this->sessionCustomerMysqlInitialize = $sessionCustomerMysqlInitialize;
		$this->sessionCustomer = $sessionCustomer;
		$this->configuration = $configuration;
		$this->cookie = $cookie;
	}

	public function __invoke(SessionCustomerMysqlInitializeCommand $command): SessionHandler
	{
		$entryIdentifier = $this->entryIdentifier($command->name());

		return $this->sessionCustomerMysqlInitialize->__invoke($command->name(), $command->blockSpider(), $this->cookie, $entryIdentifier, $this->sessionCustomer);
	}

	private function entryIdentifier(string $sessionName): EntryIdentifierInterface
	{
		$requestPostEntryIdentifierHandler = new RequestPostEntryIdentifierHandler($sessionName);
		$requestGetEntryIdentifierHandler = new RequestGetEntryIdentifierHandler($sessionName, $this->configuration);
		$cookieEntryIdentifierHandler = new CookieEntryIdentifierHandler($sessionName, $this->cookie, $this->sessionCustomer);

		$requestPostEntryIdentifierHandler->setNext($requestGetEntryIdentifierHandler)->setNext($cookieEntryIdentifierHandler);

		return $requestPostEntryIdentifierHandler;
	}
}
