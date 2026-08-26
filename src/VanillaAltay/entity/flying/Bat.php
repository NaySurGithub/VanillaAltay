<?php

declare(strict_types=1);

namespace VanillaAltay\entity\flying;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Bat extends FlyingMob
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::BAT;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(0.9, 0.5);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 6;
	}

	protected function getFlySpeed() : float
	{
		return 0.1;
	}

	public function getName() : string
	{
		return "Bat";
	}

	public function getDrops() : array
	{
		return [];
	}
}
