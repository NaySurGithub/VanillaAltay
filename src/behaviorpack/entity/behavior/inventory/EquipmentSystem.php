<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\inventory;

use behaviorpack\entity\behavior\EntitySystem;
use behaviorpack\entity\BehaviorEntity;
use pocketmine\item\Item;
use pocketmine\player\Player;
use function is_array;
use function is_string;
use function max;
use function min;
use function strtolower;

/**
 * minecraft:equipment: rolls the equipment table once when the entity first
 * gets the component, and drops the equipment on death with the chances of
 * slot_drop_chance.
 */
final class EquipmentSystem extends EntitySystem{

	public const DEFAULT_DROP_CHANCE = 0.085;

	private const DATA_ROLLED = "equipment_rolled";

	public function onAdd() : void{
		$equipment = MobEquipment::of($this->entity);
		$table = $this->config["table"] ?? null;
		if(!is_string($table) || $table === ""){
			return;
		}
		$rolled = $this->entity->getData(self::DATA_ROLLED);
		if($rolled === $table){
			return;
		}
		$this->entity->setData(self::DATA_ROLLED, $table);
		foreach(ItemCodec::rollTable($table, $this->entity) as $item){
			if($item->isNull()){
				continue;
			}
			$armorSlot = MobEquipment::armorSlotOf($item);
			if($armorSlot !== null){
				if($equipment->getArmor($armorSlot)->isNull()){
					$equipment->setArmor($armorSlot, $item);
				}
				continue;
			}
			if($equipment->getMainHand()->isNull()){
				$equipment->setMainHand($item);
			}elseif($equipment->getOffHand()->isNull()){
				$equipment->setOffHand($item);
			}
		}
	}

	public function getEquipment() : MobEquipment{
		return MobEquipment::of($this->entity);
	}

	public function getMainHand() : Item{
		return MobEquipment::of($this->entity)->getMainHand();
	}

	public function setMainHand(Item $item) : void{
		MobEquipment::of($this->entity)->setMainHand($item);
	}

	public function getOffHand() : Item{
		return MobEquipment::of($this->entity)->getOffHand();
	}

	public function setOffHand(Item $item) : void{
		MobEquipment::of($this->entity)->setOffHand($item);
	}

	public function getArmor(int $slot) : Item{
		return MobEquipment::of($this->entity)->getArmor($slot);
	}

	public function setArmor(int $slot, Item $item) : void{
		MobEquipment::of($this->entity)->setArmor($slot, $item);
	}

	/**
	 * @return array<string, float>
	 */
	public function getDropChances() : array{
		$chances = [];
		$entries = $this->config["slot_drop_chance"] ?? [];
		if(!is_array($entries)){
			return $chances;
		}
		foreach($entries as $entry){
			if(is_array($entry) && is_string($entry["slot"] ?? null)){
				$chances[strtolower($entry["slot"])] = max(0.0, min(1.0, BehaviorEntity::toFloat($entry["drop_chance"] ?? null, self::DEFAULT_DROP_CHANCE)));
			}
		}
		return $chances;
	}

	public function onSpawnTo(Player $player) : void{
		MobEquipment::of($this->entity)->sendTo($player);
	}

	public function onDeath() : void{
		MobEquipment::of($this->entity)->dropAll($this->getDropChances(), self::DEFAULT_DROP_CHANCE);
	}
}
