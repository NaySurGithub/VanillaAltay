<?php

declare(strict_types=1);

namespace VanillaAltay\entity\aquatic;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class ElderGuardian extends Guardian
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::ELDER_GUARDIAN;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(1.9975, 1.9975);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 80;
	}

	public function getName() : string
	{
		return "Elder Guardian";
	}
}
