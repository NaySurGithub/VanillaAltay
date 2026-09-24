<?php

declare(strict_types=1);

namespace behaviorpack\custom;

use customiesdevs\customies\item\ItemComponents;
use pocketmine\item\Food;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\StringToItemParser;

/**
 * A behavior pack item with minecraft:food.
 *
 * @phpstan-import-type ItemDefinition from BehaviorItemTrait
 */
final class BehaviorFoodItem extends Food implements ItemComponents{
	use BehaviorItemTrait;

	/**
	 * @phpstan-param ItemDefinition $definition
	 */
	public function __construct(ItemIdentifier $identifier, array $definition){
		$this->definition = $definition;
		parent::__construct($identifier, $definition["name"]);
	}

	public function getFoodRestore() : int{
		return $this->definition["nutrition"];
	}

	public function getSaturationRestore() : float{
		return $this->definition["saturation"];
	}

	public function requiresHunger() : bool{
		return !$this->definition["canAlwaysEat"];
	}

	public function getResidue() : Item{
		$residue = $this->definition["residue"];
		if($residue !== null){
			$item = StringToItemParser::getInstance()->parse($residue);
			if($item !== null){
				return $item;
			}
		}
		return parent::getResidue();
	}

	public function getMinUseDuration() : int{
		return $this->definition["useTicks"] > 0 ? $this->definition["useTicks"] : parent::getMinUseDuration();
	}
}
