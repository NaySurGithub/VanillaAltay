<?php

declare(strict_types=1);

namespace redstone\block\tile\dispenser;

use pocketmine\inventory\Inventory;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;

class DropDispensableItem implements DispensableItem{

	public function dispense(Position $pos, Inventory $inventory, int $slot, Vector3 $side_pos, int $facing, ?Player $player = null) : bool{
		$item = $inventory->getItem($slot);
		$pos->getWorld()->dropItem($side_pos->add(0.3, 0.3, 0.3), $item->pop(), (new Vector3(0.0, 0.0, 0.0))->getSide($facing)->multiply(0.4));
		$inventory->setItem($slot, $item);
		return true;
	}
}