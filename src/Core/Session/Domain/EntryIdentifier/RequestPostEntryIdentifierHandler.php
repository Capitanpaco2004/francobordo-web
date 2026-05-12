<?php

namespace Oscdenox\Core\Session\Domain\EntryIdentifier;

class RequestPostEntryIdentifierHandler extends AbstractEntryIdentifierHandler
{
	private $sessionName;

	public function __construct(string $sessionName)
	{
		$this->sessionName = $sessionName;
	}

	public function id(): ?string
	{
		if (isset($_POST[$this->sessionName])) {
			return $_POST[$this->sessionName];
		}

		return parent::id();
	}

	public function sane(): ?bool
	{
		if (isset($_POST[$this->sessionName]) && preg_match('/^[a-zA-Z0-9,-]+$/', $_POST[$this->sessionName]) == false) {
			unset($_POST[$this->sessionName]);
			return true;
		}

		return parent::sane();
	}
}
