<?php

declare(strict_types=1);

namespace behaviorpack\custom;

use behaviorpack\custom\item\EntityPlacer;
use behaviorpack\custom\item\PiercingWeapon;
use behaviorpack\custom\item\Repairable;
use customiesdevs\customies\item\component\ItemComponent;
use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\item\ItemUseResult;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\Utils;

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
 *     protection: int,
 *     repairable: Repairable|null,
 *     fireResistant: bool,
 *     compostingChance: int,
 *     entityPlacer: EntityPlacer|null,
 *     swingSounds: array<string, string>,
 *     piercingWeapon: PiercingWeapon|null
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

	public function isFireProof() : bool{
		return $this->definition["fireResistant"] || parent::isFireProof();
	}

	/**
	 * Returns the minecraft:compostable chance in percent, 0 when the item
	 * cannot be composted.
	 */
	public function getCompostingChance() : int{
		return $this->definition["compostingChance"];
	}

	public function getRepairable() : ?Repairable{
		return $this->definition["repairable"];
	}

	public function getEntityPlacer() : ?EntityPlacer{
		return $this->definition["entityPlacer"];
	}

	public function getSwingSound(string $type) : ?string{
		return $this->definition["swingSounds"][$type] ?? null;
	}

	public function getPiercingWeapon() : ?PiercingWeapon{
		return $this->definition["piercingWeapon"];
	}

	/**
	 * @param Item[] &$returnedItems
	 */
	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		$placer = $this->definition["entityPlacer"];
		if($placer === null){
			return parent::onInteractBlock($player, $blockReplace, $blockClicked, $face, $clickVector, $returnedItems);
		}
		if(!$placer->canUseOn($blockClicked)){
			return ItemUseResult::NONE;
		}
		$entity = $placer->create($player->getWorld(), $blockReplace->getPosition()->add(0.5, 0, 0.5), Utils::getRandomFloat() * 360);
		if($entity === null){
			return ItemUseResult::FAIL;
		}
		if($this->hasCustomName()){
			$entity->setNameTag($this->getCustomName());
		}
		$this->pop();
		$entity->spawnToAll();
		return ItemUseResult::SUCCESS;
	}
}
