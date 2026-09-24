<?php

declare(strict_types=1);

namespace redstone\block;

use pocketmine\block\WoodenPressurePlate as VanillaWoodenPressurePlate;
use redstone\block\power\PowerSource;

class WoodenPressurePlate extends VanillaWoodenPressurePlate implements PowerSource{
	use OptimizedBlockTrait;
	use PressurePlateTrait;
}