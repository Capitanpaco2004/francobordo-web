<?php

namespace Oscdenox\Core\Cookie\Application\CalculateLifeTime;

final class CookieCalculateLifeTimeCommand
{
	private $daysOfLife;

	public function __construct(int $daysOfLife)
	{
		$this->daysOfLife = $daysOfLife;
	}

	public function daysOfLife(): int
	{
		return $this->daysOfLife;
	}
}
