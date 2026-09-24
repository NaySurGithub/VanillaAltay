<?php

declare(strict_types=1);

namespace dimension\generator\nether\populator;

use dimension\generator\nether\object\OreGeneratorFeature;

final class NetherGoldOrePopulator extends OreGeneratorFeature{

	public function name() : string{
		return "nether_nether_gold_ore";
	}

	public function getState() : string{
		return "minecraft:nether_gold_ore";
	}

	public function getClusterCount() : int{
		return 10;
	}

	public function getClusterSize() : int{
		return 10;
	}

	public function getMinHeight() : int{
		return 10;
	}

	public function getMaxHeight() : int{
		return 117;
	}

	public function getConcentration() : int{
		return self::TRIANGLE;
	}
}
