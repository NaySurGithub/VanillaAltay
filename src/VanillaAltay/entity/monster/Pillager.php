<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use VanillaAltay\entity\ai\goal\NearestPlayerTargetGoal;
use VanillaAltay\entity\ai\goal\RandomStrollGoal;
use VanillaAltay\entity\ai\goal\RangedAttackGoal;
use VanillaAltay\entity\ai\GoalSelector;

final class Pillager extends ConfiguredMonster
{
	protected const NETWORK_ID = EntityIds::PILLAGER;

	protected const NAME = "Pillager";

	protected const HEIGHT = 1.9;

	protected const WIDTH = 0.6;

	protected const HEALTH = 24;

	protected const SPEED = 0.23;

	protected const DAMAGE = 5.0;

	protected function registerGoals(GoalSelector $targets, GoalSelector $goals) : void
	{
		$targets->add(1, new NearestPlayerTargetGoal(40));
		$goals->add(1, new RangedAttackGoal(.22, 15, 40));
		$goals->add(10, new RandomStrollGoal(.18, 10));
	}
}
