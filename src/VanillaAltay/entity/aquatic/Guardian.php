<?php

declare(strict_types=1);

namespace VanillaAltay\entity\aquatic;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use VanillaAltay\entity\ai\goal\GuardianBeamGoal;
use VanillaAltay\entity\ai\goal\NearestPlayerTargetGoal;
use VanillaAltay\entity\ai\goal\RandomSwimGoal;
use VanillaAltay\entity\ai\GoalSelector;

use function mt_rand;

class Guardian extends WaterMob
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::GUARDIAN;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(0.85, 0.85);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 30;
	}

	public function isHostile() : bool
	{
		return true;
	}

	protected function registerGoals(GoalSelector $targetGoals, GoalSelector $goals) : void
	{
		$targetGoals->add(1, new NearestPlayerTargetGoal(16));
		$goals->add(1, new GuardianBeamGoal());
		$goals->add(10, new RandomSwimGoal(0.1));
	}

	public function getName() : string
	{
		return "Guardian";
	}

	public function getDrops() : array
	{
		return [VanillaItems::PRISMARINE_SHARD()->setCount(mt_rand(0, 2)),VanillaItems::PRISMARINE_CRYSTALS()->setCount(mt_rand(0, 1))];
	}

	public function getXpDropAmount() : int
	{
		return 10;
	}
}
