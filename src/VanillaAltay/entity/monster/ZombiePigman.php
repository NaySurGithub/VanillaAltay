<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class ZombiePigman extends ConfiguredMonster
{
	protected const NETWORK_ID = EntityIds::ZOMBIE_PIGMAN;

	protected const NAME = "Zombified Piglin";

	protected const HEIGHT = 1.9;

	protected const WIDTH = 0.6;

	protected const HEALTH = 20;

	protected const SPEED = 0.23;

	protected const DAMAGE = 5.0;

	protected function registerGoals(\VanillaAltay\entity\ai\GoalSelector $t, \VanillaAltay\entity\ai\GoalSelector $g) : void
	{
		$g->add(1, new \VanillaAltay\entity\ai\goal\MeleeAttackGoal(.25, 5));
		$g->add(10, new \VanillaAltay\entity\ai\goal\RandomStrollGoal(.18, 10));
	}
}
