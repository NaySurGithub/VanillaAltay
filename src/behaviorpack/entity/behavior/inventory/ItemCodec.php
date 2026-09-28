<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\inventory;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\loot\LootContext;
use behaviorpack\loot\LootTableRegistry;
use behaviorpack\recipe\RecipeItemResolver;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;
use function base64_decode;
use function base64_encode;
use function count;
use function ctype_digit;
use function explode;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function str_contains;
use function strtolower;

/**
 * Converts items to and from the JSON data store of the entities, and reads
 * the item descriptors of the entity definitions.
 */
final class ItemCodec{

	private function __construct(){
	}

	public static function encode(Item $item) : string{
		return base64_encode((new LittleEndianNbtSerializer())->write(new TreeRoot($item->nbtSerialize())));
	}

	public static function decode(mixed $encoded) : Item{
		if(!is_string($encoded) || $encoded === ""){
			return VanillaItems::AIR();
		}
		$raw = base64_decode($encoded, true);
		if($raw === false){
			return VanillaItems::AIR();
		}
		try{
			return Item::nbtDeserialize((new LittleEndianNbtSerializer())->read($raw)->mustGetCompoundTag());
		}catch(\Throwable){
			return VanillaItems::AIR();
		}
	}

	/**
	 * @param array<int, Item> $items
	 *
	 * @return array<string, string>
	 */
	public static function encodeList(array $items) : array{
		$encoded = [];
		foreach($items as $slot => $item){
			if(!$item->isNull()){
				$encoded[(string) $slot] = self::encode($item);
			}
		}
		return $encoded;
	}

	/**
	 * @return array<int, Item>
	 */
	public static function decodeList(mixed $encoded) : array{
		if(!is_array($encoded)){
			return [];
		}
		$items = [];
		foreach($encoded as $slot => $value){
			$item = self::decode($value);
			if(!$item->isNull()){
				$items[(int) $slot] = $item;
			}
		}
		return $items;
	}

	/**
	 * Reads an item identifier, optionally followed by ":<data>".
	 */
	public static function parse(string $descriptor, int $count = 1, int $data = -1) : ?Item{
		[$identifier, $meta] = self::split($descriptor);
		if($data >= 0){
			$meta = $data;
		}
		try{
			return RecipeItemResolver::item($identifier, $meta ?? 0, $count);
		}catch(\Throwable){
		}
		$item = StringToItemParser::getInstance()->parse($identifier);
		if($item === null){
			$item = StringToItemParser::getInstance()->parse(str_contains($identifier, ":") ? explode(":", $identifier, 2)[1] : $identifier);
		}
		return $item?->setCount($count);
	}

	/**
	 * @return array{string, ?int}
	 */
	private static function split(string $descriptor) : array{
		$descriptor = strtolower($descriptor);
		$parts = explode(":", $descriptor);
		if(count($parts) >= 3 && ctype_digit($parts[count($parts) - 1])){
			$meta = (int) $parts[count($parts) - 1];
			unset($parts[count($parts) - 1]);
			return [implode(":", $parts), $meta];
		}
		if(count($parts) === 1){
			return ["minecraft:" . $descriptor, null];
		}
		return [$descriptor, null];
	}

	/**
	 * Returns whether an item matches a descriptor: an identifier string or
	 * an object with "item" and "data".
	 */
	public static function matches(Item $item, mixed $descriptor) : bool{
		if($item->isNull()){
			return false;
		}
		$data = -1;
		if(is_array($descriptor)){
			$data = is_int($descriptor["data"] ?? null) ? $descriptor["data"] : -1;
			$descriptor = $descriptor["item"] ?? $descriptor["name"] ?? null;
		}
		if(!is_string($descriptor) || $descriptor === ""){
			return false;
		}
		[, $meta] = self::split($descriptor);
		$expected = self::parse($descriptor, 1, $data);
		if($expected === null){
			return false;
		}
		return $item->equals($expected, $meta !== null || $data >= 0, false);
	}

	/**
	 * @param list<mixed> $descriptors
	 */
	public static function matchesAny(Item $item, array $descriptors) : bool{
		foreach($descriptors as $descriptor){
			if(self::matches($item, $descriptor)){
				return true;
			}
		}
		return false;
	}

	/**
	 * Rolls a loot table of the behavior packs for an entity.
	 *
	 * @return list<Item>
	 */
	public static function rollTable(mixed $path, BehaviorEntity $entity, ?Entity $killer = null) : array{
		if(!is_string($path) || $path === ""){
			return [];
		}
		$table = LootTableRegistry::get($path);
		if($table === null){
			return [];
		}
		return $table->roll(new LootContext($entity, $killer));
	}
}
