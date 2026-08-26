<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class CamelHusk extends ConfiguredMonster
{
	protected const NETWORK_ID = EntityIds::CAMEL_HUSK;

	protected const NAME = "Camel Husk";

	protected const HEIGHT = 2.375;

	protected const WIDTH = 1.7;

	protected const HEALTH = 32;

	protected const SPEED = 0.23;

	protected const DAMAGE = 5.0;
}
