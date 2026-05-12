<?php

namespace util\authentication\Sessions;

use SessionHandlerInterface;

class SessionsMysql implements SessionHandlerInterface
{
	private $expire;

	public function __construct(int $expire)
	{
		$this->expire = $expire;
	}

	public function close(): bool
	{
		return true;
	}

	public function destroy($session_id): bool
	{
		$result = tep_db_query('DELETE FROM sessions where sesskey = "' . tep_db_input($session_id) . '"');

		return $result !== false;
	}

	public function gc($maxlifetime): bool
	{
		$result = tep_db_query('DELETE FROM sessions WHERE expiry < "' . (time() - $maxlifetime) . '"');

		return $result !== false;
	}

	public function open($save_path, $name): bool
	{
		return true;
	}

	public function read($session_id): string
	{
		$value_query = tep_db_query('SELECT value FROM sessions WHERE sesskey = "' . tep_db_input($session_id) . '" AND expiry > "' . time() . '"');
		$value = tep_db_fetch_array($value_query);

		if (isset($value['value'])) {
			return $value['value'];
		}

		return '';
	}

	public function write($session_id, $session_data): bool
	{
		global $pdo;

		$pdo->prepare('INSERT INTO sessions VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE expiry=VALUES(expiry), value=VALUES(value)')->execute([$session_id, $this->expire, $session_data]);

		return true;
	}
}
