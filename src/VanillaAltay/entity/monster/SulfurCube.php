<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class SulfurCube extends ConfiguredMonster
{
	protected const NETWORK_ID = EntityIds::SULFUR_CUBE;

	protected const NAME = "Sulfur Cube";

	protected const HEIGHT = 0.98;

	protected const WIDTH = 0.98;

	protected const HEALTH = 16;

	protected const SPEED = 0.22;

	protected const DAMAGE = 4.0;
}
