<?php

namespace Oscdenox\Core\Cookie\Application\Intialize;

final class CookieInitializeCommand
{
	private $name;
	private $path;
	private $expire;
	private $secure;

	public function __construct(string $name, string $path, ?int $expire, bool $secure)
	{
		$this->name = $name;
		$this->path = $path;
		$this->expire = $expire;
		$this->secure = $secure;
	}

	public function name(): string
	{
		return $this->name;
	}

	public function path(): string
	{
		return $this->path;
	}

	public function expire(): ?int
	{
		return $this->expire;
	}

	public function secure(): bool
	{
		return $this->secure;
	}
}
