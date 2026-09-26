<?php

declare(strict_types=1);

namespace dummy;

use pocketmine\data\bedrock\BedrockDataFiles;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\BlockStateDictionary;
use pocketmine\utils\Filesystem;
use function is_array;
use function is_string;
use function json_decode;
use function str_replace;
use function strpos;
use function substr;
use function ucwords;
use function zlib_decode;
use const JSON_THROW_ON_ERROR;

/**
 * Reads the vanilla block and item data bundled with the server, so the dummy
 * content always follows the Minecraft version the server supports.
 */
final class VanillaData{

	private function __construct(){
	}

	/**
	 * @return array<string, list<BlockStateData>> block name => every state of the block, in palette order
	 */
	public static function blockStates() : array{
		$states = [];
		foreach(BlockStateDictionary::loadStatesFromPalette(Filesystem::fileGetContents(BedrockDataFiles::BLOCK_PALETTE_NBT)) as $state){
			$states[$state->getName()][] = $state;
		}
		return $states;
	}

	/**
	 * @return array<string, array<string, mixed>> block name => hardness, blast resistance, brightness, opacity...
	 */
	public static function blockProperties() : array{
		$table = json_decode(Filesystem::fileGetContents(BedrockDataFiles::BLOCK_PROPERTIES_TABLE_JSON), true, 512, JSON_THROW_ON_ERROR);
		$properties = [];
		if(!is_array($table)){
			return $properties;
		}
		foreach($table as $name => $entry){
			if(is_string($name) && is_array($entry)){
				$properties[$name] = $entry;
			}
		}
		return $properties;
	}

	/**
	 * @return array<string, array<string, true>> block name => set of tags carried by the block
	 */
	public static function blockTags() : array{
		$table = json_decode(Filesystem::fileGetContents(BedrockDataFiles::BLOCK_TAGS_JSON), true, 512, JSON_THROW_ON_ERROR);
		$tags = [];
		if(!is_array($table)){
			return $tags;
		}
		foreach($table as $tag => $names){
			if(!is_string($tag) || !is_array($names)){
				continue;
			}
			foreach($names as $name){
				if(is_string($name)){
					$tags[$name][$tag] = true;
				}
			}
		}
		return $tags;
	}

	/**
	 * @return list<string> every item id known by the client
	 */
	public static function itemIds() : array{
		$table = json_decode(Filesystem::fileGetContents(BedrockDataFiles::ITEM_PALETTE_JSON), true, 512, JSON_THROW_ON_ERROR);
		$ids = [];
		if(!is_array($table) || !is_array($table["items"] ?? null)){
			return $ids;
		}
		foreach($table["items"] as $entry){
			if(is_array($entry) && is_string($entry["name"] ?? null)){
				$ids[] = $entry["name"];
			}
		}
		return $ids;
	}

	/**
	 * @return array<string, CompoundTag> item id => components of the item, for component based items only
	 */
	public static function itemComponents() : array{
		$raw = zlib_decode(Filesystem::fileGetContents(BedrockDataFiles::ITEM_COMPONENTS_NBT));
		if($raw === false){
			return [];
		}
		$components = [];
		foreach((new BigEndianNbtSerializer())->read($raw)->mustGetCompoundTag()->getValue() as $id => $tag){
			if($tag instanceof CompoundTag){
				$componentsTag = $tag->getCompoundTag("components");
				if($componentsTag !== null){
					$components[$id] = $componentsTag;
				}
			}
		}
		return $components;
	}

	/**
	 * Turns a namespaced identifier into a readable English name, e.g. "minecraft:oak_shelf" into "Oak Shelf".
	 */
	public static function displayName(string $id) : string{
		return ucwords(str_replace("_", " ", self::alias($id)));
	}

	/**
	 * Returns the identifier without its namespace, e.g. "minecraft:oak_shelf" into "oak_shelf".
	 */
	public static function alias(string $id) : string{
		$separator = strpos($id, ":");
		return $separator === false ? $id : substr($id, $separator + 1);
	}
}
