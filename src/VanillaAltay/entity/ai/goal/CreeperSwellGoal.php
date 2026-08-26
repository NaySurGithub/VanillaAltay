<?php

declare(strict_types=1);

namespace VanillaAltay\entity\ai\goal;

use VanillaAltay\entity\ai\Goal;
use VanillaAltay\entity\IntelligentMob;
use VanillaAltay\entity\monster\Creeper;

final class CreeperSwellGoal implements Goal
{
	private int $fuse = 30;

	public function canStart(IntelligentMob $mob) : bool
	{
		return $mob instanceof Creeper && $mob->hasLivingTarget();
	}

	public function shouldContinue(IntelligentMob $mob) : bool
	{
		return $mob instanceof Creeper && $mob->hasLivingTarget();
	}

	public function start(IntelligentMob $mob) : void
	{
		$this->fuse = 30;
	}

	public function tick(IntelligentMob $mob, int $tickDiff) : void
	{
		if (!$mob instanceof Creeper || ($target = $mob->getTarget()) === null) {
			return;
		}
		$distance = $mob->getPosition()->distanceSquared($target->getPosition());
		$mob->lookAtEntity($target);
		if ($distance > 9) {
			if ($distance > 49) {
				$this->fuse = 30;
			}
			$mob->walkToward($target->getPosition(), 0.2);
			return;
		}
		$mob->stopHorizontalMovement();
		$this->fuse -= $tickDiff;
		if ($this->fuse <= 0) {
			$mob->explode();
		}
	}

	public function stop(IntelligentMob $mob) : void
	{
		$this->fuse = 30;
		$mob->stopHorizontalMovement();
	}
}
