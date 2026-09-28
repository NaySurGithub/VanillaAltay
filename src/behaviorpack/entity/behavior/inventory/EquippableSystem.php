<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\inventory;

use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use function array_is_list;
use function is_array;
use function is_int;
use function is_string;

/**
 * minecraft:equippable: slots the players fill by interacting with an
 * accepted item (saddles, horse armor, carpets, chests...).
 */
final class EquippableSystem extends EntitySystem{

	public function onAdd() : void{
		MobEquipment::of($this->entity);
	}

	/**
	 * @return list<array<mixed>>
	 */
	public function getSlotDefinitions() : array{
		$slots = $this->config["slots"] ?? [];
		if(!is_array($slots)){
			return [];
		}
		if(!array_is_list($slots)){
			$slots = [$slots];
		}
		$result = [];
		foreach($slots as $index => $slot){
			if(is_array($slot)){
				$slot["slot"] = is_int($slot["slot"] ?? null) ? $slot["slot"] : $index;
				$result[] = $slot;
			}
		}
		return $result;
	}

	/**
	 * @param array<mixed> $definition
	 */
	private function accepts(array $definition, Item $item) : bool{
		$accepted = $definition["accepted_items"] ?? null;
		if(is_array($accepted) && $accepted !== []){
			return ItemCodec::matchesAny($item, $accepted);
		}
		return ItemCodec::matches($item, $definition["item"] ?? null);
	}

	public function getItem(int $slot) : Item{
		return MobEquipment::of($this->entity)->getSlot($slot);
	}

	/**
	 * Equips an item in a slot and runs its on_equip trigger.
	 */
	public function equip(int $slot, Item $item, ?Player $player = null) : bool{
		foreach($this->getSlotDefinitions() as $definition){
			if($definition["slot"] !== $slot || !$this->accepts($definition, $item)){
				continue;
			}
			MobEquipment::of($this->entity)->setSlot($slot, (clone $item)->setCount(1));
			$this->entity->runTrigger($definition["on_equip"] ?? null, ["player" => $player, "other" => $player]);
			return true;
		}
		return false;
	}

	/**
	 * Removes the item of a slot, runs the on_unequip trigger and returns
	 * the item.
	 */
	public function unequip(int $slot, ?Player $player = null) : Item{
		$equipment = MobEquipment::of($this->entity);
		$item = $equipment->getSlot($slot);
		if($item->isNull()){
			return $item;
		}
		$equipment->setSlot($slot, VanillaItems::AIR());
		foreach($this->getSlotDefinitions() as $definition){
			if($definition["slot"] === $slot){
				$this->entity->runTrigger($definition["on_unequip"] ?? null, ["player" => $player, "other" => $player]);
			}
		}
		return $item;
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$held = $player->getInventory()->getItemInHand();
		if($held->isNull() || $player->isSneaking()){
			return false;
		}
		$equipment = MobEquipment::of($this->entity);
		foreach($this->getSlotDefinitions() as $definition){
			$slot = $definition["slot"];
			if(!$equipment->getSlot($slot)->isNull() || !$this->accepts($definition, $held)){
				continue;
			}
			if(!$this->equip($slot, $held, $player)){
				continue;
			}
			if($player->hasFiniteResources()){
				$held->pop();
				$player->getInventory()->setItemInHand($held);
			}
			return true;
		}
		return false;
	}

	/**
	 * Returns the interact text of the slot the held item would fill.
	 */
	public function getInteractText(Item $held) : ?string{
		$equipment = MobEquipment::of($this->entity);
		foreach($this->getSlotDefinitions() as $definition){
			if($equipment->getSlot($definition["slot"])->isNull() && $this->accepts($definition, $held) && is_string($definition["interact_text"] ?? null)){
				return $definition["interact_text"];
			}
		}
		return null;
	}

	public function onSpawnTo(Player $player) : void{
		MobEquipment::of($this->entity)->sendTo($player);
	}

	public function onDeath() : void{
		MobEquipment::of($this->entity)->dropSlots();
	}
}
