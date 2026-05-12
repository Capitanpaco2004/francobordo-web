<?php

namespace Oscdenox\Core\Session\Domain\EntryIdentifier;

interface EntryIdentifierInterface
{
	public function setNext(EntryIdentifierInterface $entryIdentifier): EntryIdentifierInterface;

	public function id(): ?string;

	public function sane(): ?bool;
}
