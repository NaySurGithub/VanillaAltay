<?php

declare(strict_types=1);

namespace VanillaAltay\entity\ai\goal;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use VanillaAltay\entity\ai\Goal;
use VanillaAltay\entity\IntelligentMob;

final class FlyingMeleeAttackGoal implements Goal
{
	private int $cooldown = 0;

	public function __construct(private float $speed, private float $damage, private int $interval = 20)
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
		$delta = $target->getPosition()->add(0, $target->getEyeHeight() * 0.5, 0)->subtractVector($mob->getPosition());
		$distanceSquared = $delta->lengthSquared();
		$mob->lookAtEntity($target);
		if ($distanceSquared > 2.25) {
			$mob->setMotion($delta->normalize()->multiply($this->speed));
			return;
		}
		$mob->setMotion(\pocketmine\math\Vector3::zero());
		if ($this->cooldown <= 0) {
			$target->attack(new EntityDamageByEntityEvent($mob, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->damage));
			$this->cooldown = $this->interval;
		}
	}

	public function stop(IntelligentMob $mob) : void
	{
		$mob->setMotion(\pocketmine\math\Vector3::zero());
	}
}
