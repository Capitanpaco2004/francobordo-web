<?php
namespace Oscdenox\Core\Session\Infrastructure\Session;

use Oscdenox\Core\Session\Domain\SessionDeleterInterface;
use PDO;

class SessionRepository implements SessionDeleterInterface
{
	/**
	 * Elimina todas las sesiones expiradas.
	 *
	 * @param int $lifeTime Tiempo en horas para considerar una sesión expirada.
	 * @param int|null $limit Límite de sesiones a eliminar.
	 * @return int Número de sesiones eliminadas.
	 */
	public function deleteExpiredSessions(int $lifeTime, ?int $limit): int
	{
		$totalDeleted = 0;
		$batchSize = $limit ?? 5000;  // Si no se pasa límite, se usa 5000
		$currentTime = time();
		$lifeTimeSeconds = $lifeTime * 3600;

		// Eliminar sesiones expiradas de la tabla de sesiones de clientes
		$totalDeleted += $this->deleteSessions('customers_session', $currentTime, $lifeTimeSeconds, $batchSize);

		// Eliminar sesiones expiradas de la tabla de sesiones de administradores
		$totalDeleted += $this->deleteSessions('admin_session', $currentTime, $lifeTimeSeconds, $batchSize);

		return $totalDeleted;
	}

	/**
	 * Elimina sesiones activas sin carrito.
	 *
	 * @param int $lifeTime Tiempo en horas para considerar una sesión activa.
	 * @param int|null $limit Límite de sesiones a eliminar.
	 * @return int Número de sesiones eliminadas.
	 */
	public function deleteActiveSessionsWithoutCart(int $lifeTime, ?int $limit): int
	{
		$totalDeleted = 0;
		$batchSize = $limit ?? 5000;  // Si no se pasa límite, se usa 5000
		$currentTime = time();
		$lifeTimeSeconds = $lifeTime * 3600;

		// Eliminar sesiones activas sin carrito (solo clientes)
		$totalDeleted += $this->deleteSessionsWithoutCart('customers_session', $currentTime, $lifeTimeSeconds, $batchSize);

		return $totalDeleted;
	}

	/**
	 * Elimina las sesiones expiradas de una tabla de sesiones específica, de forma optimizada.
	 *
	 * @param string $table Nombre de la tabla de sesiones (customers_session, admin_session).
	 * @param int $currentTime Tiempo actual en timestamp.
	 * @param int $lifeTimeSeconds Tiempo en segundos para considerar una sesión expirada.
	 * @param int $batchSize Tamaño del lote de sesiones a eliminar.
	 * @return int Número de sesiones eliminadas.
	 */
	private function deleteSessions(string $table, int $currentTime, int $lifeTimeSeconds, int $batchSize): int
	{
		// Si la tabla es admin_session, usamos 'sesskey' en lugar de 'token'
		$column = ($table === 'admin_session') ? 'sesskey' : 'token';

		// Paso 1: Obtener los identificadores de las sesiones expiradas (limitados)
		$selectQuery = "SELECT {$column} FROM {$table} WHERE expiry < ? LIMIT {$batchSize}";
		$rows = tep_db_query($selectQuery, [$currentTime]);

		$tokensList = [];
		foreach ($rows as $row) {
			$tokensList[] = $row[$column];
		}

		if (empty($tokensList)) {
			return 0;
		}

		// Paso 2: Formar la consulta de eliminación
		$placeholders = implode(',', array_fill(0, count($tokensList), '?'));

		// Paso 3: Eliminar de la tabla principal
		tep_db_query("DELETE FROM {$table} WHERE {$column} IN ($placeholders)", $tokensList);

		// Paso 4: Eliminar también de customers_session_storage si es sesión de cliente
		if ($table === 'customers_session') {
			tep_db_query("DELETE FROM customers_session_storage WHERE token IN ($placeholders)", $tokensList);
		}

		// Devolvemos el número real de eliminados
		return count($tokensList);
	}

	/**
	 * Elimina las sesiones activas sin carrito.
	 *
	 * @param string $table Nombre de la tabla de sesiones (customers_session).
	 * @param int $currentTime Tiempo actual en timestamp.
	 * @param int $lifeTimeSeconds Tiempo en segundos para considerar una sesión activa.
	 * @param int $batchSize Tamaño del lote de sesiones a eliminar.
	 * @return int Número de sesiones eliminadas.
	 */
	private function deleteSessionsWithoutCart(string $table, int $currentTime, int $lifeTimeSeconds, int $batchSize): int
	{
		$totalDeleted = 0;
		// Ajustamos la consulta con parámetros
		$query = "
        SELECT cs.token, css.value
        FROM {$table} cs
        INNER JOIN customers_session_storage css ON css.token = cs.token
        WHERE cs.customers_id IS NULL AND cs.expiry >= :expiry
        LIMIT :batchSize";

		// Pasamos parámetros de forma correcta
		$sessions = tep_db_query($query, ['expiry' => $currentTime, 'batchSize' => $batchSize]);

		foreach ($sessions as $session) {
			if ($this->isCartEmpty($session['value'], $lifeTimeSeconds)) {
				$totalDeleted++;
				// Eliminar sesión activa sin carrito
				tep_db_query("DELETE FROM {$table} WHERE token = ?", [$session['token']]);
				tep_db_query("DELETE FROM customers_session_storage WHERE token = ?", [$session['token']]);
			}
		}

		return $totalDeleted;
	}

	/**
	 * Verifica si el carrito está vacío.
	 *
	 * @param string $sessionValue Valor de la sesión almacenado.
	 * @param int $lifeTimeSeconds Tiempo en segundos para verificar si el carrito está vacío.
	 * @return bool True si el carrito está vacío, false si no lo está.
	 */
	private function isCartEmpty(string $sessionValue, int $lifeTimeSeconds): bool
	{
		if (empty($sessionValue)) {
			return true;
		}

		session_decode($sessionValue);
		$cartContents = $_SESSION['cart']->contents ?? [];

		return empty($cartContents);
	}
}
