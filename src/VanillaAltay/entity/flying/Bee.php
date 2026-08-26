<?php

declare(strict_types=1);

namespace VanillaAltay\entity\flying;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Bee extends FlyingMob
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::BEE;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(0.5, 0.55);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 10;
	}

	protected function getFlySpeed() : float
	{
		return 0.3;
	}

	public function getName() : string
	{
		return "Bee";
	}

	public function getDrops() : array
	{
		return [];
	}
}
