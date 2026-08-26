<?php

declare(strict_types=1);

namespace VanillaAltay\entity\aquatic;

use pocketmine\event\entity\EntityDamageEvent;
use VanillaAltay\entity\ai\goal\RandomSwimGoal;
use VanillaAltay\entity\ai\GoalSelector;
use VanillaAltay\entity\IntelligentMob;

abstract class WaterMob extends IntelligentMob
{
	protected function registerGoals(GoalSelector $targetGoals, GoalSelector $goals) : void
	{
		$goals->add(10, new RandomSwimGoal($this->getSwimSpeed()));
	}

	protected function getSwimSpeed() : float
	{
		return 0.1;
	}

	public function canBreathe() : bool
	{
		return $this->isUnderwater();
	}

	public function onAirExpired() : void
	{
		$this->attack(new EntityDamageEvent($this, EntityDamageEvent::CAUSE_SUFFOCATION, 2));
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool
	{
		$this->setHasGravity(!$this->isUnderwater());
		return parent::entityBaseTick($tickDiff);
	}
}
