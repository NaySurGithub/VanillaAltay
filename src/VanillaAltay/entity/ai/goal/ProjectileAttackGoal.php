<?php

declare(strict_types=1);

namespace VanillaAltay\entity\ai\goal;

use pocketmine\entity\Location;
use pocketmine\entity\projectile\Projectile;
use VanillaAltay\entity\ai\Goal;
use VanillaAltay\entity\IntelligentMob;

use function method_exists;

final class ProjectileAttackGoal implements Goal
{
	private int $cooldown = 0;

	/** @param class-string<Projectile> $projectileClass */
	public function __construct(private string $projectileClass, private float $moveSpeed, private float $projectileSpeed, private float $damage, private float $range, private int $interval)
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
		}$this->cooldown -= $tickDiff;
		$mob->lookAtEntity($target);
		$distance = $mob->getPosition()->distanceSquared($target->getPosition());
		if ($distance > $this->range ** 2) {
			$mob->walkToward($target->getPosition(), $this->moveSpeed);
			return;
		}$mob->stopHorizontalMovement();
		if ($this->cooldown <= 0) {
			$origin = $mob->getPosition()->add(0, $mob->getEyeHeight(), 0);
			$motion = $target->getPosition()->add(0, $target->getEyeHeight() * .6, 0)->subtractVector($origin)->normalize()->multiply($this->projectileSpeed);
			$class = $this->projectileClass;
			$projectile = new $class(Location::fromObject($origin, $mob->getWorld(), $mob->getLocation()->yaw, $mob->getLocation()->pitch), $mob);
			$projectile->setBaseDamage($this->damage);
			$projectile->setMotion($motion);
			if (method_exists($projectile, "setHomingTarget")) {
				$projectile->setHomingTarget($target);
			}$projectile->spawnToAll();
			$this->cooldown = $this->interval;
		}
	}

	public function stop(IntelligentMob $mob) : void
	{
		$mob->stopHorizontalMovement();
	}
}
