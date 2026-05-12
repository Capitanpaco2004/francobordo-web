<?php

namespace util\minify;

use util\tools;

final class Minify
{
	private static $instance;
	private $configuration;
	private $jsAttributesDefault;
	private $cssAttributesDefault;

	public static function getInstance(): self
	{
		if (!(self::$instance instanceof self))
			self::$instance = new self();

		return self::$instance;
	}

	public function __construct()
	{
		global $request_type;

		$this->configuration = tools::parseConfiguration($this->getKeysConfiguration());
		$this->configuration['MINIFY_DENEGATE_IPS'] = explode("\n", $this->configuration['MINIFY_DENEGATE_IPS']);
		$this->configuration['MINIFY_FILE_JS'] = array_filter(explode("\n", $this->configuration['MINIFY_FILE_JS']), function($v){ return trim($v); });
		$this->configuration['MINIFY_FILE_CSS'] = array_filter(explode("\n", $this->configuration['MINIFY_FILE_CSS']), function($v){ return trim($v); });
		$this->configuration['MINIFY_PATH_REQUEST'] = ($request_type == 'SSL' ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_CATALOG;
		$this->configuration['MINIFY_PATH_REQUEST_JS'] = $this->configuration['MINIFY_PATH_REQUEST'] . 'theme/web/js';
		$this->configuration['MINIFY_PATH_REQUEST_CSS'] = $this->configuration['MINIFY_PATH_REQUEST'] . 'theme/web/css';

		$this->jsAttributesDefault = [
			'type' => 'text/javascript'
		];

		$this->cssAttributesDefault = [
			'rel' => 'stylesheet',
			'type' => 'text/css'
		];
	}

	public function getKeysConfiguration(): array
	{
		return array('MINIFY_ACTIVE', 'MINIFY_ACTIVE_STATIC', 'MINIFY_RELEASE_NUMBER', 'MINIFY_DENEGATE_IPS', 'MINIFY_FILE_JS', 'MINIFY_FILE_CSS', 'MINIFY_ACTIVE_CACHE_RELOAD');
	}

	public function js(): ?string
	{
		if ($this->hasActive()) {
			return $this->jsRenderMinify();
		}

		return $this->jsRenderFiles();
	}

	private function jsRenderFiles(): string
	{
		$files = $this->getFilesJs();

		array_walk($files, function ($value, $key) use (&$return) {
			$return .= $this->jsRender(array_merge($this->jsAttributesDefault, [
				'src' => $this->configuration['MINIFY_PATH_REQUEST_JS'] . '/' . $value
			]));
		});

		return $return;
	}

	private function getFilesJs(): array
	{
		$files = $this->configuration['MINIFY_FILE_JS'];

		return $files;
	}

	private function jsRenderMinify(): string
	{
		return $this->jsRender(array_merge($this->jsAttributesDefault, [
			'src' => $this->configuration['MINIFY_PATH_REQUEST'] . 'javascript.js' . ($this->configuration['MINIFY_ACTIVE_CACHE_RELOAD'] ? '?v=' . $this->configuration['MINIFY_RELEASE_NUMBER'] : '')
		]));
	}

	private function jsRender($attributes): string
	{
		$attributes = $this->renderAttributes($attributes);

		return "<script$attributes></script>";
	}

	private function renderAttributes(array $attributes): string
	{
		array_walk($attributes, function ($value, $key) use (&$return) {
			$return .= " $key=\"$value\"";
		});

		return $return;
	}

	private function hasActive()
	{
		return ($this->configuration['MINIFY_ACTIVE'] && !in_array($_SERVER['REMOTE_ADDR'], $this->configuration['MINIFY_DENEGATE_IPS']));
	}

	public function groupsConfig(): array
	{
		$filesJs = $this->getFilesJs();
		$filesCss = $this->getFilesCss();

		array_walk($filesJs, function (&$value) {
			$value = trim( preg_replace( "/[\r\n\t\s]+/", " ", realpath(DIR_THEME_ROOT) . '/js/' . $value));
		});

		array_walk($filesCss, function (&$value) {
			$value = trim( preg_replace( "/[\r\n\t\s]+/", " ", realpath(DIR_THEME_ROOT) . '/css/' . $value));
		});

		return [
			'js' => $filesJs,
			'css' => $filesCss
		];
	}

	public function hasStaticFile()
	{
		return ($this->configuration['MINIFY_ACTIVE_STATIC'] && !in_array($_SERVER['REMOTE_ADDR'], $this->configuration['MINIFY_DENEGATE_IPS']));
	}

	public function staticFile(array $request, string $type): array
	{
		if (!in_array($type, ['js', 'css'])) {
			throw new Exception('El tipo solo puede ser js o css');
		}

		$config = [
			'js' => ['directory' => 'js', 'file' => 'javascript', 'content-type' => 'text/javascript; charset=utf-8'],
			'css' => ['directory' => 'css', 'file' => 'stylesheet', 'content-type' => 'text/css; charset=utf-8']
		];
		$config = $config[$type];

		$versionFile = $this->getVersionFile($config['directory'] . '/' . $config['file']);

		if ($versionFile === $this->configuration['MINIFY_RELEASE_NUMBER']) {
			$pathCacheFile = DIR_THEME_ROOT . $config['directory'] . '/' . $config['file'] . '-' . $versionFile;

			$cache = file_get_contents($pathCacheFile);

			if ($cache !== '') {
				$request['content'] = $cache;
				$request['headers']['Content-Length'] = filesize($pathCacheFile);
				$request['headers']['Content-Type'] = $config['content-type'];
				$request['headers']['Content-Encoding'] = 'gzip';

				return $request;
			}
		}

		$this->deleteFileCache(DIR_THEME_ROOT . $config['directory'] . '/' . $config['file'] . '-' . $versionFile);
		$request['content'] != '' ? file_put_contents(DIR_THEME_ROOT . $config['directory'] . '/' . $config['file'] . '-' . $this->configuration['MINIFY_RELEASE_NUMBER'], $request['content']) : null;

		return $request;
	}

	private function deleteFileCache($file)
	{
		if (file_exists($file)) {
			unlink($file);
		}
	}

	private function getVersionFile($file): string
	{
		$file = glob(DIR_THEME_ROOT . $file . '-[0-9]*.[0-9]*.[0-9]*');

		if (count($file) > 0) {
			return explode('-', $file[0])[1];
		}

		return '0.0.0';
	}

	private function getFilesCss()
	{
		$files = $this->configuration['MINIFY_FILE_CSS'];

		return $files;
	}

	public function css(): string
	{
		if ($this->hasActive()) {
			return $this->cssRenderMinify();
		}

		return $this->cssRenderFiles();
	}

	private function cssRenderMinify()
	{
		return $this->cssRender(array_merge($this->cssAttributesDefault, [
			'href' => $this->configuration['MINIFY_PATH_REQUEST'] . 'stylesheet.css' . ($this->configuration['MINIFY_ACTIVE_CACHE_RELOAD'] ? '?v=' . $this->configuration['MINIFY_RELEASE_NUMBER'] : '')
		]));
	}

	private function cssRender($attributes): string
	{
		$attributes = $this->renderAttributes($attributes);

		return "<link$attributes/>";
	}

	private function cssRenderFiles()
	{
		$files = $this->getFilesCss();

		array_walk($files, function ($value, $key) use (&$return) {
			$return .= $this->cssRender(array_merge($this->cssAttributesDefault, [
				'href' => $this->configuration['MINIFY_PATH_REQUEST_CSS'] . '/' . $value
			]));
		});

		return $return;
	}
}
