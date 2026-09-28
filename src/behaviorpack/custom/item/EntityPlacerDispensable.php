<?php

declare(strict_types=1);

namespace behaviorpack\custom\item;

use pocketmine\inventory\Inventory;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\Utils;
use pocketmine\world\Position;
use redstone\block\tile\dispenser\DispensableItem;
use redstone\block\tile\dispenser\DropDispensableItem;

/**
 * Dispenses an item with minecraft:entity_placer by spawning its entity in
 * front of the dispenser, or drops it when the facing block is not allowed.
 */
final class EntityPlacerDispensable implements DispensableItem{

	public function __construct(
		private EntityPlacer $placer
	){
	}

	public function dispense(Position $pos, Inventory $inventory, int $slot, Vector3 $side_pos, int $facing, ?Player $player = null) : bool{
		$world = $pos->getWorld();
		$below = $world->getBlock($side_pos->down());
		if(!$this->placer->canDispenseOn($below) && !$this->placer->canDispenseOn($world->getBlock($side_pos))){
			return (new DropDispensableItem())->dispense($pos, $inventory, $slot, $side_pos, $facing, $player);
		}
		$entity = $this->placer->create($world, $side_pos->add(0.5, 0, 0.5), Utils::getRandomFloat() * 360);
		if($entity === null){
			return false;
		}
		$item = $inventory->getItem($slot);
		if($item->hasCustomName()){
			$entity->setNameTag($item->getCustomName());
		}
		$item->pop();
		$inventory->setItem($slot, $item);
		$entity->spawnToAll();
		return true;
	}
}
