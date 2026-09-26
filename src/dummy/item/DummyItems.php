<?php

declare(strict_types=1);

namespace dummy\item;

use dummy\VanillaData;
use pocketmine\data\bedrock\item\BlockItemIdMap;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\StringToItemParser;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\io\GlobalItemDataHandlers;

/**
 * Registers a dummy item for every item of the vanilla palette that has no
 * deserializer once every other module is loaded. Block items, and legacy ids
 * that the item upgrader turns into another item, are left out.
 */
final class DummyItems{

	private const DEFAULT_MAX_STACK_SIZE = 64;

	private function __construct(){
	}

	/**
	 * @return int the number of registered items
	 */
	public static function register() : int{
		$deserializer = GlobalItemDataHandlers::getDeserializer();
		$serializer = GlobalItemDataHandlers::getSerializer();
		$upgrader = GlobalItemDataHandlers::getUpgrader()->getIdMetaUpgrader();
		$blockItems = BlockItemIdMap::getInstance();
		$blockDeserializer = GlobalBlockStateHandlers::getDeserializer();
		$parser = StringToItemParser::getInstance();
		$components = VanillaData::itemComponents();

		$count = 0;
		foreach(VanillaData::itemIds() as $id){
			if(
				$deserializer->getDeserializerForId($id) !== null ||
				$blockItems->lookupBlockId($id) !== null ||
				$blockDeserializer->getDeserializerForId($id) !== null ||
				$upgrader->upgrade($id, 0)[0] !== $id
			){
				continue;
			}

			$item = self::create($id, $components[$id] ?? null);
			$deserializer->map($id, fn() : Item => clone $item);
			$serializer->map($item, fn() : SavedItemData => new SavedItemData($id));

			$alias = VanillaData::alias($id);
			if($parser->parse($alias) === null){
				$parser->register($alias, fn() : Item => clone $item);
			}
			$count++;
		}
		return $count;
	}

	private static function create(string $id, ?CompoundTag $components) : Item{
		$identifier = new ItemIdentifier(ItemTypeIds::newId());
		$name = VanillaData::displayName($id);

		$durability = $components?->getCompoundTag("minecraft:durability")?->getTag("max_durability");
		if($durability instanceof IntTag && $durability->getValue() > 0){
			return new DummyDurableItem($identifier, $name, $durability->getValue());
		}

		return new DummyItem($identifier, $name, self::maxStackSize($components));
	}

	private static function maxStackSize(?CompoundTag $components) : int{
		$component = $components?->getCompoundTag("minecraft:max_stack_size")?->getTag("value");
		if($component instanceof ByteTag || $component instanceof IntTag){
			return $component->getValue();
		}
		$property = $components?->getCompoundTag("item_properties")?->getTag("max_stack_size");
		if($property instanceof IntTag){
			return $property->getValue();
		}
		return self::DEFAULT_MAX_STACK_SIZE;
	}
}
