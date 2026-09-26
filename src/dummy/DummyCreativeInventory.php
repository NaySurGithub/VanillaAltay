<?php

declare(strict_types=1);

namespace dummy;

use dummy\block\DummyBlock;
use dummy\item\DummyDurableItem;
use dummy\item\DummyItem;
use pocketmine\crafting\CraftingManagerFromDataHelper;
use pocketmine\data\bedrock\BedrockDataFiles;
use pocketmine\inventory\CreativeCategory;
use pocketmine\inventory\CreativeGroup;
use pocketmine\inventory\CreativeInventory;
use pocketmine\inventory\CreativeInventoryEntry;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\lang\Translatable;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\Filesystem;
use function base64_decode;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use const JSON_THROW_ON_ERROR;

/**
 * Rebuilds the creative inventory in the vanilla order once the dummy content
 * exists, so dummy items land at their vanilla position and in their vanilla
 * group. Entries added by other modules keep their item and category; entries
 * that are not part of the vanilla list stay at the end.
 */
final class DummyCreativeInventory{

	private function __construct(){
	}

	public static function rebuild() : void{
		$data = json_decode(Filesystem::fileGetContents(BedrockDataFiles::CREATIVE_ITEMS_JSON), true, 512, JSON_THROW_ON_ERROR);
		if(!is_array($data) || !is_array($data["groups"] ?? null) || !is_array($data["items"] ?? null)){
			return;
		}

		$inventory = CreativeInventory::getInstance();
		$remaining = [];
		foreach($inventory->getAllEntries() as $entry){
			$remaining[$entry->getItem()->getStateId()][] = $entry;
		}

		[$categories, $groups] = self::readGroups($data["groups"]);

		$entries = [];
		foreach($data["items"] as $itemData){
			if(!is_array($itemData) || !is_int($itemData["group_index"] ?? null)){
				continue;
			}
			$item = self::readItem($itemData);
			if($item === null){
				continue;
			}
			$groupIndex = $itemData["group_index"];
			$existing = self::take($remaining, $item);
			if($existing !== null){
				$entries[] = new CreativeInventoryEntry($existing->getItem(), $existing->getCategory(), $groups[$groupIndex] ?? $existing->getGroup());
			}elseif(self::isDummy($item)){
				$entries[] = new CreativeInventoryEntry($item, $categories[$groupIndex] ?? CreativeCategory::ITEMS, $groups[$groupIndex] ?? null);
			}
		}
		foreach($remaining as $leftovers){
			foreach($leftovers as $entry){
				$entries[] = $entry;
			}
		}

		$inventory->clear();
		foreach($entries as $entry){
			$inventory->add($entry->getItem(), $entry->getCategory(), $entry->getGroup());
		}
	}

	/**
	 * @param mixed[] $groupsData
	 *
	 * @return array{array<int, CreativeCategory>, array<int, CreativeGroup>}
	 */
	private static function readGroups(array $groupsData) : array{
		$categories = [];
		$groups = [];
		foreach($groupsData as $index => $groupData){
			if(!is_int($index) || !is_array($groupData) || !is_int($groupData["creative_category"] ?? null)){
				continue;
			}
			$categories[$index] = match($groupData["creative_category"]){
				1 => CreativeCategory::CONSTRUCTION,
				2 => CreativeCategory::NATURE,
				3 => CreativeCategory::EQUIPMENT,
				default => CreativeCategory::ITEMS
			};
			$name = $groupData["name"] ?? "";
			if(!is_string($name) || $name === "" || !is_array($groupData["icon"] ?? null)){
				continue;
			}
			$icon = self::readItem($groupData["icon"]);
			if($icon !== null){
				$groups[$index] = new CreativeGroup(new Translatable($name), $icon);
			}
		}
		return [$categories, $groups];
	}

	/**
	 * @param mixed[] $data
	 */
	private static function readItem(array $data) : ?Item{
		if(!is_string($data["id"] ?? null)){
			return null;
		}
		try{
			$blockStates = null;
			if(is_string($data["block_state_b64"] ?? null)){
				$blockStates = self::decodeNbt($data["block_state_b64"])?->getCompoundTag("states");
			}
			$nbt = is_string($data["nbt_b64"] ?? null) ? self::decodeNbt($data["nbt_b64"]) : null;
			$damage = $data["damage"] ?? null;
			return CraftingManagerFromDataHelper::deserializeItemStackFromFields(
				$data["id"],
				is_int($damage) ? $damage : null,
				1,
				$blockStates,
				$nbt
			);
		}catch(\Exception){
			return null;
		}
	}

	private static function decodeNbt(string $base64) : ?CompoundTag{
		$raw = base64_decode($base64, true);
		if($raw === false){
			return null;
		}
		return (new LittleEndianNbtSerializer())->read($raw)->mustGetCompoundTag();
	}

	/**
	 * @param array<int, list<CreativeInventoryEntry>> $remaining
	 */
	private static function take(array &$remaining, Item $item) : ?CreativeInventoryEntry{
		$stateId = $item->getStateId();
		foreach($remaining[$stateId] ?? [] as $key => $entry){
			if($entry->matchesItem($item)){
				unset($remaining[$stateId][$key]);
				return $entry;
			}
		}
		return null;
	}

	private static function isDummy(Item $item) : bool{
		return $item instanceof DummyItem || $item instanceof DummyDurableItem || ($item instanceof ItemBlock && $item->getBlock() instanceof DummyBlock);
	}
}
