<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Strider extends RideableAnimal
{
	protected const NETWORK_ID = EntityIds::STRIDER;

	protected const NAME = "Strider";

	protected const HEIGHT = 1.7;

	protected const WIDTH = 0.9;

	protected const HEALTH = 20;

	protected const SPEED = 0.175;
}
