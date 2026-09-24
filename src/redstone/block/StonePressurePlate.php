<?php

declare(strict_types=1);

namespace redstone\block;

use pocketmine\block\StonePressurePlate as VanillaStonePressurePlate;
use redstone\block\power\PowerSource;

class StonePressurePlate extends VanillaStonePressurePlate implements PowerSource{
	use PressurePlateTrait;
	use OptimizedBlockTrait;
}