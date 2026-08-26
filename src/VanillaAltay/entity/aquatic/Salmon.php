<?php

declare(strict_types=1);

namespace VanillaAltay\entity\aquatic;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Salmon extends WaterMob
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::SALMON;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(0.4, 0.7);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 3;
	}

	protected function getSwimSpeed() : float
	{
		return 0.12;
	}

	public function getName() : string
	{
		return "Salmon";
	}

	public function getDrops() : array
	{
		return [VanillaItems::RAW_SALMON()];
	}

	public function getXpDropAmount() : int
	{
		return 1;
	}
}
