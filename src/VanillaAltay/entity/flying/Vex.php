<?php

declare(strict_types=1);

namespace VanillaAltay\entity\flying;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Vex extends FlyingMonster
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::VEX;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(0.8, 0.4);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 14;
	}

	protected function getFlySpeed() : float
	{
		return 0.4;
	}

	protected function getAttackSpeed() : float
	{
		return 0.4;
	}

	protected function getAttackDamage() : float
	{
		return 5;
	}

	public function getName() : string
	{
		return "Vex";
	}

	public function getDrops() : array
	{
		return [];
	}

	public function getXpDropAmount() : int
	{
		return 3;
	}
}
