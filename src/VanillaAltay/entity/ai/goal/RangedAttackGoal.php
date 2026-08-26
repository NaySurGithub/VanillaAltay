<?php

declare(strict_types=1);

namespace VanillaAltay\entity\ai\goal;

use pocketmine\entity\Location;
use pocketmine\entity\projectile\Arrow;
use pocketmine\math\Vector3;
use VanillaAltay\entity\ai\Goal;
use VanillaAltay\entity\IntelligentMob;

use function sqrt;

final class RangedAttackGoal implements Goal
{
	private int $cooldown = 0;

	public function __construct(private float $speed = 0.2, private float $range = 15.0, private int $interval = 40)
	{

	}

	public function canStart(IntelligentMob $mob) : bool
	{
		return $mob->hasLivingTarget();
	}

	public function shouldContinue(IntelligentMob $mob) : bool
	{
		return $mob->hasLivingTarget();
	}

	public function start(IntelligentMob $mob) : void
	{
	}

	public function tick(IntelligentMob $mob, int $tickDiff) : void
	{
		$target = $mob->getTarget();
		if ($target === null) {
			return;
		}
		$this->cooldown -= $tickDiff;
		$distanceSquared = $mob->getPosition()->distanceSquared($target->getPosition());
		$mob->lookAtEntity($target);
		if ($distanceSquared > ($this->range * 0.75) ** 2) {
			$mob->walkToward($target->getPosition(), $this->speed);
		} else {
			$mob->stopHorizontalMovement();
		}
		if ($distanceSquared <= $this->range ** 2 && $this->cooldown <= 0) {
			$origin = $mob->getPosition()->add(0, $mob->getEyeHeight() - 0.1, 0);
			$destination = $target->getPosition()->add(0, $target->getEyeHeight() * 0.7, 0);
			$delta = $destination->subtractVector($origin);
			$horizontal = sqrt(($delta->x ** 2) + ($delta->z ** 2));
			$motion = new Vector3($delta->x, $delta->y + ($horizontal * 0.12), $delta->z);
			$arrow = new Arrow(Location::fromObject($origin, $mob->getWorld(), $mob->getLocation()->yaw, $mob->getLocation()->pitch), $mob, false);
			$arrow->setMotion($motion->normalize()->multiply(1.6));
			$arrow->spawnToAll();
			$this->cooldown = $this->interval;
		}
	}

	public function stop(IntelligentMob $mob) : void
	{
		$mob->stopHorizontalMovement();
	}
}
