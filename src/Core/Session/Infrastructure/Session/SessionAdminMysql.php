<?php

namespace Oscdenox\Core\Session\Infrastructure\Session;

use Oscdenox\Core\Cookie\Domain\Cookie;
use Oscdenox\Core\Session\Domain\SessionAdminInterface;
use SessionHandlerInterface;
use util\Query;

class SessionAdminMysql implements SessionAdminInterface, SessionHandlerInterface
{
	private $expire;
	private $cookie;

	public function __construct(Cookie $cookie)
	{
		$this->cookie = $cookie;
		$this->expire = $this->cookie->expire();

		session_set_save_handler($this, true);
	}

	public function close(): bool
	{
		return true;
	}

	public function destroy($session_id): bool
	{
		$result = tep_db_query('DELETE FROM admin_session where sesskey = "' . tep_db_input($session_id) . '"');

		return $result !== false;
	}

	public function gc($maxlifetime): int|false
	{
		$result = tep_db_query('DELETE FROM admin_session WHERE expiry < "' . (time() - $maxlifetime) . '"');

		return $result !== false;
	}

	public function open($save_path, $name): bool
	{
		return true;
	}

	public function exists($id): bool
	{
		$query = new Query('');
		$query = $query::select();

		$query->column([
			'ads.sesskey'
		]);

		$query->from('admin_session ads');
		$query->where('ads.sesskey = ? and ads.expiry > ?', [$id, time()]);

		return pharaonix_query($query)->num_rows > 0;
	}

	public function read($session_id): string
	{
		$value_query = tep_db_query('SELECT value FROM admin_session WHERE sesskey = "' . tep_db_input($session_id) . '" AND expiry > "' . time() . '"');
		$value = tep_db_fetch_array($value_query);

		if (isset($value['value'])) {
			return $value['value'];
		}

		return '';
	}

	public function write($session_id, $session_data): bool
	{
		global $pdo;

		$pdo->prepare('INSERT INTO admin_session VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE expiry=VALUES(expiry), value=VALUES(value)')->execute([$session_id, $this->expire, $session_data]);

		return true;
	}

	public function savePath($path = ''): string
	{
		return '';
	}
}
