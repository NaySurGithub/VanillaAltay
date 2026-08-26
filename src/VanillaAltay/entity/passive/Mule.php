<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Mule extends Horse
{
	protected const NETWORK_ID = EntityIds::MULE;

	protected const NAME = "Mule";
}
