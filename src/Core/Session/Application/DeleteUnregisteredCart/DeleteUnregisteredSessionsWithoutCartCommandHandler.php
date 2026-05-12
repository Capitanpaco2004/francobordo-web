<?php
namespace Oscdenox\Core\Session\Application\DeleteUnregisteredCart;

use Oscdenox\Core\Session\Domain\SessionDeleterInterface;

class DeleteUnregisteredSessionsWithoutCartCommandHandler
{
	private $sessionDeleter;

	// Inyección de dependencias
	public function __construct(SessionDeleterInterface $sessionDeleter)
	{
		$this->sessionDeleter = $sessionDeleter;
	}

	/**
	 * Maneja el comando para eliminar sesiones sin carrito.
	 *
	 * @param DeleteUnregisteredSessionsWithoutCartCommand $command
	 * @return int El número de registros eliminados
	 */
	public function __invoke(DeleteUnregisteredSessionsWithoutCartCommand $command): int
	{
		$lifeTime = $command->getLifeTime();
		$limit = $command->getLimit();

		// Llama a los métodos del repositorio para eliminar sesiones
		$totalDeleted = 0;
		$totalDeleted += $this->sessionDeleter->deleteExpiredSessions($lifeTime, $limit);
		$totalDeleted += $this->sessionDeleter->deleteActiveSessionsWithoutCart($lifeTime, $limit);

		return $totalDeleted;
	}
}
