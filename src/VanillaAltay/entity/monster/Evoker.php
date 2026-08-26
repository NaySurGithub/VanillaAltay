<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use VanillaAltay\entity\ai\goal\EvokerSpellGoal;
use VanillaAltay\entity\ai\goal\NearestPlayerTargetGoal;
use VanillaAltay\entity\ai\goal\RandomStrollGoal;
use VanillaAltay\entity\ai\GoalSelector;

final class Evoker extends ConfiguredMonster
{
	protected const NETWORK_ID = EntityIds::EVOCATION_ILLAGER;

	protected const NAME = "Evoker";

	protected const HEIGHT = 1.9;

	protected const WIDTH = .6;

	protected const HEALTH = 24;

	protected const SPEED = .23;

	protected const DAMAGE = 6.0;

	protected function registerGoals(GoalSelector $targets, GoalSelector $goals) : void
	{
		$targets->add(1, new NearestPlayerTargetGoal(32));
		$goals->add(1, new EvokerSpellGoal());
		$goals->add(10, new RandomStrollGoal(.16, 10));
	}
}
