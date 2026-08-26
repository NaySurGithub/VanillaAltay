<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Stray extends Skeleton
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::STRAY;
	}

	public function getName() : string
	{
		return "Stray";
	}
}
