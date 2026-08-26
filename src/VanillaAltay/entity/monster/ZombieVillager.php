<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class ZombieVillager extends Zombie
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::ZOMBIE_VILLAGER_V2;
	}

	public function getName() : string
	{
		return "Zombie Villager";
	}
}
