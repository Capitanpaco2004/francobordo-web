<?php

namespace Oscdenox\Core\Cookie\Application\Intialize;

use Oscdenox\Adapter\Configuration\Configuration;
use Oscdenox\Core\Cookie\Domain\Cookie;

final class CookieInitialize
{
	private $configure;

	public function __construct(Configuration $configure)
	{
		$this->configure = $configure;
	}

	public function __invoke(string $name, string $path, ?int $expire, bool $secure): Cookie
	{
		return new Cookie($name, $path, $expire, $secure, $this->configure);
	}
}
