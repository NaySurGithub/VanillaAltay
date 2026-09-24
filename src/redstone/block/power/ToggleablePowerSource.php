<?php

declare(strict_types=1);

namespace redstone\block\power;

interface ToggleablePowerSource extends PowerSource{

	public function switch(bool $state) : void;
}