<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use VanillaAltay\entity\ai\goal\MeleeAttackGoal;
use VanillaAltay\entity\ai\goal\NearestMonsterTargetGoal;
use VanillaAltay\entity\ai\goal\RandomStrollGoal;
use VanillaAltay\entity\ai\GoalSelector;

final class IronGolem extends ConfiguredAnimal
{
	protected const NETWORK_ID = EntityIds::IRON_GOLEM;

	protected const NAME = "Iron Golem";

	protected const HEIGHT = 2.9;

	protected const WIDTH = 1.4;

	protected const HEALTH = 100;

	protected const SPEED = .18;

	protected function registerGoals(GoalSelector $targets, GoalSelector $goals) : void
	{
		$targets->add(1, new NearestMonsterTargetGoal(32));
		$goals->add(1, new MeleeAttackGoal(.22, 15, 2.5, 24));
		$goals->add(10, new RandomStrollGoal(.14, 10));
	}
}
