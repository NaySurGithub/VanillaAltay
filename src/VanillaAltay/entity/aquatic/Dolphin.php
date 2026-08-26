<?php

declare(strict_types=1);

namespace VanillaAltay\entity\aquatic;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Dolphin extends ConfiguredWaterMob
{
	protected const NETWORK_ID = EntityIds::DOLPHIN;

	protected const NAME = "Dolphin";

	protected const HEIGHT = 0.6;

	protected const WIDTH = 0.9;

	protected const HEALTH = 10;

	protected const SPEED = 0.2;
}
