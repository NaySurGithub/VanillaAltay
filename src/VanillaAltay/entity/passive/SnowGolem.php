<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class SnowGolem extends ConfiguredAnimal
{
	protected const NETWORK_ID = EntityIds::SNOW_GOLEM;

	protected const NAME = "Snow Golem";

	protected const HEIGHT = 1.8;

	protected const WIDTH = 0.4;

	protected const HEALTH = 4;

	protected const SPEED = 0.2;
}
