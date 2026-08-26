<?php

declare(strict_types=1);

namespace VanillaAltay\entity\flying;

use VanillaAltay\entity\ai\goal\FlyingMeleeAttackGoal;
use VanillaAltay\entity\ai\goal\NearestPlayerTargetGoal;
use VanillaAltay\entity\ai\goal\RandomFlyGoal;
use VanillaAltay\entity\ai\GoalSelector;

abstract class FlyingMonster extends FlyingMob
{
	public function isHostile() : bool
	{
		return true;
	}

	protected function registerGoals(GoalSelector $targetGoals, GoalSelector $goals) : void
	{
		$targetGoals->add(1, new NearestPlayerTargetGoal($this->getFollowRange()));
		$goals->add(1, new FlyingMeleeAttackGoal($this->getAttackSpeed(), $this->getAttackDamage()));
		$goals->add(10, new RandomFlyGoal($this->getFlySpeed(), 12));
	}

	protected function getFollowRange() : float
	{
		return 40;
	}

	protected function getAttackSpeed() : float
	{
		return 0.3;
	}

	protected function getAttackDamage() : float
	{
		return 4;
	}
}
