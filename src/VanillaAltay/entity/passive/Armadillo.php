<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Armadillo extends ConfiguredAnimal
{
	protected const NETWORK_ID = EntityIds::ARMADILLO;

	protected const NAME = "Armadillo";

	protected const HEIGHT = 0.65;

	protected const WIDTH = 0.7;

	protected const HEALTH = 12;

	protected const SPEED = 0.14;
}
