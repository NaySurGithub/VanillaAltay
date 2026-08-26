<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class ZombieNautilus extends ConfiguredMonster
{
	protected const NETWORK_ID = EntityIds::ZOMBIE_NAUTILUS;

	protected const NAME = "Zombie Nautilus";

	protected const HEIGHT = 0.9;

	protected const WIDTH = 0.9;

	protected const HEALTH = 20;

	protected const SPEED = 0.18;

	protected const DAMAGE = 4.0;
}
