<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Creaking extends ConfiguredMonster
{
	protected const NETWORK_ID = EntityIds::CREAKING;

	protected const NAME = "Creaking";

	protected const HEIGHT = 2.5;

	protected const WIDTH = 1.0;

	protected const HEALTH = 1;

	protected const SPEED = 0.4;

	protected const DAMAGE = 3.0;

	protected const FOLLOW = 32.0;
}
