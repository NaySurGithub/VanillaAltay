<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Zoglin extends Hoglin
{
	protected const NETWORK_ID = EntityIds::ZOGLIN;

	protected const NAME = "Zoglin";

	protected const SPEED = 0.25;
}
