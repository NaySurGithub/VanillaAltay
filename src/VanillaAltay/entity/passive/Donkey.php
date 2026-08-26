<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Donkey extends Horse
{
	protected const NETWORK_ID = EntityIds::DONKEY;

	protected const NAME = "Donkey";
}
