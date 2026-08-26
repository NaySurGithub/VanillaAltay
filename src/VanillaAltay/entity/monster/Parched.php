<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Parched extends ConfiguredMonster
{
	protected const NETWORK_ID = EntityIds::PARCHED;

	protected const NAME = "Parched";

	protected const HEIGHT = 1.9;

	protected const WIDTH = 0.6;

	protected const HEALTH = 20;

	protected const SPEED = 0.25;

	protected const DAMAGE = 4.0;
}
