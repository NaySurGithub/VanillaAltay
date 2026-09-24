<?php

declare(strict_types=1);

namespace redstone\block\utils;

use redstone\world\BlockData;

final class PoweredBlockData implements BlockData{

	public function __construct(
		public bool $powered
	){}
}
