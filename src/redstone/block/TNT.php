<?php

declare(strict_types=1);

namespace redstone\block;

use pocketmine\block\TNT as VanillaTNT;
use redstone\block\power\Powerable;
use redstone\block\power\PowerableTrait;

class TNT extends VanillaTNT implements Powerable{
	use OptimizedBlockTrait;
	use PowerableTrait;

	protected int $activation_delay = 0;
	protected int $deactivation_delay = 0;
	protected bool $requires_strong_power = false;

	public function isPowered() : bool{
		return false;
	}

	protected function onReceivePower(int $power) : void{
		if($power > 0){
			$this->ignite();
		}
	}
}