<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class PolarBear extends ConfiguredAnimal
{
	protected const NETWORK_ID = EntityIds::POLAR_BEAR;

	protected const NAME = "Polar Bear";

	protected const HEIGHT = 1.4;

	protected const WIDTH = 1.3;

	protected const HEALTH = 30;

	protected const SPEED = 0.2;
}
