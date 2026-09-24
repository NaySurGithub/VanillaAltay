<?php

declare(strict_types=1);

namespace behaviorpack\custom;

use customiesdevs\customies\item\ItemComponents;
use pocketmine\item\Armor;
use pocketmine\item\ArmorTypeInfo;
use pocketmine\item\ItemIdentifier;

/**
 * A behavior pack item with minecraft:wearable in an armor slot.
 *
 * @phpstan-import-type ItemDefinition from BehaviorItemTrait
 */
final class BehaviorArmorItem extends Armor implements ItemComponents{
	use BehaviorItemTrait;

	/**
	 * @phpstan-param ItemDefinition $definition
	 */
	public function __construct(ItemIdentifier $identifier, array $definition){
		$this->definition = $definition;
		parent::__construct($identifier, $definition["name"], new ArmorTypeInfo(
			$definition["protection"],
			$definition["durability"],
			$definition["armorSlot"] ?? 0
		));
		if($definition["durability"] <= 0){
			$this->setUnbreakable();
		}
	}
}
