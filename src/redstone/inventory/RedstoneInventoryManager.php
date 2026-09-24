<?php

declare(strict_types=1);

namespace redstone\inventory;

use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\inventory\Inventory;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\plugin\PluginBase;
use RuntimeException;

final class RedstoneInventoryManager{

	public function __construct(PluginBase $loader){
		$hacky_block_inventory_handler = static function(int $id, Inventory $inventory) : ?array{
			return $inventory instanceof HackyBlockInventory ? [
				ContainerOpenPacket::blockInv($id, $inventory->getWindowType(), BlockPosition::fromVector3($inventory->getHolder()))
			] : null;
		};
		$loader->getServer()->getPluginManager()->registerEvent(PlayerJoinEvent::class, static function(PlayerJoinEvent $event) use($hacky_block_inventory_handler) : void{
			$manager = $event->getPlayer()->getNetworkSession()->getInvManager() ?? throw new RuntimeException("Expected non-null inventory manager");

			// shift $hacky_block_inventory_handler to the first position in the callbacks set
			// so the default callback does not override this one
			$callbacks = $manager->getContainerOpenCallbacks();
			$callbacks_list = $callbacks->toArray();
			$callbacks->clear();
			$callbacks->add($hacky_block_inventory_handler, ...$callbacks_list);
		}, EventPriority::MONITOR, $loader);
		$loader->getServer()->getPluginManager()->registerEvents(new TrappedChestListener(), $loader);
	}
}