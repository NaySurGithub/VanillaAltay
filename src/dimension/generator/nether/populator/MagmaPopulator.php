<?php

declare(strict_types=1);

namespace dimension\generator\nether\populator;

use dimension\generator\nether\object\OreGeneratorFeature;

final class MagmaPopulator extends OreGeneratorFeature{

	public function name() : string{
		return "nether_magma";
	}

	public function getState() : string{
		return "minecraft:magma";
	}

	public function getClusterCount() : int{
		return 9;
	}

	public function getClusterSize() : int{
		return 28;
	}

	public function getMinHeight() : int{
		return 23;
	}

	public function getMaxHeight() : int{
		return 36;
	}
}
