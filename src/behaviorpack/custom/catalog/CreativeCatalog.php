<?php

declare(strict_types=1);

namespace behaviorpack\custom\catalog;

use behaviorpack\BehaviorPack;
use Logger;
use Throwable;
use function in_array;
use function is_array;
use function is_string;
use function str_starts_with;
use function substr;

/**
 * The creative inventory layout declared by the item_catalog files of the
 * behavior packs, indexed by item identifier.
 */
final class CreativeCatalog{

	private const CATEGORIES = [
		"construction",
		"equipment",
		"items",
		"nature"
	];

	/** @var array<string, CatalogEntry> */
	private array $entries = [];

	private int $nextOrder = 0;

	/**
	 * @param list<BehaviorPack> $packs
	 */
	public static function load(array $packs, Logger $logger) : self{
		$catalog = new self();
		foreach($packs as $pack){
			foreach($pack->listFiles("item_catalog") as $file){
				try{
					$catalog->read(BehaviorPack::readJson($file));
				}catch(Throwable $e){
					$logger->warning("Behavior packs: skipped item catalog " . $pack->getName() . "/" . $pack->relativePath($file) . ": " . $e->getMessage());
				}
			}
		}
		return $catalog;
	}

	public function get(string $identifier) : ?CatalogEntry{
		return $this->entries[$identifier] ?? null;
	}

	public function isEmpty() : bool{
		return $this->entries === [];
	}

	/**
	 * @return array<string, CatalogEntry>
	 */
	public function getAll() : array{
		return $this->entries;
	}

	/**
	 * Strips the "minecraft:" namespace of vanilla group names, which the
	 * creative inventory knows without it.
	 */
	public static function normalizeGroup(string $group) : string{
		if(str_starts_with($group, "minecraft:")){
			return substr($group, 10);
		}
		return $group;
	}

	/**
	 * @param array<mixed> $data
	 */
	private function read(array $data) : void{
		$root = $data["minecraft:crafting_items_catalog"] ?? null;
		if(!is_array($root)){
			return;
		}
		foreach($root["categories"] ?? [] as $category){
			if(!is_array($category)){
				continue;
			}
			$name = $category["category_name"] ?? null;
			if(!is_string($name) || !in_array($name, self::CATEGORIES, true)){
				continue;
			}
			foreach($category["groups"] ?? [] as $group){
				if(is_array($group)){
					$this->readGroup($name, $group);
				}
			}
		}
	}

	/**
	 * @param array<mixed> $group
	 */
	private function readGroup(string $category, array $group) : void{
		$identifier = $group["group_identifier"] ?? null;
		$groupName = null;
		$icon = null;
		if(is_array($identifier)){
			$name = $identifier["name"] ?? null;
			if(is_string($name) && $name !== ""){
				$groupName = self::normalizeGroup($name);
			}
			$iconValue = $identifier["icon"] ?? null;
			if(is_string($iconValue) && $iconValue !== ""){
				$icon = $iconValue;
			}elseif(is_array($iconValue) && is_string($iconValue["name"] ?? null)){
				$icon = $iconValue["name"];
			}
		}

		foreach($group["items"] ?? [] as $item){
			$itemId = is_array($item) ? ($item["name"] ?? null) : $item;
			if(!is_string($itemId) || $itemId === "" || isset($this->entries[$itemId])){
				continue;
			}
			$this->entries[$itemId] = new CatalogEntry($category, $groupName, $icon, $this->nextOrder++);
		}
	}
}
