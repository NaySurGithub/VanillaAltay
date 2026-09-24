<?php

declare(strict_types=1);

namespace dimension\rule\block;

use dimension\rule\DimensionLookup;
use pocketmine\block\Lava;

/**
 * Lava that flows eight blocks every ten ticks in the nether, and four blocks
 * every thirty ticks everywhere else.
 */
class DimensionLava extends Lava{

	private const NETHER_TICK_RATE = 10;
	private const NETHER_FLOW_DECAY_PER_BLOCK = 1;

	public function tickRate() : int{
		if(DimensionLookup::isInNether($this->position)){
			return self::NETHER_TICK_RATE;
		}
		return parent::tickRate();
	}

	public function getFlowDecayPerBlock() : int{
		if(DimensionLookup::isInNether($this->position)){
			return self::NETHER_FLOW_DECAY_PER_BLOCK;
		}
		return parent::getFlowDecayPerBlock();
	}
}
