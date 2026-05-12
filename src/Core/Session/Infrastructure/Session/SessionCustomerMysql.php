<?php

namespace Oscdenox\Core\Session\Infrastructure\Session;

use Oscdenox\Core\Cookie\Domain\Cookie;
use Oscdenox\Core\Session\Domain\SessionCustomerInterface;
use PDO;
use Ramsey\Uuid\Uuid;
use SessionHandlerInterface;
use util\Query;

class SessionCustomerMysql implements SessionCustomerInterface, SessionHandlerInterface
{
	private $expire;
	private $token;
	private $customersId;
	private $sessionId;
	private $cookie;

	public function __construct(Cookie $cookie)
	{
		$this->cookie = $cookie;
		$this->token = $this->cookie->token;
		$this->expire = $this->cookie->expire();

		session_set_save_handler($this, true);
	}

	public function token(string $token)
	{
		$this->token = $token;
	}

	public function sessionId($sessionId)
	{
		$this->sessionId = $sessionId;
	}

	public function registerUser(int $customersId): void
	{
		global $pdo;

		$row = pharaonix_queryOne('SELECT token FROM customers_session where customers_id = "' . (int)$customersId . '"')->records;
		$token = isset($row['token']) ? $row['token'] : null;

		if ($token) {
			tep_db_query('DELETE FROM customers_session_storage where token = "' . $this->token . '"');

			$this->token = $token;
			$this->cookie->token = $this->token;
		}

		$token = $this->token;

		$pdo->prepare('INSERT INTO customers_session (sesskey, expiry, token, customers_id, user_agent, user_ip) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE token=VALUES(token), customers_id=VALUES(customers_id), user_agent=VALUES(user_agent), user_ip=VALUES(user_ip)')->execute([
			$this->sessionId,
			$this->expire,
			$token,
			(int)$customersId,
			$_SERVER['HTTP_USER_AGENT'],
			$_SERVER['REMOTE_ADDR']
		]);
	}

	public function close(): bool
	{
		return true;
	}

	public function destroy($session_id): bool
	{
		global $pdo;

		$row = pharaonix_queryOne('SELECT token FROM customers_session where sesskey = "' . $session_id . '"')->records;
		$token = $row['token'] ?? null;

		if ($token) {
			$pdo->prepare('DELETE FROM customers_session WHERE sesskey = ?')->execute([$session_id]);
			$pdo->prepare('DELETE FROM customers_session_storage WHERE customers_session_storage.token = ? AND (SELECT COUNT(customers_session.sesskey) FROM customers_session WHERE customers_session.token = customers_session_storage.token) = 0')->execute([$token]);

			return true;
		}

		return false;
	}

	public function gc(int $maxlifetime): int|false
	{
		return false;
	}

	public function open($save_path, $name): bool
	{
		return true;
	}

	public function exists($id): bool
	{
		if (empty($id) || $id === false) {
			return false; // No consultar si no hay sesskey válido
		}

		$query = new Query('');
		$query = $query::select();

		$query->column([
			'cs.sesskey'
		]);

		$query->from('customers_session cs');
		$query->where('cs.sesskey = ? and cs.expiry > ?', [$id, time()]);

		return pharaonix_query($query)->num_rows > 0;
	}

	public function read($session_id): string
	{
		$query = new Query('');
		$query = $query::select();

		$query->column([
			'cs.sesskey',
			'cs.token',
			'cs.expiry',
			'cs.token',
			'cs.customers_id',
			'css.value'
		]);

		$query->from('customers_session cs');
		$query->innerJoin('customers_session_storage css', 'css.token = cs.token');

		$query->where('cs.sesskey = ? and cs.expiry > ?', [$session_id, time()]);

		$rows = pharaonix_queryOne($query)->records;

		if (isset($rows['value'])) {
			$this->sessionId = $rows['sesskey'];
			$this->token = $rows['token'];
			$this->customersId = $rows['customers_id'];

			return $rows['value'];
		}

		return '';
	}

	public function write($session_id, $session_data): bool
	{
		global $pdo;

		$token = $this->cookie->token ?? null;

		$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
		$user_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

		$statement = $pdo->prepare('INSERT INTO customers_session_storage (token, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=VALUES(value)');
		$statement->bindParam(1, $token, \PDO::PARAM_INT);
		$statement->bindParam(2, $session_data, \PDO::PARAM_LOB);
		$statement->execute();

		$token = $token ?? $pdo->lastInsertId();
		$this->cookie->token = $token;

		$statement = $pdo->prepare('INSERT INTO customers_session (sesskey, expiry, token, customers_id, user_agent, user_ip) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE expiry=VALUES(expiry), token=VALUES(token)');
		$statement->bindParam(1, $session_id, \PDO::PARAM_STR);
		$statement->bindParam(2, $this->expire, \PDO::PARAM_INT);
		$statement->bindParam(3, $token, \PDO::PARAM_INT);
		$statement->bindParam(4, $this->customersId, \PDO::PARAM_INT);
		$statement->bindParam(5, $user_agent, \PDO::PARAM_STR);
		$statement->bindParam(6, $user_ip, \PDO::PARAM_STR);
		$statement->execute();

		return true;
	}

	public function savePath($path = ''): string
	{
		return '';
	}
}
