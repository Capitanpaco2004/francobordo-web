<?php

namespace Oscdenox\Core\Session\Application\InitializeAdmin;

use Oscdenox\Adapter\Configuration\Configuration;
use Oscdenox\Core\Cookie\Domain\Cookie;
use Oscdenox\Core\Session\Domain\EntryIdentifier\CookieEntryIdentifierHandler;
use Oscdenox\Core\Session\Domain\EntryIdentifier\EntryIdentifierInterface;
use Oscdenox\Core\Session\Domain\EntryIdentifier\RequestGetEntryIdentifierHandler;
use Oscdenox\Core\Session\Domain\EntryIdentifier\RequestPostEntryIdentifierHandler;
use Oscdenox\Core\Session\Domain\SessionAdminInterface;
use Oscdenox\Core\Session\Domain\SessionHandler;

final class SessionAdminMysqlInitializeCommandHandler
{
	private $sessionAdminMysqlInitialize;
	private $configuration;
	private $sessionAdmin;
	private $cookie;

	public function __construct(SessionAdminMysqlInitialize $sessionAdminMysqlInitialize, SessionAdminInterface $sessionAdmin, Configuration $configuration, Cookie $cookie)
	{
		$this->sessionAdminMysqlInitialize = $sessionAdminMysqlInitialize;
		$this->sessionAdmin = $sessionAdmin;
		$this->configuration = $configuration;
		$this->cookie = $cookie;
	}

	public function __invoke(SessionAdminMysqlInitializeCommand $command): SessionHandler
	{
		$entryIdentifier = $this->entryIdentifier($command->name());

		return $this->sessionAdminMysqlInitialize->__invoke($command->name(), false, $this->cookie, $entryIdentifier, $this->sessionAdmin);
	}

	private function entryIdentifier(string $sessionName): EntryIdentifierInterface
	{
		$requestPostEntryIdentifierHandler = new RequestPostEntryIdentifierHandler($sessionName);
		$requestGetEntryIdentifierHandler = new RequestGetEntryIdentifierHandler($sessionName, $this->configuration);
		$cookieEntryIdentifierHandler = new CookieEntryIdentifierHandler($sessionName, $this->cookie, $this->sessionAdmin);

		$cookieEntryIdentifierHandler->setNext($requestGetEntryIdentifierHandler)->setNext($requestPostEntryIdentifierHandler);

		return $cookieEntryIdentifierHandler;
	}
}
