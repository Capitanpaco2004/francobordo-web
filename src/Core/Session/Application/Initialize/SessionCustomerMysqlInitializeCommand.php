<?php

namespace Oscdenox\Core\Session\Application\Initialize;

final class SessionCustomerMysqlInitializeCommand
{
	private $name;
	private $expire;
	private $token;
	private $blockSpider;
	public function __construct(string $name, int $expire, string $token, bool $blockSpider)
	{
		$this->name = $name;
		$this->expire = $expire;
		$this->token = $token;
		$this->blockSpider = $blockSpider;
	}

	public function expire(): int
	{
		return $this->expire;
	}

	public function token(): string
	{
		return $this->token;
	}

	public function blockSpider(): bool
	{
		return $this->blockSpider;
	}

	public function name(): string
	{
		return $this->name;
	}
}
