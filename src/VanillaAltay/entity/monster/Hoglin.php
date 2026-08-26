<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class Hoglin extends ConfiguredMonster
{
	protected const NETWORK_ID = EntityIds::HOGLIN;

	protected const NAME = "Hoglin";

	protected const HEIGHT = 1.4;

	protected const WIDTH = 1.4;

	protected const HEALTH = 40;

	protected const SPEED = 0.36;

	protected const DAMAGE = 6.0;
}
