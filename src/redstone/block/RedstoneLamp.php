<?php

declare(strict_types=1);

namespace redstone\block;

use pocketmine\block\RedstoneLamp as VanillaRedstoneLamp;
use redstone\block\power\Powerable;
use redstone\block\power\PowerableTrait;

class RedstoneLamp extends VanillaRedstoneLamp implements Powerable{
	use OptimizedBlockTrait;
	use PowerableTrait;

	protected int $activation_delay = 0;
	protected int $deactivation_delay = 2;
	protected bool $requires_strong_power = true;

	protected function onReceivePower(int $power) : void{
		$powered = $power > 0;
		if($powered !== $this->powered){
			$this->position->world->setBlockAt($this->position->x, $this->position->y, $this->position->z, $this->setPowered($powered), false);
		}
	}
}