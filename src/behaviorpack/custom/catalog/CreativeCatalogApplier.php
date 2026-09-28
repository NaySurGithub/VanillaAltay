<?php

declare(strict_types=1);

namespace behaviorpack\custom\catalog;

use pocketmine\inventory\CreativeCategory;
use pocketmine\inventory\CreativeGroup;
use pocketmine\inventory\CreativeInventory;
use pocketmine\inventory\CreativeInventoryEntry;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\lang\Translatable;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use Throwable;
use function array_key_exists;
use function array_values;
use function usort;

/**
 * Rearranges the creative inventory once all custom content is registered so
 * that every item listed by the item catalog, vanilla or custom, sits in its
 * catalog category and group, in catalog order, with the catalog group icon.
 */
final class CreativeCatalogApplier{

	/** @var array<string, CreativeGroup> */
	private array $existingGroups = [];

	/** @var array<string, ?CreativeGroup> */
	private array $catalogGroups = [];

	public function __construct(
		private CreativeCatalog $catalog
	){
	}

	public function apply() : void{
		if($this->catalog->isEmpty()){
			return;
		}
		$inventory = CreativeInventory::getInstance();
		$entries = array_values($inventory->getAllEntries());

		foreach($entries as $entry){
			$group = $entry->getGroup();
			if($group !== null){
				$this->existingGroups[self::groupName($group)] ??= $group;
			}
		}

		/** @var array<int, CatalogEntry> $placements */
		$placements = [];
		/** @var array<string, list<int>> $members */
		$members = [];
		foreach($entries as $index => $entry){
			$identifier = self::identifierOf($entry->getItem());
			$placement = $identifier === null ? null : $this->catalog->get($identifier);
			if($placement === null){
				continue;
			}
			$placements[$index] = $placement;
			$members[$placement->groupKey()][] = $index;
		}
		if($placements === []){
			return;
		}

		/** @var array<string, string> $anchorGroups */
		$anchorGroups = [];
		foreach($members as $key => $indexes){
			$first = $placements[$indexes[0]];
			if($first->group !== null && isset($this->existingGroups[$first->group])){
				$anchorGroups[$first->group] ??= $key;
			}
		}

		/** @var list<CreativeInventoryEntry> $result */
		$result = [];
		/** @var array<string, true> $emitted */
		$emitted = [];
		foreach($entries as $index => $entry){
			if(isset($placements[$index])){
				$key = $placements[$index]->groupKey();
				if(!isset($emitted[$key])){
					$emitted[$key] = true;
					$this->emitGroup($result, $entries, $placements, $members[$key]);
				}
				continue;
			}
			$group = $entry->getGroup();
			$key = $group === null ? null : ($anchorGroups[self::groupName($group)] ?? null);
			if($key !== null && !isset($emitted[$key])){
				$emitted[$key] = true;
				$this->emitGroup($result, $entries, $placements, $members[$key]);
			}
			$result[] = $entry;
		}

		$inventory->clear();
		foreach($result as $entry){
			$inventory->add($entry->getItem(), $entry->getCategory(), $entry->getGroup());
		}
	}

	/**
	 * @param list<CreativeInventoryEntry> $result
	 * @param list<CreativeInventoryEntry> $entries
	 * @param array<int, CatalogEntry>     $placements
	 * @param list<int>                    $indexes
	 */
	private function emitGroup(array &$result, array $entries, array $placements, array $indexes) : void{
		usort($indexes, static function(int $a, int $b) use ($placements) : int{
			return $placements[$a]->order <=> $placements[$b]->order ?: $a <=> $b;
		});
		$first = $placements[$indexes[0]];
		$category = self::category($first->category);
		$group = $this->resolveGroup($first, $entries[$indexes[0]]->getItem());
		foreach($indexes as $index){
			$result[] = new CreativeInventoryEntry($entries[$index]->getItem(), $category, $group);
		}
	}

	private function resolveGroup(CatalogEntry $placement, Item $fallbackIcon) : ?CreativeGroup{
		$key = $placement->groupKey();
		if(array_key_exists($key, $this->catalogGroups)){
			return $this->catalogGroups[$key];
		}
		if($placement->group === null || $placement->group === "none"){
			return $this->catalogGroups[$key] = null;
		}
		if(isset($this->existingGroups[$placement->group]) && $placement->icon === null){
			return $this->catalogGroups[$key] = $this->existingGroups[$placement->group];
		}
		$icon = $placement->icon === null ? null : self::parseItem($placement->icon);
		if($icon === null && isset($this->existingGroups[$placement->group])){
			return $this->catalogGroups[$key] = $this->existingGroups[$placement->group];
		}
		return $this->catalogGroups[$key] = new CreativeGroup(new Translatable($placement->group), $icon ?? $fallbackIcon);
	}

	private static function parseItem(string $identifier) : ?Item{
		try{
			return StringToItemParser::getInstance()->parse($identifier);
		}catch(Throwable){
			return null;
		}
	}

	private static function identifierOf(Item $item) : ?string{
		try{
			return GlobalItemDataHandlers::getSerializer()->serializeType($item)->getName();
		}catch(Throwable){
			return null;
		}
	}

	private static function groupName(CreativeGroup $group) : string{
		$name = $group->getName();
		return $name instanceof Translatable ? $name->getText() : $name;
	}

	private static function category(string $category) : CreativeCategory{
		return match($category){
			"construction" => CreativeCategory::CONSTRUCTION,
			"nature" => CreativeCategory::NATURE,
			"equipment" => CreativeCategory::EQUIPMENT,
			default => CreativeCategory::ITEMS
		};
	}
}
