<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Endermite extends Monster
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::ENDERMITE;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(0.3, 0.4);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 8;
	}

	protected function getFollowRange() : float
	{
		return 16;
	}

	protected function getAttackSpeed() : float
	{
		return 0.3;
	}

	protected function getAttackDamage() : float
	{
		return 2.0;
	}

	public function getName() : string
	{
		return "Endermite";
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
