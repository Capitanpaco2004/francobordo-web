<?php

namespace Oscdenox\Core\Session\Domain\EntryIdentifier;

use Oscdenox\Core\Cookie\Domain\Cookie;
use Oscdenox\Core\Session\Domain\SessionHandlerInterface;

class CookieEntryIdentifierHandler extends AbstractEntryIdentifierHandler
{
	private $cookie;
	private $sessionHandler;
	private $sessionName;

	public function __construct(string $sessionName, Cookie $cookie, SessionHandlerInterface $sessionHandler)
	{
		$this->cookie = $cookie;
		$this->sessionHandler = $sessionHandler;
		$this->sessionName = $sessionName;
	}

	public function id(): ?string
	{
		$sessionId = $this->cookie->sessionId;

		if (isset($this->cookie->sessionId) && $this->isValidIdentifier($sessionId) && $this->sessionHandler->exists($sessionId)) {
			return $sessionId;
		}

		return parent::id();
	}

	public function sane(): ?bool
	{
		$name = $this->sessionName;

		if (isset($_COOKIE[$name]) && preg_match('/^[a-zA-Z0-9,-]+$/', $_COOKIE[$name]) == false) {
			$this->cookie->createCookie($name, '', time() - 42000);

			unset($_COOKIE[$name]);

			return true;
		}

		return parent::sane();
	}
}
