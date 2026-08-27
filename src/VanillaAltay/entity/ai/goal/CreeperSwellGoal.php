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
		return $mob instanceof Creeper &&
			($target = $mob->getTarget()) !== null &&
			$mob->getPosition()->distanceSquared($target->getPosition()) <= 9;
	}

	public function shouldContinue(IntelligentMob $mob) : bool
	{
		return $mob instanceof Creeper &&
			($target = $mob->getTarget()) !== null &&
			$mob->getPosition()->distanceSquared($target->getPosition()) < 49;
	}

	public function start(IntelligentMob $mob) : void
	{
		$this->fuse = 30;
		if ($mob instanceof Creeper) {
			$mob->startSwelling();
		}
	}

	public function tick(IntelligentMob $mob, int $tickDiff) : void
	{
		if (!$mob instanceof Creeper || ($target = $mob->getTarget()) === null) {
			return;
		}
		$mob->lookAtEntity($target);
		$mob->stopHorizontalMovement();
		$this->fuse -= $tickDiff;
		$mob->setSwellTicks(30 - $this->fuse);
		if ($this->fuse <= 0) {
			$mob->explode();
		}
	}

	public function stop(IntelligentMob $mob) : void
	{
		$this->fuse = 30;
		if ($mob instanceof Creeper && !$mob->isFlaggedForDespawn()) {
			$mob->stopSwelling();
		}
		$mob->stopHorizontalMovement();
	}
}
