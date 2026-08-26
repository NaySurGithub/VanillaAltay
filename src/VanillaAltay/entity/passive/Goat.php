<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Goat extends ConfiguredAnimal
{
	protected const NETWORK_ID = EntityIds::GOAT;

	protected const NAME = "Goat";

	protected const HEIGHT = 1.3;

	protected const WIDTH = 0.9;

	protected const HEALTH = 10;

	protected const SPEED = 0.2;
}
