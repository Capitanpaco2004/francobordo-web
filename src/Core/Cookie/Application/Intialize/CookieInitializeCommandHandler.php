<?php

namespace Oscdenox\Core\Cookie\Application\Intialize;

use Oscdenox\Core\Cookie\Domain\Cookie;

class CookieInitializeCommandHandler
{
	private $initialize;

	public function __construct(CookieInitialize $initialize)
	{
		$this->initialize = $initialize;
	}

	public function __invoke(CookieInitializeCommand $command): Cookie
	{
		$name = $command->name();
		$path = $command->path();
		$expire = $command->expire();
		$secure = $command->secure();

		return $this->initialize->__invoke($name, $path, $expire, $secure);
	}
}
