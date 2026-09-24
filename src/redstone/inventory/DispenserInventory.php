<?php

declare(strict_types=1);

namespace redstone\inventory;

use pocketmine\inventory\SimpleInventory;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\world\Position;

final class DispenserInventory extends SimpleInventory implements HackyBlockInventory{

	protected Position $holder;

	public function __construct(
		Position $holder,
		readonly private int $window_type = WindowTypes::DISPENSER
	){
		parent::__construct(9);
		$this->holder = $holder;
	}

	public function getWindowType() : int{
		return $this->window_type;
	}

	public function getHolder() : Position{
		return $this->holder;
	}
}