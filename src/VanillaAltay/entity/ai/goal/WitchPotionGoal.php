<?php

declare(strict_types=1);

namespace VanillaAltay\entity\ai\goal;

use pocketmine\entity\Location;
use pocketmine\entity\projectile\SplashPotion;
use pocketmine\item\PotionType;
use VanillaAltay\entity\ai\Goal;
use VanillaAltay\entity\IntelligentMob;

use function mt_rand;
use function sqrt;

final class WitchPotionGoal implements Goal
{
	private int $cooldown = 0;

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
		$distance = $mob->getPosition()->distanceSquared($target->getPosition());
		$mob->lookAtEntity($target);
		if ($distance > 12 ** 2) {
			$mob->walkToward($target->getPosition(), .2);
			return;
		}$mob->stopHorizontalMovement();
		if ($this->cooldown > 0) {
			return;
		}$type = $distance > 8 ** 2 ? PotionType::SLOWNESS : ($target->getHealth() >= 8 ? PotionType::POISON : (mt_rand(0, 3) === 0 ? PotionType::WEAKNESS : PotionType::HARMING));
		$origin = $mob->getPosition()->add(0, $mob->getEyeHeight(), 0);
		$delta = $target->getPosition()->add(0, $target->getEyeHeight() * .6, 0)->subtractVector($origin);
		$horizontal = sqrt($delta->x ** 2 + $delta->z ** 2);
		$potion = new SplashPotion(Location::fromObject($origin, $mob->getWorld(), $mob->getLocation()->yaw, $mob->getLocation()->pitch), $mob, $type);
		$potion->setMotion($delta->add(0, $horizontal * .2, 0)->normalize()->multiply(.75));
		$potion->spawnToAll();
		$this->cooldown = 60;
	}

	public function stop(IntelligentMob $mob) : void
	{
		$mob->stopHorizontalMovement();
	}
}
