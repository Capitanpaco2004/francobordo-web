<?php
namespace Oscdenox\Core\Session\Application\DeleteSessions;

use Oscdenox\Core\Session\Application\DeleteSessions\DeleteExpiredSessionsCommand;
use Oscdenox\Core\Session\Infrastructure\Persistence\SessionRepository;

class DeleteExpiredSessionsCommandHandler
{
	private $sessionRepository;

	public function __construct(SessionRepository $sessionRepository)
	{
		$this->sessionRepository = $sessionRepository;
	}

	public function handle(DeleteExpiredSessionsCommand $command): int
	{
		return $this->sessionRepository->deleteExpiredSessions(
			$command->getLifeTime(),
			$command->getLimit()
		);
	}
}
