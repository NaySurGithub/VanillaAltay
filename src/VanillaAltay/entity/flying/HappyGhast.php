<?php

declare(strict_types=1);

namespace VanillaAltay\entity\flying;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class HappyGhast extends FlyingMob
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::HAPPY_GHAST;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(4, 4);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 20;
	}

	protected function getFlySpeed() : float
	{
		return 0.12;
	}

	public function getName() : string
	{
		return "Happy Ghast";
	}

	public function getDrops() : array
	{
		return [];
	}
}
