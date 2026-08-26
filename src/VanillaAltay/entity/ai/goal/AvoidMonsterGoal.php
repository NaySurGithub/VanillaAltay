<?php

declare(strict_types=1);

namespace VanillaAltay\entity\ai\goal;

use VanillaAltay\entity\ai\Goal;
use VanillaAltay\entity\IntelligentMob;
use VanillaAltay\entity\monster\Monster;

final class AvoidMonsterGoal implements Goal
{
	private ?Monster $threat = null;

	public function __construct(private float $range = 10, private float $speed = .25)
	{

	}

	public function canStart(IntelligentMob $mob) : bool
	{
		$this->threat = null;
		$best = $this->range ** 2;
		foreach ($mob->getWorld()->getEntities() as $entity) {
			if (!$entity instanceof Monster) {
				continue;
			}$distance = $mob->getPosition()->distanceSquared($entity->getPosition());
			if ($distance < $best) {
				$best = $distance;
				$this->threat = $entity;
			}
		}return $this->threat !== null;
	}

	public function shouldContinue(IntelligentMob $mob) : bool
	{
		return $this->threat !== null && !$this->threat->isClosed() && $this->threat->isAlive() && $mob->getPosition()->distanceSquared($this->threat->getPosition()) < $this->range ** 2;
	}

	public function start(IntelligentMob $mob) : void
	{
	}

	public function tick(IntelligentMob $mob, int $tickDiff) : void
	{
		if ($this->threat !== null) {
			$away = $mob->getPosition()->subtractVector($this->threat->getPosition());
			$mob->walkToward($mob->getPosition()->addVector($away->normalize()->multiply($this->range)), $this->speed);
		}
	}

	public function stop(IntelligentMob $mob) : void
	{
		$this->threat = null;
		$mob->stopHorizontalMovement();
	}
}
