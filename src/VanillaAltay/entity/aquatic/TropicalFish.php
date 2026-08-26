<?php

declare(strict_types=1);

namespace VanillaAltay\entity\aquatic;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class TropicalFish extends WaterMob
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::TROPICALFISH;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(0.4, 0.5);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 6;
	}

	protected function getSwimSpeed() : float
	{
		return 0.12;
	}

	public function getName() : string
	{
		return "Tropical Fish";
	}

	public function getDrops() : array
	{
		return [VanillaItems::CLOWNFISH()];
	}

	public function getXpDropAmount() : int
	{
		return 1;
	}
}
