<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class PiglinBrute extends Piglin
{
	protected const NETWORK_ID = EntityIds::PIGLIN_BRUTE;

	protected const NAME = "Piglin Brute";

	protected const HEALTH = 50;

	protected const SPEED = 0.35;

	protected const DAMAGE = 10.0;
}
