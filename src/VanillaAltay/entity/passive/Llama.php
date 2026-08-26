<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class Llama extends ConfiguredAnimal
{
	protected const NETWORK_ID = EntityIds::LLAMA;

	protected const NAME = "Llama";

	protected const HEIGHT = 1.9;

	protected const WIDTH = 0.6;

	protected const HEALTH = 30;

	protected const SPEED = 0.175;
}
