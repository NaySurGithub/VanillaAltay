<?php

declare(strict_types=1);

namespace redstone\block;

use pocketmine\block\WeightedPressurePlateHeavy as VanillaWeightedPressurePlateHeavy;
use redstone\block\power\PowerSource;

class WeightedPressurePlateHeavy extends VanillaWeightedPressurePlateHeavy implements PowerSource{
	use OptimizedBlockTrait;
	use PressurePlateTrait;

	public function isPressed() : bool{
		return $this->signalStrength > 0;
	}

	public function setPressed(bool $value) : self{
		$this->signalStrength = $value ? 15 : 0;
		return $this;
	}
}