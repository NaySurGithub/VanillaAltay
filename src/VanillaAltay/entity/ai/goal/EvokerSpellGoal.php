<?php

declare(strict_types=1);

namespace VanillaAltay\entity\ai\goal;

use pocketmine\entity\Location;
use VanillaAltay\entity\ai\Goal;
use VanillaAltay\entity\flying\Vex;
use VanillaAltay\entity\IntelligentMob;
use VanillaAltay\entity\projectile\EvocationFang;

use function mt_rand;

final class EvokerSpellGoal implements Goal
{
	private int $cooldown = 0;

	private int $casts = 0;

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
		}$this->cooldown -= $tickDiff;
		$mob->lookAtEntity($target);
		$distance = $mob->getPosition()->distanceSquared($target->getPosition());
		if ($distance > 12 ** 2) {
			$mob->walkToward($target->getPosition(), .18);
			return;
		}$mob->stopHorizontalMovement();
		if ($this->cooldown > 0) {
			return;
		}$direction = $target->getPosition()->subtractVector($mob->getPosition())->normalize();
		for ($i = 1; $i <= 8; $i++) {
			$pos = $mob->getPosition()->add($direction->x * $i, 0, $direction->z * $i);
			$fang = new EvocationFang(Location::fromObject($pos, $mob->getWorld(), $mob->getLocation()->yaw, 0), $mob);
			$fang->spawnToAll();
		}if (++$this->casts % 3 === 0) {
			for ($i = 0; $i < 3; $i++) {
				$vex = new Vex(Location::fromObject($mob->getPosition()->add(mt_rand(-2, 2), 1, mt_rand(-2, 2)), $mob->getWorld()));
				$vex->setTarget($target);
				$vex->spawnToAll();
			}
		}$this->cooldown = 100;
	}

	public function stop(IntelligentMob $mob) : void
	{
		$mob->stopHorizontalMovement();
	}
}
