<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Panda extends ConfiguredAnimal
{
	protected const NETWORK_ID = EntityIds::PANDA;

	protected const NAME = "Panda";

	protected const HEIGHT = 1.25;

	protected const WIDTH = 1.3;

	protected const HEALTH = 20;

	protected const SPEED = 0.15;
}
