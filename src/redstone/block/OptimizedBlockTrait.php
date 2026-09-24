<?php

declare(strict_types=1);

namespace redstone\block;

use redstone\block\utils\HelperUtils;

trait OptimizedBlockTrait{

	public function getSide(int $side, int $step = 1){
		return HelperUtils::getBlockAtSide($this->position, $side, $step);
	}
}