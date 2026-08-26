<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Ravager extends ConfiguredMonster
{
	protected const NETWORK_ID = EntityIds::RAVAGER;

	protected const NAME = "Ravager";

	protected const HEIGHT = 1.9;

	protected const WIDTH = 1.2;

	protected const HEALTH = 100;

	protected const SPEED = 0.4;

	protected const DAMAGE = 12.0;
}
