<?php

declare(strict_types=1);

namespace redstone\inventory;

use pocketmine\block\inventory\BlockInventory;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;

interface HackyBlockInventory extends BlockInventory{

	/**
	 * @return int WindowTypes::*
	 */
	public function getWindowType() : int;
}