<?php

declare(strict_types=1);

namespace dimension\generator\nether\populator;

use dimension\generator\nether\object\OreGeneratorFeature;

final class AncientDebrisSmallPopulator extends OreGeneratorFeature{

	public function name() : string{
		return "nether_ancientdebris_small";
	}

	public function getState() : string{
		return "minecraft:ancient_debris";
	}

	public function getClusterCount() : int{
		return 3;
	}

	public function getClusterSize() : int{
		return 2;
	}

	public function getMinHeight() : int{
		return 8;
	}

	public function getMaxHeight() : int{
		return 119;
	}

	public function getSkipAir() : float{
		return 1.0;
	}
}
