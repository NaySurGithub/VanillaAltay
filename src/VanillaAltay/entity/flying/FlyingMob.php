<?php

declare(strict_types=1);

namespace VanillaAltay\entity\flying;

use VanillaAltay\entity\ai\goal\RandomFlyGoal;
use VanillaAltay\entity\ai\GoalSelector;
use VanillaAltay\entity\IntelligentMob;

abstract class FlyingMob extends IntelligentMob
{
	protected function initEntity(\pocketmine\nbt\tag\CompoundTag $nbt) : void
	{
		parent::initEntity($nbt);
		$this->setHasGravity(false);
	}

	protected function registerGoals(GoalSelector $targetGoals, GoalSelector $goals) : void
	{
		$goals->add(10, new RandomFlyGoal($this->getFlySpeed(), 10));
	}

	protected function getFlySpeed() : float
	{
		return 0.15;
	}

	protected function calculateFallDamage(float $fallDistance) : float
	{
		return 0.0;
	}
}
