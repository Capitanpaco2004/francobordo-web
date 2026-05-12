<?php
namespace Oscdenox\Core\Session\Domain;

interface SessionDeleterInterface
{
	/**
	 * Elimina todas las sesiones expiradas.
	 *
	 * @param int $lifeTime Tiempo en horas para considerar una sesión expirada.
	 * @param int|null $limit Límite de sesiones a eliminar.
	 * @return int Número de sesiones eliminadas.
	 */
	public function deleteExpiredSessions(int $lifeTime, ?int $limit): int;

	/**
	 * Elimina sesiones activas sin carrito.
	 *
	 * @param int $lifeTime Tiempo en horas para considerar una sesión activa.
	 * @param int|null $limit Límite de sesiones a eliminar.
	 * @return int Número de sesiones eliminadas.
	 */
	public function deleteActiveSessionsWithoutCart(int $lifeTime, ?int $limit): int;
}
