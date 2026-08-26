<?php

declare(strict_types=1);

namespace VanillaAltay\entity\ai\goal;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use VanillaAltay\entity\ai\Goal;
use VanillaAltay\entity\IntelligentMob;

final class MeleeAttackGoal implements Goal
{
	private int $cooldown = 0;

	public function __construct(
		private float $speed,
		private float $damage,
		private float $range = 1.8,
		private int $interval = 20,
		/** @var null|\Closure() : \pocketmine\entity\effect\EffectInstance */
		private ?\Closure $effectFactory = null,
	) {
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
		if ($distanceSquared > $this->range ** 2) {
			$mob->walkToward($target->getPosition(), $this->speed);
			return;
		}

		$mob->stopHorizontalMovement();
		if ($this->cooldown <= 0) {
			$target->attack(new EntityDamageByEntityEvent($mob, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->damage));
			if ($this->effectFactory !== null && $target->isAlive()) {
				$target->getEffects()->add(($this->effectFactory)());
			}
			$this->cooldown = $this->interval;
		}
	}

	public function stop(IntelligentMob $mob) : void
	{
		$mob->stopHorizontalMovement();
	}
}
