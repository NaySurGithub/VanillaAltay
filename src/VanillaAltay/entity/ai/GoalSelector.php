<?php

declare(strict_types=1);

namespace VanillaAltay\entity\ai;

use VanillaAltay\entity\IntelligentMob;

use function ksort;

final class GoalSelector
{
	/** @var array<int, list<Goal>> */
	private array $goals = [];

	private ?Goal $running = null;

	public function add(int $priority, Goal $goal) : self
	{
		$this->goals[$priority][] = $goal;
		ksort($this->goals);
		return $this;
	}

	public function tick(IntelligentMob $mob, int $tickDiff) : void
	{
		if ($this->running !== null && !$this->running->shouldContinue($mob)) {
			$this->running->stop($mob);
			$this->running = null;
		}

		$next = null;
		foreach ($this->goals as $goals) {
			foreach ($goals as $goal) {
				if ($goal->canStart($mob)) {
					$next = $goal;
					break 2;
				}
			}
		}

		if ($next !== $this->running) {
			$this->running?->stop($mob);
			$this->running = $next;
			$this->running?->start($mob);
		}

		$this->running?->tick($mob, $tickDiff);
	}
}
