<?php

declare(strict_types=1);

namespace redstone\block\utils;

use redstone\world\BlockData;
use function array_shift;

final class RedstoneTorchBlockData implements BlockData{

	/** @var list<int> */
	public array $counters = [];

	public function __construct(){
	}

	public function count(int $tick) : void{
		$this->counters[] = $tick;
		if(count($this->counters) > 9){
			array_shift($this->counters);
		}
	}

	public function isBurntOut(int $tick) : bool{
		return count($this->counters) >= 8 && $tick - $this->counters[0] <= 60;
	}
}