<?php

namespace Oscdenox\Core\Cookie\Domain;

use Oscdenox\Adapter\Configuration\Configuration;
use Oscdenox\Core\Cookie\Domain\Exception\CannotSaveAnArrayException;
use Oscdenox\Core\Cookie\Domain\Exception\CharacterNotAllowedException;
use util\tools;

class Cookie
{
	private $contents;
	private $name;
	private $expire;
	private $domain;
	private $path;
	private $hasModified;
	private $allowWrite;
	private $salt;
	private $secure;
	private $hasEnabled;
	private $sameSite;
	private $configuration;

	public function __construct(string $name, string $path, ?int $expire, bool $secure, Configuration $configuration)
	{
		$this->configuration = $configuration;
		$this->secure = $secure;

		$this->sameSite = $this->configuration->get('COOKIE_SAMESITE', 'lax');
		$this->domain = $this->resolveDomain();
		$this->path = $this->resolvePath($path);
		$this->hasEnabled = $this->resolveEnabled();

		$this->contents = [];
		$this->hasModified = false;
		$this->allowWrite = true;

		$this->expire = null === $expire ? time() + 1728000 : $expire;

		$this->name = 'Oscdenox-' . md5($name . $this->domain);

		$this->salt = $this->configuration->get('SECURITY_SALT');

		$this->update();
	}

	public function sameSite(): string
	{
		return $this->sameSite;
	}

	public function path(): string
	{
		return $this->path;
	}

	public function secure(): bool
	{
		return $this->secure;
	}

	public function domain(): string
	{
		return $this->domain;
	}

	public function expire(): int
	{
		return $this->expire;
	}

	public function name(): string
	{
		return $this->name;
	}

	public function disallowWrite()
	{
		$this->allowWrite = false;
	}

	public function hasEnabled(): bool
	{
		return $this->hasEnabled;
	}

	public function update()
	{
		if (isset($_COOKIE[$this->name])) {
			$content = tools::decrypt($_COOKIE[$this->name], $this->configuration->get('SECURITY_KEY'));

			/* Get cookie checksum */
			$tmpTab = explode('¤', $content);
			// remove the checksum which is the last element
			array_pop($tmpTab);
			$content_for_checksum = implode('¤', $tmpTab) . '¤';
			$checksum = hash('sha256', $this->salt . $content_for_checksum);

			/* Unserialize cookie content */
			$tmpTab = explode('¤', $content);
			foreach ($tmpTab as $keyAndValue) {
				$tmpTab2 = explode('|', $keyAndValue);

				if (count($tmpTab2) == 2) {
					$this->contents[$tmpTab2[0]] = tools::validate('Serialized', $tmpTab2[1]) ? unserialize($tmpTab2[1]) : $tmpTab2[1];
				}
			}

			/* Check if cookie has not been modified */
			if (!isset($this->contents['checksum']) || $this->contents['checksum'] != $checksum) {
				$this->logout();
			}

			if (!isset($this->contents['date_add'])) {
				$this->contents['date_add'] = date('Y-m-d H:i:s');
			}
		} else {
			$this->contents['date_add'] = date('Y-m-d H:i:s');
		}
	}

	protected function encryptAndSetCookie($cookie = null): bool
	{
// Check the content length
		$length = 0;
		if (!empty($cookie)) {
			if (ini_get('mbstring.func_overload') & 2) {
				$length = mb_strlen($cookie, ini_get('default_charset'));
			} else {
				$length = strlen($cookie);
			}
		}

		if ($length >= 1048576) {
			return false;
		}

		if ($cookie) {
			$content = tools::encrypt($cookie, $this->configuration->get('SECURITY_KEY'));
			$time = $this->expire;
		} else {
			$content = 0;
			$time = 1;
		}

		return $this->createCookie($this->name, $content, $time);
	}

	public function write(): bool
	{
		if (!$this->hasModified || headers_sent() || !$this->allowWrite) {
			return false;
		}

		$previousChecksum = $cookie = '';

		/* Serialize cookie content */
		if (isset($this->contents['checksum'])) {
			$previousChecksum = $this->contents['checksum'];
			unset($this->contents['checksum']);
		}

		foreach ($this->contents as $key => $value) {
			$cookie .= $key . '|' . $value . '¤';
		}

		/* Add checksum to cookie */
		$newChecksum = hash('sha256', $this->salt . $cookie);

		// do not set cookie if the checksum is the same: it means the content has not changed!
		if ($previousChecksum === $newChecksum) {
			return false;
		}

		$cookie .= 'checksum|' . $newChecksum;
		$this->hasModified = false;

		/* Cookies are encrypted for evident security reasons */
		return $this->encryptAndSetCookie($cookie);
	}

	public function logout()
	{
		$this->contents = [];

		$this->encryptAndSetCookie();

		unset($_COOKIE[$this->name]);

		$this->hasModified = true;
	}

	public function __get($key)
	{
		return isset($this->contents[$key]) ? $this->contents[$key] : false;
	}

	public function __isset($key): bool
	{
		return isset($this->contents[$key]);
	}

	public function __set($key, $value)
	{
		if (is_array($value)) {
			throw new CannotSaveAnArrayException ();
		}

		if (is_object($value)) {
			$value = serialize($value);
		}

		if (preg_match('/¤|\|/', $key . $value)) {
			throw new CharacterNotAllowedException();
		}

		if (!$this->hasModified && (!array_key_exists($key, $this->contents) || $this->contents[$key] !== $value)) {
			$this->hasModified = true;
		}

		$this->contents[$key] = $value;
	}

	public function __unset($key)
	{
		if (isset($this->contents[$key])) {
			$this->hasModified = true;
		}

		unset($this->contents[$key]);
	}

	public function __destruct()
	{
		$this->write();
	}

	public function createCookie(string $name, string $content, ?int $expire = null): bool
	{
		$options = [
			'expires' => $expire,
			'path' => $this->path,
			'domain' => $this->domain,
			'secure' => $this->secure,
			'httponly' => true,
			'samesite' => $this->sameSite
		];

		if (isset($expire)) {
			$options['expires'] = $expire;
		}

		return setcookie($name, $content, $options);
	}

	private function resolveDomain(): string
	{
		$cookieDomain = $this->configuration->check('request_type', true) ? $this->configuration->get('HTTP_COOKIE_DOMAIN') : $this->configuration->get('HTTPS_COOKIE_DOMAIN');

		return str_replace('www.', '', $cookieDomain);
	}

	private function resolveEnabled(): bool
	{
		$this->createCookie('TEMPCOOKIE', 'CookieOn', time() + 60 * 60);

		$cookieinfo = $_COOKIE['TEMPCOOKIE'] ?? '';

		if ($cookieinfo == "CookieOn") {
			return true;
		}

		return false;
	}

	private function resolvePath(string $path): string
	{
		$path = trim($path, '/\\') . '/';

		if ($path[0] != '/') {
			$path = '/' . $path;
		}

		$path = rawurlencode($path);

		return str_replace(['%2F', '%7E', '%2B', '%26'], ['/', '~', '+', '&'], $path);
	}
}
