<?php

declare(strict_types=1);

namespace VanillaAltay\entity\flying;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Allay extends FlyingMob
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::ALLAY;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(0.6, 0.6);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 20;
	}

	protected function getFlySpeed() : float
	{
		return 0.1;
	}

	public function getName() : string
	{
		return "Allay";
	}

	public function getDrops() : array
	{
		return [];
	}
}
