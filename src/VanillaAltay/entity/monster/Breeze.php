<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Breeze extends ConfiguredMonster
{
	protected const NETWORK_ID = EntityIds::BREEZE;

	protected const NAME = "Breeze";

	protected const HEIGHT = 1.77;

	protected const WIDTH = 0.6;

	protected const HEALTH = 30;

	protected const SPEED = 0.32;

	protected const DAMAGE = 3.0;

	protected const FOLLOW = 24.0;

	protected function registerGoals(\VanillaAltay\entity\ai\GoalSelector $t, \VanillaAltay\entity\ai\GoalSelector $g) : void
	{
		$t->add(1, new \VanillaAltay\entity\ai\goal\NearestPlayerTargetGoal(24));
		$g->add(1, new \VanillaAltay\entity\ai\goal\ProjectileAttackGoal(\pocketmine\entity\projectile\WindCharge::class, .3, 1.2, 3, 16, 30));
		$g->add(10, new \VanillaAltay\entity\ai\goal\RandomStrollGoal(.2, 12));
	}
}
