<?php

declare(strict_types=1);

namespace VanillaAltay\entity\ai\goal;

use pocketmine\entity\Living;
use VanillaAltay\entity\ai\Goal;
use VanillaAltay\entity\IntelligentMob;

final class FollowOwnerGoal implements Goal
{
	public function __construct(private float $speed = .25, private float $startDistance = 6, private float $stopDistance = 3)
	{
	}

	public function canStart(IntelligentMob $mob) : bool
	{
		$owner = $mob->getOwningEntity();
		return $owner instanceof Living && $mob->getPosition()->distanceSquared($owner->getPosition()) > $this->startDistance ** 2;
	}

	public function shouldContinue(IntelligentMob $mob) : bool
	{
		$owner = $mob->getOwningEntity();
		return $owner instanceof Living && $mob->getPosition()->distanceSquared($owner->getPosition()) > $this->stopDistance ** 2;
	}

	public function start(IntelligentMob $mob) : void
	{
	}

	public function tick(IntelligentMob $mob, int $tickDiff) : void
	{
		$owner = $mob->getOwningEntity();
		if ($owner instanceof Living) {
			$mob->walkToward($owner->getPosition(), $this->speed);
		}
	}

	public function stop(IntelligentMob $mob) : void
	{
		$mob->stopHorizontalMovement();
	}
}
