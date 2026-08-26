<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\item\ItemTypeIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Cat extends TameableAnimal
{
	protected const NETWORK_ID = EntityIds::CAT;

	protected const NAME = "Cat";

	protected const HEIGHT = 0.7;

	protected const WIDTH = 0.6;

	protected const HEALTH = 10;

	protected const SPEED = 0.3;

	protected function getTamingItemTypeIds() : array
	{
		return [ItemTypeIds::RAW_FISH,ItemTypeIds::RAW_SALMON];
	}
}
