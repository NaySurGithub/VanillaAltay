<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Ocelot extends ConfiguredAnimal
{
	protected const NETWORK_ID = EntityIds::OCELOT;

	protected const NAME = "Ocelot";

	protected const HEIGHT = 0.7;

	protected const WIDTH = 0.6;

	protected const HEALTH = 10;

	protected const SPEED = 0.3;
}
