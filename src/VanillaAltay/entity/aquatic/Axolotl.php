<?php

declare(strict_types=1);

namespace VanillaAltay\entity\aquatic;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Axolotl extends ConfiguredWaterMob
{
	protected const NETWORK_ID = EntityIds::AXOLOTL;

	protected const NAME = "Axolotl";

	protected const HEIGHT = 0.42;

	protected const WIDTH = 0.75;

	protected const HEALTH = 14;

	protected const SPEED = 0.1;
}
