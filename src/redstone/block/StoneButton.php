<?php

declare(strict_types=1);

namespace redstone\block;

use pocketmine\block\StoneButton as VanillaStoneButton;
use redstone\block\power\PowerSource;

class StoneButton extends VanillaStoneButton implements PowerSource{
	use ButtonTrait;
	use OptimizedBlockTrait;
}