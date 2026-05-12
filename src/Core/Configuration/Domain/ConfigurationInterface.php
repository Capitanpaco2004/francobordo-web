<?php

namespace Oscdenox\Core\Configuration\Domain;

interface ConfigurationInterface
{
	public function get(string $key, $default = false);

	public function has(string $key): bool;

	public function check(string $key, $valueToCheck): bool;
}
