<?php

declare(strict_types=1);

namespace VanillaAltay\entity\ai\goal;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use VanillaAltay\entity\ai\Goal;
use VanillaAltay\entity\IntelligentMob;

final class DirectRangedAttackGoal implements Goal
{
	private int $cooldown = 0;

	public function __construct(private float $speed, private float $damage, private float $range, private int $interval, private int $cause = EntityDamageEvent::CAUSE_MAGIC)
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
		} $this->cooldown -= $tickDiff;
		$mob->lookAtEntity($target);
		$distance = $mob->getPosition()->distanceSquared($target->getPosition());
		if ($distance > $this->range ** 2) {
			$mob->walkToward($target->getPosition(), $this->speed);
			return;
		}
		$mob->stopHorizontalMovement();
		if ($this->cooldown <= 0) {
			$target->attack(new EntityDamageByEntityEvent($mob, $target, $this->cause, $this->damage));
			$this->cooldown = $this->interval;
		}
	}

	public function stop(IntelligentMob $mob) : void
	{
		$mob->stopHorizontalMovement();
	}
}
