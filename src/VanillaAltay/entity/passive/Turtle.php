<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Turtle extends ConfiguredAnimal
{
	protected const NETWORK_ID = EntityIds::TURTLE;

	protected const NAME = "Turtle";

	protected const HEIGHT = 0.4;

	protected const WIDTH = 1.2;

	protected const HEALTH = 30;

	protected const SPEED = 0.1;
}
