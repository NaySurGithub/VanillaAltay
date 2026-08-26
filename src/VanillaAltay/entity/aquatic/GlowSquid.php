<?php

declare(strict_types=1);

namespace VanillaAltay\entity\aquatic;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

use function mt_rand;

final class GlowSquid extends WaterMob
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::GLOW_SQUID;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(0.95, 0.475);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 10;
	}

	protected function getSwimSpeed() : float
	{
		return 0.2;
	}

	public function getName() : string
	{
		return "Glow Squid";
	}

	public function getDrops() : array
	{
		return [VanillaItems::GLOW_INK_SAC()->setCount(mt_rand(1, 3))];
	}

	public function getXpDropAmount() : int
	{
		return mt_rand(1, 3);
	}
}
