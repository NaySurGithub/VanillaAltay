<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use VanillaAltay\entity\ai\goal\NearestPlayerTargetGoal;
use VanillaAltay\entity\ai\GoalSelector;

final class Shulker extends ConfiguredMonster
{
	protected const NETWORK_ID = EntityIds::SHULKER;

	protected const NAME = "Shulker";

	protected const HEIGHT = 0.99;

	protected const WIDTH = 0.99;

	protected const HEALTH = 30;

	protected const SPEED = 0.05;

	protected const DAMAGE = 4.0;

	protected const FOLLOW = 16.0;

	protected function registerGoals(GoalSelector $targets, GoalSelector $goals) : void
	{
		$targets->add(1, new NearestPlayerTargetGoal(16));
		$goals->add(1, new \VanillaAltay\entity\ai\goal\ProjectileAttackGoal(\VanillaAltay\entity\projectile\ShulkerBullet::class, 0, .6, 4, 16, 40));
	}
}
