<?php

declare(strict_types=1);

namespace VanillaAltay\entity\aquatic;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

use function mt_rand;

final class Squid extends WaterMob
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::SQUID;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(0.8, 0.8);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 10;
	}

	public function getName() : string
	{
		return "Squid";
	}

	public function getDrops() : array
	{
		return [VanillaItems::INK_SAC()->setCount(mt_rand(1, 3))];
	}

	public function getXpDropAmount() : int
	{
		return mt_rand(1, 3);
	}
}
