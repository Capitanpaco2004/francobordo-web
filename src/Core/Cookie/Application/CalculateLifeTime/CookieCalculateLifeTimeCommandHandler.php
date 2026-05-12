<?php

namespace Oscdenox\Core\Cookie\Application\CalculateLifeTime;

final class CookieCalculateLifeTimeCommandHandler
{
	private $calculateLifeTime;
	public function __construct(CookieCalculateLifeTime $calculateLifeTime)
	{
		$this->calculateLifeTime = $calculateLifeTime;
	}

	public function __invoke(CookieCalculateLifeTimeCommand $command): int
	{
		return $this->calculateLifeTime->__invoke($command->daysOfLife());
	}
}
