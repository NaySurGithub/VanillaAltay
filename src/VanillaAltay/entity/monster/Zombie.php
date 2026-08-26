<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

use function mt_rand;

class Zombie extends Monster
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::ZOMBIE;
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

	public function getName() : string
	{
		return "Zombie";
	}

	public function getDrops() : array
	{
		$drops = [VanillaItems::ROTTEN_FLESH()->setCount(mt_rand(0, 2))];
		if (mt_rand(0, 199) < 5) {
			$drops[] = [VanillaItems::IRON_INGOT(), VanillaItems::CARROT(), VanillaItems::POTATO()][mt_rand(0, 2)];
		}
		return $drops;
	}

	public function getXpDropAmount() : int
	{
		return 5;
	}

	public function getPickedItem() : ?Item
	{
		return VanillaItems::ZOMBIE_SPAWN_EGG();
	}
}
