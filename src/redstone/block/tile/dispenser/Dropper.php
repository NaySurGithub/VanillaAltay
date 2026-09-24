<?php

declare(strict_types=1);

namespace redstone\block\tile\dispenser;

use pocketmine\block\tile\Container;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\player\Player;
use redstone\inventory\DispenserInventory;

class Dropper extends Dispenser{

	protected function createInventory() : DispenserInventory{
		return new DispenserInventory($this->position, WindowTypes::DROPPER);
	}

	public function getDefaultName() : string{
		return "Dropper";
	}

	protected function dispenseSlot(int $slot, int $facing, ?Player $player) : bool{
		$side_pos = $this->position->getSide($facing);
		$destination = $this->position->world->getTileAt($side_pos->x, $side_pos->y, $side_pos->z);
		if($destination instanceof Container){
			return $this->insertInto($slot, $destination);
		}
		return (new DropDispensableItem())->dispense($this->position, $this->getInventory(), $slot, $side_pos, $facing, $player);
	}

	private function insertInto(int $slot, Container $destination) : bool{
		$inventory = $this->getInventory();
		$destination_inventory = $destination->getInventory();
		$item = $inventory->getItem($slot);
		$item_to_move = (clone $item)->setCount(1);
		if(!$destination_inventory->canAddItem($item_to_move)){
			return false;
		}

		$item->pop();
		$inventory->setItem($slot, $item);
		$destination_inventory->addItem($item_to_move);
		return true;
	}
}
