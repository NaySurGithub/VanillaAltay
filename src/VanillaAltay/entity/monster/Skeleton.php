<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use VanillaAltay\entity\ai\goal\NearestPlayerTargetGoal;
use VanillaAltay\entity\ai\goal\RandomStrollGoal;
use VanillaAltay\entity\ai\goal\RangedAttackGoal;
use VanillaAltay\entity\ai\GoalSelector;

use function mt_rand;

class Skeleton extends Monster
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::SKELETON;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(1.9, 0.6);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 20;
	}

	protected function burnsInDaylight() : bool
	{
		return true;
	}

	protected function registerGoals(GoalSelector $targetGoals, GoalSelector $goals) : void
	{
		$targetGoals->add(1, new NearestPlayerTargetGoal(40));
		$goals->add(1, new RangedAttackGoal(0.2, 15, 40));
		$goals->add(10, new RandomStrollGoal(0.18, 10));
	}

	public function getName() : string
	{
		return "Skeleton";
	}

	public function getDrops() : array
	{
		return [VanillaItems::BONE()->setCount(mt_rand(0, 2)), VanillaItems::ARROW()->setCount(mt_rand(0, 2))];
	}

	public function getXpDropAmount() : int
	{
		return 5;
	}
}
