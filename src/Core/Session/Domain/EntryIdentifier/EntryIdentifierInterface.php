<?php

namespace Oscdenox\Core\Session\Domain\EntryIdentifier;

interface EntryIdentifierInterface
{
	// Patron unico de identificador de sesion aceptado. Es el mismo que ya venian aplicando
	// CookieEntryIdentifierHandler::sane() y RequestPostEntryIdentifierHandler::sane(), pero
	// ahora se aplica en id(), ANTES de que el identificador llegue a ninguna consulta.
	// Cubre los UUID v4 de SessionHandler y los identificadores nativos de PHP
	// (session.sid_bits_per_character 4, 5 y 6). No admite comillas, barras ni espacios.
	const IDENTIFIER_PATTERN = '/^[a-zA-Z0-9,-]{1,128}\z/';

	public function setNext(EntryIdentifierInterface $entryIdentifier): EntryIdentifierInterface;

	public function id(): ?string;

	public function sane(): ?bool;
}
