<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Sniffer extends ConfiguredAnimal
{
	protected const NETWORK_ID = EntityIds::SNIFFER;

	protected const NAME = "Sniffer";

	protected const HEIGHT = 1.75;

	protected const WIDTH = 1.9;

	protected const HEALTH = 14;

	protected const SPEED = 0.15;
}
