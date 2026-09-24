<?php

declare(strict_types=1);

namespace redstone\block\power;

interface Movable{

	public function canBeMoved() : bool;
}