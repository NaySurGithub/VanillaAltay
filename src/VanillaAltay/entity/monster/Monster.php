<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use VanillaAltay\entity\ai\goal\MeleeAttackGoal;
use VanillaAltay\entity\ai\goal\NearestPlayerTargetGoal;
use VanillaAltay\entity\ai\goal\RandomStrollGoal;
use VanillaAltay\entity\ai\GoalSelector;
use VanillaAltay\entity\IntelligentMob;

abstract class Monster extends IntelligentMob
{
	final public function isHostile() : bool
	{
		return true;
	}

	protected function registerGoals(GoalSelector $targetGoals, GoalSelector $goals) : void
	{
		$targetGoals->add(1, new NearestPlayerTargetGoal($this->getFollowRange()));
		$goals->add(1, new MeleeAttackGoal($this->getAttackSpeed(), $this->getAttackDamage(), $this->getAttackRange(), $this->getAttackInterval(), $this->getAttackEffectFactory()));
		$goals->add(10, new RandomStrollGoal($this->getWanderSpeed(), 10));
	}

	protected function getFollowRange() : float
	{
		return 40.0;
	}

	protected function getAttackSpeed() : float
	{
		return 0.23;
	}

	protected function getAttackDamage() : float
	{
		return 3.0;
	}

	protected function getAttackRange() : float
	{
		return 1.8;
	}

	protected function getAttackInterval() : int
	{
		return 20;
	}

	protected function getWanderSpeed() : float
	{
		return 0.18;
	}

	/** @return null|\Closure() : \pocketmine\entity\effect\EffectInstance */
	protected function getAttackEffectFactory() : ?\Closure
	{
		return null;
	}
}
