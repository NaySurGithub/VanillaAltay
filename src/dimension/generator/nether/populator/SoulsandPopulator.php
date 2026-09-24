<?php

declare(strict_types=1);

namespace dimension\generator\nether\populator;

use dimension\generator\nether\object\OreGeneratorFeature;

final class SoulsandPopulator extends OreGeneratorFeature{

	public function name() : string{
		return "nether_soulsand";
	}

	public function getState() : string{
		return "minecraft:soul_sand";
	}

	public function getClusterCount() : int{
		return 12;
	}

	public function getClusterSize() : int{
		return 12;
	}

	public function getMinHeight() : int{
		return 0;
	}

	public function getMaxHeight() : int{
		return 32;
	}
}
