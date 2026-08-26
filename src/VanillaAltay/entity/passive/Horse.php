<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class Horse extends RideableAnimal
{
	protected const NETWORK_ID = EntityIds::HORSE;

	protected const NAME = "Horse";

	protected const HEIGHT = 1.6;

	protected const WIDTH = 1.4;

	protected const HEALTH = 30;

	protected const SPEED = 0.225;
}
