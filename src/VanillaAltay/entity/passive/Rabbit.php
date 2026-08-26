<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Rabbit extends ConfiguredAnimal
{
	protected const NETWORK_ID = EntityIds::RABBIT;

	protected const NAME = "Rabbit";

	protected const HEIGHT = 0.402;

	protected const WIDTH = 0.402;

	protected const HEALTH = 3;

	protected const SPEED = 0.3;
}
