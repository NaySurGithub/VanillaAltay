<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class CopperGolem extends ConfiguredAnimal
{
	protected const NETWORK_ID = EntityIds::COPPER_GOLEM;

	protected const NAME = "Copper Golem";

	protected const HEIGHT = 0.98;

	protected const WIDTH = 0.49;

	protected const HEALTH = 12;

	protected const SPEED = 0.2;
}
