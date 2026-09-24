<?php

declare(strict_types=1);

namespace behaviorpack\custom;

use customiesdevs\customies\item\component\ItemComponent;
use pocketmine\block\Block;
use pocketmine\item\StringToItemParser;

/**
 * Shared behaviour of the items defined by a behavior pack minecraft:item
 * file: Customies components and the server-side values read from the JSON.
 *
 * @phpstan-type ItemDefinition array{
 *     identifier: string,
 *     name: string,
 *     maxStackSize: int,
 *     attackPoints: int,
 *     fuelTicks: int,
 *     cooldownTicks: int,
 *     cooldownTag: string|null,
 *     blockPlacer: string|null,
 *     durability: int,
 *     nutrition: int,
 *     saturation: float,
 *     canAlwaysEat: bool,
 *     residue: string|null,
 *     useTicks: int,
 *     armorSlot: int|null,
 *     protection: int
 * }
 */
trait BehaviorItemTrait{

	/**
	 * @phpstan-var ItemDefinition
	 */
	protected array $definition;

	/** @var array<string, ItemComponent> */
	private array $itemComponents = [];

	public function getIdentifier() : string{
		return $this->definition["identifier"];
	}

	public function addComponent(ItemComponent $component) : void{
		$this->itemComponents[$component->getName()] = $component;
	}

	public function hasComponent(string $name) : bool{
		return isset($this->itemComponents[$name]);
	}

	/**
	 * @return array<string, ItemComponent>
	 */
	public function getComponents() : array{
		return $this->itemComponents;
	}

	public function getMaxStackSize() : int{
		return $this->definition["maxStackSize"];
	}

	public function getAttackPoints() : int{
		return $this->definition["attackPoints"];
	}

	public function getFuelTime() : int{
		return $this->definition["fuelTicks"];
	}

	public function getCooldownTicks() : int{
		return $this->definition["cooldownTicks"];
	}

	public function getCooldownTag() : ?string{
		return $this->definition["cooldownTag"];
	}

	public function getBlock(?int $clickedFace = null) : Block{
		$identifier = $this->definition["blockPlacer"];
		if($identifier !== null){
			$item = StringToItemParser::getInstance()->parse($identifier);
			if($item !== null){
				return $item->getBlock($clickedFace);
			}
		}
		return parent::getBlock($clickedFace);
	}
}
