<?php

declare(strict_types=1);

namespace VanillaAltay\entity\aquatic;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Cod extends WaterMob
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::COD;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(0.3, 0.6);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 3;
	}

	public function getName() : string
	{
		return "Cod";
	}

	public function getDrops() : array
	{
		return [VanillaItems::RAW_FISH()];
	}

	public function getXpDropAmount() : int
	{
		return 1;
	}
}
