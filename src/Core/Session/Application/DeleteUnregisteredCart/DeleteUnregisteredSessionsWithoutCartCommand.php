<?php
namespace Oscdenox\Core\Session\Application\DeleteUnregisteredCart;

class DeleteUnregisteredSessionsWithoutCartCommand
{
	private $lifeTime;
	private $limit;

	// Constructor
	public function __construct(int $lifeTime, ?int $limit)
	{
		$this->lifeTime = $lifeTime;
		$this->limit = $limit;
	}

	// Métodos de acceso
	public function getLifeTime(): int
	{
		return $this->lifeTime;
	}

	public function getLimit(): ?int
	{
		return $this->limit;
	}
}
