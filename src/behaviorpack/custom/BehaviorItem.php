<?php

declare(strict_types=1);

namespace behaviorpack\custom;

use customiesdevs\customies\item\ItemComponents;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;

/**
 * A plain behavior pack item, reused for every identifier.
 *
 * @phpstan-import-type ItemDefinition from BehaviorItemTrait
 */
final class BehaviorItem extends Item implements ItemComponents{
	use BehaviorItemTrait;

	/**
	 * @phpstan-param ItemDefinition $definition
	 */
	public function __construct(ItemIdentifier $identifier, array $definition){
		$this->definition = $definition;
		parent::__construct($identifier, $definition["name"]);
	}
}
