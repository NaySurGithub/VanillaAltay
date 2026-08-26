<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Frog extends ConfiguredAnimal
{
	protected const NETWORK_ID = EntityIds::FROG;

	protected const NAME = "Frog";

	protected const HEIGHT = 0.5;

	protected const WIDTH = 0.5;

	protected const HEALTH = 10;

	protected const SPEED = 0.2;
}
