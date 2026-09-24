<?php

declare(strict_types=1);

namespace redstone\block;

use pocketmine\block\Redstone as VanillaRedstone;
use redstone\block\power\PowerSource;

class Redstone extends VanillaRedstone implements PowerSource{
	use OptimizedBlockTrait;

	public function getPowerLevel() : int{
		return 15;
	}

	public function getOutputPowerLevel() : int{
		return $this->getPowerLevel();
	}

	public function canPower(int $side) : bool{
		return true;
	}

	public function canStronglyPower(int $side) : bool{
		return false;
	}
}