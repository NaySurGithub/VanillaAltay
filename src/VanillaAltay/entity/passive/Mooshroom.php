<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Mooshroom extends ConfiguredAnimal
{
	protected const NETWORK_ID = EntityIds::MOOSHROOM;

	protected const NAME = "Mooshroom";

	protected const HEIGHT = 1.3;

	protected const WIDTH = 0.9;

	protected const HEALTH = 10;
}
