<?php

namespace Oscdenox\Adapter\Configuration;

use Oscdenox\Core\Configuration\Domain\ConfigurationInterface;
use util\tools;

class Configuration implements ConfigurationInterface
{
	public function get(string $key, $default = false)
	{
		if (defined($key)) {
			return $this->parseConfigurationDefined($key);
		}

		if (isset($GLOBALS[$key])) {
			return $this->parseConfigurationGlobal($key, $GLOBALS[$key]);
		}

		return $default;
	}

	public function has(string $key): bool
	{
		if (defined($key)) {
			return true;
		}

		if (isset($GLOBALS[$key])) {
			return true;
		}

		return false;
	}

	private function parseConfiguration($key, $value, $defined)
	{
		$config = tools::parseConfiguration([$key => $value], $defined);

		return $config[$key];
	}

	private function parseConfigurationDefined($key)
	{
		return $this->parseConfiguration($key, $key, true);
	}

	private function parseConfigurationGlobal($key, $value)
	{
		return $this->parseConfiguration($key, $value, false);
	}

	public function check(string $key, $valueToCheck): bool
	{
		return $this->get($key) === $valueToCheck;
	}
}
