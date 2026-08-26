<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

use function mt_rand;

final class Vindicator extends Monster
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::VINDICATOR;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(1.9, 0.6);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 24;
	}

	protected function getAttackSpeed() : float
	{
		return 0.35;
	}

	protected function getAttackDamage() : float
	{
		return 5.0;
	}

	public function getName() : string
	{
		return "Vindicator";
	}

	public function getDrops() : array
	{
		return mt_rand(0, 1) === 1 ? [VanillaItems::EMERALD()] : [];
	}

	public function getXpDropAmount() : int
	{
		return 5;
	}
}
