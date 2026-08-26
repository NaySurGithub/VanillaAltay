<?php

declare(strict_types=1);

namespace VanillaAltay\entity\passive;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class WanderingTrader extends Villager
{
	protected const NETWORK_ID = EntityIds::WANDERING_TRADER;

	protected const NAME = "Wandering Trader";

	protected const HEIGHT = 1.9;

	protected const WIDTH = 0.6;

	protected const HEALTH = 20;

	protected const SPEED = 0.2;

	protected function getBreedingItemTypeIds() : array
	{
		return [];
	}
}
