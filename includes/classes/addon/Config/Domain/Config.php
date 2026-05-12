<?php

namespace util\addon\Config\Domain;

use util\strings;
use util\tools;

abstract class Config
{
	private $configs = array();
	private $addon;

	public function __construct(string $addon, ?string $appDirectory = null)
	{
		$this->addon = $addon;

		$http = defined('HTTPS_CATALOG_SERVER') ? HTTPS_CATALOG_SERVER : (defined('HTTPS_SERVER') ? HTTPS_SERVER : 'localhost');
		$rootDirectory = $this->getRootDirectory();

		$moduleName = strings::undoCamelCase(array_values(explode('\\', $addon))[0], '-');

		$moduleDirectory = $rootDirectory . '/includes/vendor/oscdenox/' . $moduleName;
		$moduleDirectory = preg_replace('/\/$/', '',is_link($moduleDirectory) ? readlink($moduleDirectory) : $moduleDirectory);

		$this->configs['module'] = [
			'name' => $moduleName
		];

		$this->configs['directory'] = [
			'root' => $rootDirectory,
			'module' => $moduleDirectory
		];

		$this->configs['http'] = [
			'file' => str_replace('-', '_', $this->configs['module']['name']) . '.php',
			'module' => $http . '/includes/vendor/oscdenox/' . $this->configs['module']['name'],
			'root' => $http
		];

		if (!is_null($appDirectory)) {
			$this->configs['module']['app'] = str_replace($moduleDirectory . '/apps/', '', $appDirectory);

			$this->configs['directory']['app'] = $appDirectory;
			$this->configs['directory']['template'] = $appDirectory . '/templates';
			$this->configs['directory']['translation'] = $appDirectory . '/translations';
			$this->configs['directory']['public'] = $appDirectory . '/public';

			$this->configs['http']['public'] = $http . '/includes/vendor/oscdenox/' . $this->get('module.name') . '/apps/' . $this->get('module.app') . '/public';
		}

		$this->configs = array_merge($this->configs, $this->buildConfig());

		if (file_exists($this->get('directory.app') . '/config/config.ini')) {
			$this->configs = array_merge($this->configs, parse_ini_file($this->get('directory.app') . '/config/config.ini', true));
		}
	}

	private function getRootDirectory(): string
	{
		$directory = dirname(__FILE__);
		$directoryRoot = $directory;

		while (!is_file($directory.'/index.php')) {
			if ($directory === dirname($directory)) {
				return $directoryRoot;
			}

			$directory = dirname($directory);
		}

		return $directory;
	}

	private function buildConfig(): array
	{
		$return = [];
		$keysSettings = method_exists($this->addon, 'getKeysSettings') ? $this->addon::getKeysSettings() : [];

		require_once $this->get('directory.root') . '/cache/cachefile.inc.php';

		foreach ($keysSettings as $key) {
			if (defined($key)) {
				$return[$key] = stripcslashes(constant($key));
			}
		}

        	$return = tools::parseConfiguration($return, false);

		return $return;
	}

	private function search($searchs, array $array)
	{
		if (is_array($searchs)) {
			foreach ($searchs as $search) {
				$array = $this->search($search, $array);
			}

			return $array;
		} else {
			return isset($array[$searchs]) ? $array[$searchs] : false;
		}
	}

	public function get(string $search)
	{
		return $this->search(explode('.', $search), $this->configs);
	}

	public function getAll(): array
	{
		return $this->configs;
	}
}
