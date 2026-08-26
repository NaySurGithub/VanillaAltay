<?php

declare(strict_types=1);

namespace VanillaAltay\entity\aquatic;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class Nautilus extends ConfiguredWaterMob
{
	protected const NETWORK_ID = EntityIds::NAUTILUS;

	protected const NAME = "Nautilus";

	protected const HEIGHT = 0.8;

	protected const WIDTH = 0.9;

	protected const HEALTH = 20;

	protected const SPEED = 0.12;
}
