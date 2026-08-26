<?php

declare(strict_types=1);

namespace VanillaAltay\entity\ai\goal;

use pocketmine\math\Vector3;
use VanillaAltay\entity\ai\Goal;
use VanillaAltay\entity\IntelligentMob;

use function mt_rand;

final class RandomStrollGoal implements Goal
{
	private ?Vector3 $destination = null;

	private int $remainingTicks = 0;

	public function __construct(private float $speed = 0.2, private int $radius = 10)
	{

	}

	public function canStart(IntelligentMob $mob) : bool
	{
		return !$mob->hasLivingTarget() && ($this->destination !== null || mt_rand(0, 99) < 3);
	}

	public function shouldContinue(IntelligentMob $mob) : bool
	{
		return !$mob->hasLivingTarget() && $this->destination !== null && $this->remainingTicks > 0 &&
			$mob->getPosition()->distanceSquared($this->destination) > 1.0;
	}

	public function start(IntelligentMob $mob) : void
	{
		if ($this->destination === null) {
			$this->destination = $mob->getPosition()->add(mt_rand(-$this->radius, $this->radius), 0, mt_rand(-$this->radius, $this->radius));
			$this->remainingTicks = mt_rand(40, 100);
		}
	}

	public function tick(IntelligentMob $mob, int $tickDiff) : void
	{
		if ($this->destination !== null) {
			$mob->walkToward($this->destination, $this->speed);
			$this->remainingTicks -= $tickDiff;
		}
	}

	public function stop(IntelligentMob $mob) : void
	{
		$this->destination = null;
		$this->remainingTicks = 0;
		$mob->stopHorizontalMovement();
	}
}
