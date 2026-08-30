<?php

namespace Oscdenox\Core\Session\Domain\EntryIdentifier;

abstract class AbstractEntryIdentifierHandler implements EntryIdentifierInterface
{
	private $nextIdentifier;

	/**
	 * Un identificador que no cumpla el patron se trata como inexistente: la cadena sigue
	 * al siguiente handler y, si ninguno aporta uno valido, SessionHandler abre sesion nueva.
	 */
	protected function isValidIdentifier($id): bool
	{
		return is_string($id) && preg_match(self::IDENTIFIER_PATTERN, $id) === 1;
	}

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
