<?php

declare(strict_types=1);

namespace VanillaAltay\entity\aquatic;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Tadpole extends ConfiguredWaterMob
{
	protected const NETWORK_ID = EntityIds::TADPOLE;

	protected const NAME = "Tadpole";

	protected const HEIGHT = 0.8;

	protected const WIDTH = 0.6;

	protected const HEALTH = 6;

	protected const SPEED = 0.1;
}
