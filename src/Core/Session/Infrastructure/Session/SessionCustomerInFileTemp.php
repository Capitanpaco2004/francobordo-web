<?php

namespace Oscdenox\Core\Session\Infrastructure\Session;

use Oscdenox\Core\Cookie\Domain\Cookie;
use Oscdenox\Core\Session\Domain\SessionCustomerInterface;
use ReturnTypeWillChange;
use SessionHandlerInterface;

class SessionCustomerInFileTemp implements SessionCustomerInterface, SessionHandlerInterface
{
	private $expire;
	private $token;
	private $customersId;
	private $sessionId;
	private $cookie;
	private $path;

	public function __construct(Cookie $cookie)
	{
		$this->cookie = $cookie;
		$this->token = null;
		$this->customersId = null;
		$this->expire = null;
		$this->path = getcwd() . '/temp/sessions';

		$this->ensureDirectoryExists();
		$this->cleanFiles();

		session_set_save_handler($this, true);
	}

	public function token(string $token)
	{
		$this->token = null;
	}

	public function sessionId($sessionId)
	{
		$this->sessionId = $sessionId;
	}

	public function registerUser(int $userId): void
	{
	}

	public function savePath($path = ''): string
	{
		return '';
	}

	public function exists($id): bool
	{
		return file_exists($this->path . '/' . $id);
	}

	public function close() : bool
	{
		return true;
	}

	#[ReturnTypeWillChange] public function destroy($session_id)
	{
		unlink($this->path . '/' . $session_id);
	}

	public function gc( int $maxlifetime): int|false
	{
		return true;
	}

	public function open($save_path, $name): bool
	{
		return true;
	}

	public function read($session_id): string|false
	{
		$pathFile = $this->path . '/' . $session_id;

		if (file_exists($pathFile)) {
			$content = file_get_contents($pathFile);

			$this->sessionId = $session_id;
			$this->expire = null;
			$this->token = null;
			$this->customersId = null;

			return $content;
		}

		return '';
	}

	public function write($session_id, $session_data): bool
	{
		$file = fopen($this->path . '/' . $session_id, "w");
		fwrite($file, $session_data);
		fclose($file);

		return true;
	}

	private function ensureDirectoryExists(): void
	{
		if (!is_dir($this->path)) {
			mkdir($this->path);
		}
	}

	private function cleanFiles()
	{
		$handle = opendir($this->path);

		while (false !== ($file = readdir($handle))) {
			if (in_array($file, ['.', '..'])) {
				continue;
			}

			$filelastmodified = filemtime($this->path . '/' . $file);

			if ((time() - $filelastmodified) > 300) {
				unlink($this->path  . '/' .  $file);
			}

		}

		closedir($handle);
	}
}
