<?php

namespace Oscdenox\Core\Session\Application\InitializeAdmin;

use Oscdenox\Core\Cookie\Domain\Cookie;
use Oscdenox\Core\Session\Domain\EntryIdentifier\EntryIdentifierInterface;
use Oscdenox\Core\Session\Domain\SessionHandler;
use Oscdenox\Core\Session\Domain\SessionHandlerInterface;

final class SessionAdminMysqlInitialize
{
	public function __invoke(string $name, bool $blockSpider, Cookie $cookie, EntryIdentifierInterface $entryIdentifier, SessionHandlerInterface $sessionHandler): SessionHandler
	{
		$session = new SessionHandler($name, $blockSpider, $cookie, $entryIdentifier, $sessionHandler);
		$session->initialize();

		return $session;
	}
}
