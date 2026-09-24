<?php

declare(strict_types=1);

namespace redstone\block\power;

interface VariablePowerSource extends PowerSource{

	public function setPowerLevel(int $level) : void;
}