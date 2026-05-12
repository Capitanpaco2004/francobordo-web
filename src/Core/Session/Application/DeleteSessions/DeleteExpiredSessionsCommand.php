<?
namespace Oscdenox\Core\Session\Application\DeleteSessions;

class DeleteExpiredSessionsCommand
{
	private $lifeTime;
	private $limit;

	public function __construct(int $lifeTime, ?int $limit)
	{
		$this->lifeTime = $lifeTime;
		$this->limit = $limit;
	}

	public function getLifeTime(): int
	{
		return $this->lifeTime;
	}

	public function getLimit(): ?int
	{
		return $this->limit;
	}
}
