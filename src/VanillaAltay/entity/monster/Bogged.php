<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Bogged extends Skeleton
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::BOGGED;
	}

	protected function getVanillaMaxHealth() : int
	{
		return 16;
	}

	public function getName() : string
	{
		return "Bogged";
	}
}
