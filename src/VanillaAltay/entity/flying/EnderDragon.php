<?php

declare(strict_types=1);

namespace VanillaAltay\entity\flying;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use VanillaAltay\entity\ai\goal\NearestPlayerTargetGoal;
use VanillaAltay\entity\ai\goal\ProjectileAttackGoal;
use VanillaAltay\entity\ai\goal\RandomFlyGoal;
use VanillaAltay\entity\ai\GoalSelector;
use VanillaAltay\entity\projectile\DragonFireball;

final class EnderDragon extends BossFlyingMob
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::ENDER_DRAGON;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(4, 13);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 200;
	}

	protected function getFlySpeed() : float
	{
		return .35;
	}

	protected function getAttackDamage() : float
	{
		return 10;
	}

	protected function getFollowRange() : float
	{
		return 96;
	}

	protected function registerGoals(GoalSelector $t, GoalSelector $g) : void
	{
		$t->add(1, new NearestPlayerTargetGoal(96));
		$g->add(1, new ProjectileAttackGoal(DragonFireball::class, .3, .8, 10, 40, 50));
		$g->add(10, new RandomFlyGoal(.3, 32));
	}

	public function getName() : string
	{
		return "Ender Dragon";
	}

	public function getDrops() : array
	{
		return [];
	}

	public function getXpDropAmount() : int
	{
		return 12000;
	}
}
