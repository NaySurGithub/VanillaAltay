<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

use function mt_rand;

final class Pig extends Animal
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::PIG;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(0.9, 0.9);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 10;
	}

	protected function getBreedingItemTypeIds() : array
	{
		return [ItemTypeIds::CARROT];
	}

	public function getName() : string
	{
		return "Pig";
	}

	public function getDrops() : array
	{
		return [VanillaItems::RAW_PORKCHOP()->setCount(mt_rand(1, 3))];
	}

	public function getXpDropAmount() : int
	{
		return mt_rand(1, 3);
	}
}
