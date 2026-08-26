<?php

declare(strict_types=1);

namespace VanillaAltay\entity\ai\goal;

use VanillaAltay\entity\ai\Goal;
use VanillaAltay\entity\IntelligentMob;
use VanillaAltay\entity\monster\Monster;

final class NearestMonsterTargetGoal implements Goal
{
	public function __construct(private float $range = 32)
	{
	}

	public function canStart(IntelligentMob $mob) : bool
	{
		$current = $mob->getTarget();
		if ($current instanceof Monster && $mob->isValidTarget($current, $this->range)) {
			return true;
		}$best = null;
		$distance = $this->range ** 2;
		foreach ($mob->getWorld()->getEntities() as $entity) {
			if (!$entity instanceof Monster || !$mob->isValidTarget($entity, $this->range)) {
				continue;
			}$d = $mob->getPosition()->distanceSquared($entity->getPosition());
			if ($d < $distance) {
				$best = $entity;
				$distance = $d;
			}
		}$mob->setTarget($best);
		return $best !== null;
	}

	public function shouldContinue(IntelligentMob $mob) : bool
	{
		return ($target = $mob->getTarget()) instanceof Monster && $mob->isValidTarget($target, $this->range);
	}

	public function start(IntelligentMob $mob) : void
	{
	}

	public function tick(IntelligentMob $mob, int $tickDiff) : void
	{
	}

	public function stop(IntelligentMob $mob) : void
	{
		$mob->setTarget(null);
	}
}
