<?php

declare(strict_types=1);

namespace behaviorpack\custom;

use behaviorpack\custom\item\CombatItem;
use behaviorpack\custom\item\RepairableTrait;
use customiesdevs\customies\item\ItemComponents;
use pocketmine\item\Durable;
use pocketmine\item\ItemIdentifier;

/**
 * A behavior pack item with minecraft:durability.
 *
 * @phpstan-import-type ItemDefinition from BehaviorItemTrait
 */
final class BehaviorDurableItem extends Durable implements ItemComponents, CombatItem{
	use BehaviorItemTrait;
	use RepairableTrait;

	/**
	 * @phpstan-param ItemDefinition $definition
	 */
	public function __construct(ItemIdentifier $identifier, array $definition){
		$this->definition = $definition;
		parent::__construct($identifier, $definition["name"]);
	}

	public function getMaxDurability() : int{
		return $this->definition["durability"];
	}
}
