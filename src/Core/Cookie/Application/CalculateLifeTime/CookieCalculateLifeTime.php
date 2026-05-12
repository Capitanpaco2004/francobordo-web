<?php

namespace Oscdenox\Core\Cookie\Application\CalculateLifeTime;

final class CookieCalculateLifeTime
{
	public function __invoke(int $daysOfLife): int
	{
		return time() + (max($daysOfLife * 24, 1) * 3600);
	}
}
