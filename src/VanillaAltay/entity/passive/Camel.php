<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Camel extends RideableAnimal
{
	protected const NETWORK_ID = EntityIds::CAMEL;

	protected const NAME = "Camel";

	protected const HEIGHT = 2.375;

	protected const WIDTH = 1.7;

	protected const HEALTH = 32;

	protected const SPEED = 0.18;
}
