<?php

declare(strict_types=1);

namespace VanillaAltay\entity\ai\goal;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use VanillaAltay\entity\ai\Goal;
use VanillaAltay\entity\IntelligentMob;

final class WardenAttackGoal implements Goal
{
	private int $meleeCooldown = 0;

	private int $sonicCooldown = 0;

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
		}$this->meleeCooldown -= $tickDiff;
		$this->sonicCooldown -= $tickDiff;
		$distance = $mob->getPosition()->distanceSquared($target->getPosition());
		$mob->lookAtEntity($target);
		if ($distance <= 2.5 ** 2 && $this->meleeCooldown <= 0) {
			$target->attack(new EntityDamageByEntityEvent($mob, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 30));
			$this->meleeCooldown = 18;
			return;
		}if ($distance <= 15 ** 2 && $distance >= 4 ** 2 && $this->sonicCooldown <= 0) {
			$target->attack(new EntityDamageByEntityEvent($mob, $target, EntityDamageEvent::CAUSE_MAGIC, 10));
			$delta = $target->getPosition()->subtractVector($mob->getPosition())->normalize();
			$target->setMotion($delta->multiply(2)->withComponents(null, .5, null));
			$this->sonicCooldown = 200;
			return;
		}$mob->walkToward($target->getPosition(), .3);
	}

	public function stop(IntelligentMob $mob) : void
	{
		$mob->stopHorizontalMovement();
	}
}
