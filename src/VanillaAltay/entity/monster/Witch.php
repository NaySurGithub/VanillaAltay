<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use VanillaAltay\entity\ai\goal\NearestPlayerTargetGoal;
use VanillaAltay\entity\ai\goal\RandomStrollGoal;
use VanillaAltay\entity\ai\goal\WitchPotionGoal;
use VanillaAltay\entity\ai\GoalSelector;

final class Witch extends ConfiguredMonster
{
	protected const NETWORK_ID = EntityIds::WITCH;

	protected const NAME = "Witch";

	protected const HEIGHT = 1.9;

	protected const WIDTH = .6;

	protected const HEALTH = 26;

	protected const SPEED = .23;

	protected const DAMAGE = 4.0;

	protected function registerGoals(GoalSelector $targets, GoalSelector $goals) : void
	{
		$targets->add(1, new NearestPlayerTargetGoal(40));
		$goals->add(1, new WitchPotionGoal());
		$goals->add(10, new RandomStrollGoal(.18, 10));
	}
}
