<?php

namespace Oscdenox\Core\Session\Domain\EntryIdentifier;

abstract class AbstractEntryIdentifierHandler implements EntryIdentifierInterface
{
	private $nextIdentifier;

	public function setNext(EntryIdentifierInterface $entryIdentifier): EntryIdentifierInterface
	{
		$this->nextIdentifier = $entryIdentifier;

		return $entryIdentifier;
	}

	public function id(): ?string
	{
		if ($this->nextIdentifier) {
			return $this->nextIdentifier->id();
		}

		return null;
	}

	public function sane(): ?bool
	{
		if ($this->nextIdentifier) {
			return $this->nextIdentifier->sane();
		}

		return null;
	}
}
