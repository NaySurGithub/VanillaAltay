<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Npc extends ConfiguredAnimal
{
	protected const NETWORK_ID = EntityIds::NPC;

	protected const NAME = "NPC";

	protected const HEIGHT = 2.1;

	protected const WIDTH = 0.6;

	protected const HEALTH = 20;

	protected const SPEED = 0.2;
}
