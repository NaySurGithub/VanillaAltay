<?php

declare(strict_types=1);

namespace dimension\generator\nether\populator;

use dimension\generator\nether\object\OreGeneratorFeature;

final class AncientDebrisLargePopulator extends OreGeneratorFeature{

	public function name() : string{
		return "nether_ancientdebris_large";
	}

	public function getState() : string{
		return "minecraft:ancient_debris";
	}

	public function getClusterCount() : int{
		return 2;
	}

	public function getClusterSize() : int{
		return 3;
	}

	public function getMinHeight() : int{
		return 8;
	}

	public function getMaxHeight() : int{
		return 23;
	}

	public function getSkipAir() : float{
		return 1.0;
	}
}
