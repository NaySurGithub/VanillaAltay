<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\inventory;

use behaviorpack\entity\behavior\EntitySystem;
use behaviorpack\entity\BehaviorEntity;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\player\Player;
use function in_array;
use function is_int;
use function is_string;
use function max;
use function min;
use function strtolower;

/**
 * minecraft:inventory: the container of the entity, kept in its data store,
 * dropped on death unless private and opened to the players that may use it.
 */
final class InventorySystem extends EntitySystem{

	private const DATA_KEY = "inventory";
	private const MAX_SIZE = 256;

	private ?EntityInventory $inventory = null;

	private bool $loading = false;

	public function onAdd() : void{
		$size = $this->computeSize();
		$windowType = $this->windowType();
		if($this->inventory === null){
			$this->inventory = new EntityInventory($size, $this->entity, $windowType);
			$this->inventory->getListeners()->add(CallbackInventoryListener::onAnyChange(function(Inventory $inventory) : void{
				$this->save();
			}));
			$this->loading = true;
			foreach(ItemCodec::decodeList($this->entity->getData(self::DATA_KEY)) as $slot => $item){
				if($slot >= 0 && $slot < $size){
					$this->inventory->setItem($slot, $item);
				}
			}
			$this->loading = false;
		}elseif($this->inventory->getSize() !== $size){
			$this->resize($size);
		}
		$this->inventory->setWindowType($windowType);
		$this->entity->setFlag(EntityMetadataFlags::CONTAINER_PRIVATE, $this->isPrivate());
		$this->entity->setMetadata(EntityMetadataProperties::CONTAINER_TYPE, "byte", $windowType);
		$this->entity->setMetadata(EntityMetadataProperties::CONTAINER_BASE_SIZE, "int", $this->baseSize());
		$this->entity->setMetadata(EntityMetadataProperties::CONTAINER_EXTRA_SLOTS_PER_STRENGTH, "int", $this->slotsPerStrength());
	}

	public function onRemove() : void{
		if($this->inventory !== null){
			$this->inventory->removeAllViewers();
		}
	}

	public function getInventory() : Inventory{
		if($this->inventory === null){
			$this->onAdd();
		}
		return $this->inventory ?? throw new \LogicException("Inventory not initialized");
	}

	public function getContainerType() : string{
		$type = $this->config["container_type"] ?? "none";
		return is_string($type) ? strtolower($type) : "none";
	}

	public function isPrivate() : bool{
		return ($this->config["private"] ?? false) === true;
	}

	public function isRestrictedToOwner() : bool{
		return ($this->config["restrict_to_owner"] ?? false) === true;
	}

	public function canBeSiphonedFrom() : bool{
		return ($this->config["can_be_siphoned_from"] ?? false) === true;
	}

	private function baseSize() : int{
		$size = $this->config["inventory_size"] ?? 5;
		return is_int($size) ? max(0, $size) : 5;
	}

	private function slotsPerStrength() : int{
		$slots = $this->config["additional_slots_per_strength"] ?? 0;
		return is_int($slots) ? max(0, $slots) : 0;
	}

	private function computeSize() : int{
		$strength = $this->entity->getData("strength", 0);
		$strength = is_int($strength) ? max(0, $strength) : 0;
		$extra = $this->slotsPerStrength() * $strength;
		if($this->getContainerType() === "horse" && $extra > 0 && !$this->entity->getFlag(EntityMetadataFlags::CHESTED)){
			$extra = 0;
		}
		return max(1, min(self::MAX_SIZE, $this->baseSize() + $extra));
	}

	private function windowType() : int{
		return match($this->getContainerType()){
			"horse" => WindowTypes::HORSE,
			"minecart_chest", "chest_boat", "container" => WindowTypes::CONTAINER,
			"minecart_hopper" => WindowTypes::HOPPER,
			"inventory" => WindowTypes::INVENTORY,
			default => WindowTypes::CONTAINER
		};
	}

	private function resize(int $size) : void{
		$old = $this->inventory;
		if($old === null){
			return;
		}
		$contents = $old->getContents();
		$old->removeAllViewers();
		$this->inventory = null;
		$this->entity->setData(self::DATA_KEY, ItemCodec::encodeList($contents));
		$this->onAdd();
		$world = $this->entity->getWorld();
		foreach($contents as $slot => $item){
			if($slot >= $size){
				$world->dropItem($this->entity->getLocation(), $item);
			}
		}
		$this->save();
	}

	private function save() : void{
		if($this->loading || $this->inventory === null){
			return;
		}
		$contents = ItemCodec::encodeList($this->inventory->getContents());
		$this->entity->setData(self::DATA_KEY, $contents === [] ? null : $contents);
	}

	/**
	 * Returns whether a player may open the container right now.
	 */
	public function canOpen(Player $player) : bool{
		if($this->isPrivate() || !$this->entity->isAlive()){
			return false;
		}
		$type = $this->getContainerType();
		if($type === "none" || $type === "inventory"){
			return false;
		}
		if($this->isRestrictedToOwner() && !$this->entity->isOwnedBy($player)){
			return false;
		}
		if($type === "horse"){
			if(!$this->entity->hasComponent("minecraft:is_tamed")){
				return false;
			}
			return $player->isSneaking() || in_array($player, $this->entity->getPassengers(), true);
		}
		return true;
	}

	public function open(Player $player) : bool{
		if(!$this->canOpen($player)){
			return false;
		}
		EntityInventory::registerOpener($player);
		return $player->setCurrentWindow($this->getInventory());
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$type = $this->getContainerType();
		if($type === "horse" && !$player->isSneaking()){
			return false;
		}
		return $this->open($player);
	}

	public function onDeath() : void{
		if($this->inventory === null){
			return;
		}
		$this->inventory->removeAllViewers();
		if($this->isPrivate()){
			return;
		}
		$world = $this->entity->getWorld();
		foreach($this->inventory->getContents() as $item){
			$world->dropItem($this->entity->getLocation(), $item);
		}
		$this->inventory->clearAll();
	}

	/**
	 * Adds items to the container and returns what did not fit.
	 *
	 * @return list<Item>
	 */
	public function addItems(Item ...$items) : array{
		$left = [];
		foreach($this->getInventory()->addItem(...$items) as $item){
			$left[] = $item;
		}
		return $left;
	}
}
