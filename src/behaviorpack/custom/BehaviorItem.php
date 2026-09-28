<?php

declare(strict_types=1);

namespace behaviorpack\custom;

use behaviorpack\custom\item\CombatItem;
use customiesdevs\customies\item\ItemComponents;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;

/**
 * A plain behavior pack item, reused for every identifier.
 *
 * @phpstan-import-type ItemDefinition from BehaviorItemTrait
 */
final class BehaviorItem extends Item implements ItemComponents, CombatItem{
	use BehaviorItemTrait;

	/**
	 * @phpstan-param ItemDefinition $definition
	 */
	public function __construct(ItemIdentifier $identifier, array $definition){
		$this->definition = $definition;
		parent::__construct($identifier, $definition["name"]);
	}
}
