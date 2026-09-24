<?php

declare(strict_types=1);

namespace redstone\block;

use pocketmine\block\WoodenButton as VanillaWoodenButton;
use redstone\block\power\PowerSource;

class WoodenButton extends VanillaWoodenButton implements PowerSource{
	use ButtonTrait;
	use OptimizedBlockTrait;
}