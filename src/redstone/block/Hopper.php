<?php

declare(strict_types=1);

namespace redstone\block;

use pocketmine\block\Hopper as VanillaHopper;
use pocketmine\block\inventory\BrewingStandInventory;
use pocketmine\block\inventory\FurnaceInventory;
use pocketmine\block\Jukebox;
use pocketmine\block\tile\BrewingStand as BrewingStandTile;
use pocketmine\block\tile\Container;
use pocketmine\block\tile\Furnace as FurnaceTile;
use pocketmine\block\tile\Hopper as HopperTile;
use pocketmine\block\tile\Jukebox as JukeboxTile;
use pocketmine\block\tile\Tile;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\block\BlockItemPickupEvent;
use pocketmine\inventory\Inventory;
use pocketmine\item\Bucket;
use pocketmine\item\GlassBottle;
use pocketmine\item\Item;
use pocketmine\item\Potion;
use pocketmine\item\Record;
use pocketmine\item\SplashPotion;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use redstone\block\power\Powerable;
use redstone\block\power\PowerableTrait;
use ReflectionProperty;
use function min;

class Hopper extends VanillaHopper implements Powerable{
	use OptimizedBlockTrait;
	use PowerableTrait;

	public const TRANSFER_COOLDOWN = 8;

	private const BOWL_DEPTH = 6 / 16;

	protected int $activation_delay = 0;
	protected int $deactivation_delay = 0;
	protected bool $requires_strong_power = false;

	protected function onReceivePower(int $power) : void{
		$powered = $power > 0;
		if($powered !== $this->powered){
			$this->powered = $powered;
			$this->position->world->setBlockAt($this->position->x, $this->position->y, $this->position->z, $this, false);
		}
	}

	public function onPostPlace() : void{
		$this->recalculatePowerState();
		$this->position->world->scheduleDelayedBlockUpdate($this->position, 1);
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->world;
		$tile = $world->getTile($this->position);
		if(!($tile instanceof HopperTile)){
			return;
		}

		$world->scheduleDelayedBlockUpdate($this->position, 1);
		if($this->powered){
			return;
		}

		$cooldown = self::getTransferCooldown($tile);
		if($cooldown > 0){
			self::setTransferCooldown($tile, --$cooldown);
			if($cooldown > 0){
				return;
			}
		}

		$inventory = $tile->getInventory();
		$success = $this->push($inventory);
		$origin = $this->getLoadedTile($this->position->getSide(Facing::UP));
		if($origin instanceof Container){
			$success = $this->pull($inventory, $origin->getRealInventory()) || $success;
		}elseif(!($origin instanceof JukeboxTile)){
			$success = $this->pickup($inventory) || $success;
		}
		if($success){
			self::setTransferCooldown($tile, self::TRANSFER_COOLDOWN);
		}
	}

	private function push(Inventory $inventory) : bool{
		$destination = null;
		for($slot = 0, $size = $inventory->getSize(); $slot < $size; $slot++){
			if($inventory->isSlotEmpty($slot)){
				continue;
			}
			if($destination === null && ($destination = $this->getLoadedTile($this->position->getSide($this->getFacing()))) === null){
				return false;
			}

			$item = $inventory->getItem($slot);
			if($destination instanceof FurnaceTile){
				if($this->getFacing() === Facing::DOWN){
					$furnace_slot = FurnaceInventory::SLOT_INPUT;
				}elseif($item->getFuelTime() > 0){
					$furnace_slot = FurnaceInventory::SLOT_FUEL;
				}else{
					continue;
				}
				if($this->transferToSlot($inventory, $slot, $destination->getInventory(), $furnace_slot)){
					return true;
				}
			}elseif($destination instanceof BrewingStandTile){
				$brewing_inventory = $destination->getInventory();
				$brewing_slot = $this->getBrewingStandSlot($brewing_inventory, $item);
				if($brewing_slot !== null && $this->transferToSlot($inventory, $slot, $brewing_inventory, $brewing_slot)){
					return true;
				}
			}elseif($destination instanceof JukeboxTile){
				return $this->pushIntoJukebox($inventory, $slot, $destination);
			}elseif($destination instanceof Container){
				if($this->transferToInventory($inventory, $slot, $destination->getInventory())){
					if($destination instanceof HopperTile && self::getTransferCooldown($destination) === 0){
						self::setTransferCooldown($destination, self::TRANSFER_COOLDOWN);
					}
					return true;
				}
			}else{
				return false;
			}
		}
		return false;
	}

	private function pushIntoJukebox(Inventory $inventory, int $slot, JukeboxTile $destination) : bool{
		$item = $inventory->getItem($slot);
		$jukebox = $destination->getBlock();
		if(!($item instanceof Record) || !($jukebox instanceof Jukebox) || $jukebox->getRecord() !== null){
			return false;
		}

		$record = $item->pop();
		$inventory->setItem($slot, $item);
		$jukebox->insertRecord($record);
		$this->position->world->setBlock($jukebox->getPosition(), $jukebox);
		return true;
	}

	private function pull(Inventory $inventory, Inventory $origin) : bool{
		if($origin instanceof FurnaceInventory){
			if($origin->getFuel() instanceof Bucket){
				return $this->transferToInventory($origin, FurnaceInventory::SLOT_FUEL, $inventory);
			}
			return !$origin->getResult()->isNull() && $this->transferToInventory($origin, FurnaceInventory::SLOT_RESULT, $inventory);
		}

		if($origin instanceof BrewingStandInventory){
			$slots = [BrewingStandInventory::SLOT_BOTTLE_LEFT, BrewingStandInventory::SLOT_BOTTLE_MIDDLE, BrewingStandInventory::SLOT_BOTTLE_RIGHT];
		}else{
			$slots = [];
			for($slot = 0, $size = $origin->getSize(); $slot < $size; $slot++){
				$slots[] = $slot;
			}
		}
		foreach($slots as $slot){
			if(!$origin->isSlotEmpty($slot) && $this->transferToInventory($origin, $slot, $inventory)){
				return true;
			}
		}
		return false;
	}

	private function pickup(Inventory $inventory) : bool{
		$pickup_box = new AxisAlignedBB(
			$this->position->x,
			$this->position->y + 1 - self::BOWL_DEPTH,
			$this->position->z,
			$this->position->x + 1,
			$this->position->y + 1.75,
			$this->position->z + 1
		);

		foreach($this->position->world->getNearbyEntities($pickup_box) as $entity){
			if(!($entity instanceof ItemEntity) || $entity->isClosed() || $entity->isFlaggedForDespawn() || $entity->getPickupDelay() === ItemEntity::NEVER_DESPAWN){
				continue;
			}

			$item = $entity->getItem();
			$ev = new BlockItemPickupEvent($this, $entity, $item, $inventory);
			$ev->call();
			$destination = $ev->getInventory();
			if($ev->isCancelled() || $destination === null){
				continue;
			}

			$picked_up = $ev->getItem();
			$quantity = min($destination->getAddableItemQuantity($picked_up), $item->getCount());
			if($quantity <= 0){
				continue;
			}

			$destination->addItem((clone $picked_up)->setCount($quantity));
			$remaining = $item->getCount() - $quantity;
			if($remaining > 0){
				$entity->setStackSize($remaining);
			}else{
				$entity->flagForDespawn();
			}
			return true;
		}
		return false;
	}

	private function transferToSlot(Inventory $source, int $source_slot, Inventory $destination, int $destination_slot) : bool{
		$item = $source->getItem($source_slot);
		$existing = $destination->getItem($destination_slot);
		$max_stack_size = min($destination->getMaxStackSize(), $item->getMaxStackSize());
		if(!$existing->isNull() && (!$existing->canStackWith($item) || $existing->getCount() >= $max_stack_size)){
			return false;
		}

		$moved = $item->pop();
		$source->setItem($source_slot, $item);
		$destination->setItem($destination_slot, $existing->isNull() ? $moved : $existing->setCount($existing->getCount() + 1));
		return true;
	}

	private function transferToInventory(Inventory $source, int $source_slot, Inventory $destination) : bool{
		$item = $source->getItem($source_slot);
		$moved = (clone $item)->setCount(1);
		if(!$destination->canAddItem($moved)){
			return false;
		}

		$item->pop();
		$source->setItem($source_slot, $item);
		$destination->addItem($moved);
		return true;
	}

	private function getBrewingStandSlot(BrewingStandInventory $inventory, Item $item) : ?int{
		if($this->getFacing() === Facing::DOWN){
			return BrewingStandInventory::SLOT_INGREDIENT;
		}
		if($item->equals(VanillaItems::BLAZE_POWDER(), true, false)){
			return BrewingStandInventory::SLOT_FUEL;
		}
		if(!($item instanceof Potion) && !($item instanceof SplashPotion) && !($item instanceof GlassBottle)){
			return null;
		}
		foreach([BrewingStandInventory::SLOT_BOTTLE_LEFT, BrewingStandInventory::SLOT_BOTTLE_MIDDLE, BrewingStandInventory::SLOT_BOTTLE_RIGHT] as $slot){
			if($inventory->isSlotEmpty($slot)){
				return $slot;
			}
		}
		return null;
	}

	private function getLoadedTile(Vector3 $pos) : ?Tile{
		$world = $this->position->world;
		return $world->isInLoadedTerrain($pos) ? $world->getTile($pos) : null;
	}

	private static function getTransferCooldown(HopperTile $tile) : int{
		return (new ReflectionProperty(HopperTile::class, "transferCooldown"))->getValue($tile);
	}

	private static function setTransferCooldown(HopperTile $tile, int $cooldown) : void{
		(new ReflectionProperty(HopperTile::class, "transferCooldown"))->setValue($tile, $cooldown);
	}
}
