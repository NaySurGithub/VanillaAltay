<?php

declare(strict_types=1);

namespace redstone\inventory;

use Generator;
use pocketmine\block\inventory\ChestInventory;
use pocketmine\block\inventory\DoubleChestInventory;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\Listener;
use pocketmine\inventory\Inventory;
use pocketmine\world\Position;
use redstone\block\TrappedChest;

final class TrappedChestListener implements Listener{

	/**
	 * @param InventoryOpenEvent $event
	 * @priority MONITOR
	 */
	public function onInventoryOpen(InventoryOpenEvent $event) : void{
		$this->notify($event->getInventory());
	}

	/**
	 * @param InventoryCloseEvent $event
	 * @priority MONITOR
	 */
	public function onInventoryClose(InventoryCloseEvent $event) : void{
		$this->notify($event->getInventory());
	}

	private function notify(Inventory $inventory) : void{
		foreach($this->getHolders($inventory) as $holder){
			$block = $holder->world->getBlockAt($holder->x, $holder->y, $holder->z);
			if($block instanceof TrappedChest){
				$block->onViewersChange();
			}
		}
	}

	/**
	 * @return Generator<Position>
	 */
	private function getHolders(Inventory $inventory) : Generator{
		if($inventory instanceof DoubleChestInventory){
			yield $inventory->getLeftSide()->getHolder();
			yield $inventory->getRightSide()->getHolder();
		}elseif($inventory instanceof ChestInventory){
			yield $inventory->getHolder();
		}
	}
}
