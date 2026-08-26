<?php

declare(strict_types=1);

namespace VanillaAltay\entity\flying;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

use function mt_rand;

final class Phantom extends FlyingMonster
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::PHANTOM;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(0.5, 0.9);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 20;
	}

	protected function getFlySpeed() : float
	{
		return 0.1;
	}

	protected function getAttackDamage() : float
	{
		return 6;
	}

	public function getName() : string
	{
		return "Phantom";
	}

	public function getDrops() : array
	{
		return [VanillaItems::PHANTOM_MEMBRANE()->setCount(mt_rand(0, 1))];
	}

	public function getXpDropAmount() : int
	{
		return 5;
	}
}
