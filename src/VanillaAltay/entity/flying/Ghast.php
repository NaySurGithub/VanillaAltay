<?php

declare(strict_types=1);

namespace VanillaAltay\entity\flying;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use VanillaAltay\entity\ai\goal\NearestPlayerTargetGoal;
use VanillaAltay\entity\ai\goal\ProjectileAttackGoal;
use VanillaAltay\entity\ai\goal\RandomFlyGoal;
use VanillaAltay\entity\ai\GoalSelector;
use VanillaAltay\entity\projectile\Fireball;

use function mt_rand;

final class Ghast extends FlyingMonster
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::GHAST;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(4, 4);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 10;
	}

	protected function getFlySpeed() : float
	{
		return .15;
	}

	protected function getAttackDamage() : float
	{
		return 6;
	}

	protected function registerGoals(GoalSelector $t, GoalSelector $g) : void
	{
		$t->add(1, new NearestPlayerTargetGoal(64));
		$g->add(1, new ProjectileAttackGoal(Fireball::class, .15, .8, 6, 32, 40));
		$g->add(10, new RandomFlyGoal(.15, 20));
	}

	public function getName() : string
	{
		return "Ghast";
	}

	public function getDrops() : array
	{
		return [VanillaItems::GHAST_TEAR()->setCount(mt_rand(0, 1)),VanillaItems::GUNPOWDER()->setCount(mt_rand(0, 2))];
	}
}
