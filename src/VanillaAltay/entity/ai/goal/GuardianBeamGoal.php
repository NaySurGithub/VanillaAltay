<?php

declare(strict_types=1);

namespace VanillaAltay\entity\ai\goal;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use VanillaAltay\entity\ai\Goal;
use VanillaAltay\entity\IntelligentMob;

final class GuardianBeamGoal implements Goal
{
	private int $charge = 0;

	public function __construct(private int $chargeTicks = 80, private float $damage = 6.0, private float $range = 15.0)
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
		$this->charge = 0;
	}

	public function tick(IntelligentMob $mob, int $tickDiff) : void
	{
		$target = $mob->getTarget();
		if ($target === null) {
			return;
		}
		$distance = $mob->getPosition()->distanceSquared($target->getPosition());
		if ($distance > $this->range ** 2) {
			$mob->setMotion($target->getPosition()->subtractVector($mob->getPosition())->normalize()->multiply(0.12));
			$this->charge = 0;
			return;
		}
		$mob->setMotion(\pocketmine\math\Vector3::zero());
		$mob->lookAtEntity($target);
		$this->charge += $tickDiff;
		if ($this->charge >= $this->chargeTicks) {
			$target->attack(new EntityDamageByEntityEvent($mob, $target, EntityDamageEvent::CAUSE_MAGIC, $this->damage));
			$this->charge = 0;
		}
	}

	public function stop(IntelligentMob $mob) : void
	{
		$this->charge = 0;
	}
}
