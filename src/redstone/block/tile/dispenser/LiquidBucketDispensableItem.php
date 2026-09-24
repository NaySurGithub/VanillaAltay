<?php

declare(strict_types=1);

namespace redstone\block\tile\dispenser;

use pocketmine\inventory\Inventory;
use pocketmine\item\LiquidBucket;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;

class LiquidBucketDispensableItem extends DropDispensableItem{

	public function dispense(Position $pos, Inventory $inventory, int $slot, Vector3 $side_pos, int $facing, ?Player $player = null) : bool{
		$world = $pos->getWorld();
		if($world->getBlockAt($side_pos->x, $side_pos->y, $side_pos->z)->canBeReplaced()){
			/** @var LiquidBucket $item */
			$item = $inventory->getItem($slot);

			$world->setBlockAt($side_pos->x, $side_pos->y, $side_pos->z, $item->getLiquid());
			$inventory->setItem($slot, VanillaItems::BUCKET()->setCount($item->getCount()));
			return true;
		}

		return parent::dispense($pos, $inventory, $slot, $side_pos, $facing, $player);
	}
}