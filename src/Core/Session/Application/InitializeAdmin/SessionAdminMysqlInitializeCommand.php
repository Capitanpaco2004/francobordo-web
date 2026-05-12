<?php

namespace Oscdenox\Core\Session\Application\InitializeAdmin;

final class SessionAdminMysqlInitializeCommand
{
	private $name;
	private $expire;

	public function __construct(string $name, int $expire)
	{
		$this->name = $name;
		$this->expire = $expire;
	}

	public function expire(): int
	{
		return $this->expire;
	}

	public function name(): string
	{
		return $this->name;
	}
}
