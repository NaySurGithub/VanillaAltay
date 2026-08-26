<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Blaze extends ConfiguredMonster
{
	protected const NETWORK_ID = EntityIds::BLAZE;

	protected const NAME = "Blaze";

	protected const HEIGHT = 1.8;

	protected const WIDTH = 0.5;

	protected const HEALTH = 20;

	protected const SPEED = 0.23;

	protected const DAMAGE = 6.0;

	protected function registerGoals(\VanillaAltay\entity\ai\GoalSelector $t, \VanillaAltay\entity\ai\GoalSelector $g) : void
	{
		$t->add(1, new \VanillaAltay\entity\ai\goal\NearestPlayerTargetGoal(48));
		$g->add(1, new \VanillaAltay\entity\ai\goal\ProjectileAttackGoal(\VanillaAltay\entity\projectile\SmallFireball::class, .2, 1.0, 6, 20, 30));
		$g->add(10, new \VanillaAltay\entity\ai\goal\RandomStrollGoal(.16, 10));
	}
}
