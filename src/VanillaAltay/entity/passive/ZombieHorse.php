<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class ZombieHorse extends Horse
{
	protected const NETWORK_ID = EntityIds::ZOMBIE_HORSE;

	protected const NAME = "Zombie Horse";

	protected const HEALTH = 15;
}
