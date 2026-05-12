<?php

namespace Oscdenox\Core\Session\Domain;

interface SessionHandlerInterface
{
	public function savePath($path = ''): string;

	public function exists($id): bool;

	public function gc(int $maxlifetime): int|false;
}
