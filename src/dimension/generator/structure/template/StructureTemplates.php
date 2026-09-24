<?php

declare(strict_types=1);

namespace dimension\generator\structure\template;

use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use function count;
use function file_exists;
use function file_get_contents;
use function is_string;
use function rtrim;
use function zlib_decode;

/**
 * Reads the structure templates shipped in
 * dimension/structures/structures.nbt of the plugin resources. The file is
 * a gzip big-endian compound mapping a template name such as
 * "bastion/units/air_base" to its size, palette of block states, block
 * indices and jigsaw blocks. Palette states are upgraded to the running
 * block state version on load.
 *
 * Templates are read once per thread and never modified afterwards. A
 * missing or unreadable file yields no templates.
 */
final class StructureTemplates{

	public const FILE = "dimension/structures/structures.nbt";

	/** @var array<string, array<string, CompoundTag>> */
	private static array $sources = [];
	/** @var array<string, array<string, StructureTemplate|null>> */
	private static array $templates = [];

	private function __construct(){
	}

	public static function get(string $resourceFolder, string $name) : ?StructureTemplate{
		if(!isset(self::$sources[$resourceFolder])){
			self::$sources[$resourceFolder] = self::readFile($resourceFolder);
			self::$templates[$resourceFolder] = [];
		}
		if(!isset(self::$templates[$resourceFolder][$name]) && !isset(self::$sources[$resourceFolder][$name])){
			return null;
		}
		if(!isset(self::$templates[$resourceFolder][$name])){
			self::$templates[$resourceFolder][$name] = self::build(self::$sources[$resourceFolder][$name]);
			unset(self::$sources[$resourceFolder][$name]);
		}
		return self::$templates[$resourceFolder][$name];
	}

	/**
	 * @return array<string, CompoundTag>
	 */
	private static function readFile(string $resourceFolder) : array{
		if($resourceFolder === ""){
			return [];
		}
		$path = rtrim($resourceFolder, "/\\") . "/" . self::FILE;
		if(!file_exists($path)){
			return [];
		}
		$raw = @file_get_contents($path);
		if(!is_string($raw)){
			return [];
		}
		$decoded = @zlib_decode($raw);
		if(!is_string($decoded)){
			return [];
		}
		try{
			$root = (new BigEndianNbtSerializer())->read($decoded)->mustGetCompoundTag();
		}catch(\Exception){
			return [];
		}
		$sources = [];
		foreach($root->getValue() as $name => $tag){
			if($tag instanceof CompoundTag){
				$sources[$name] = $tag;
			}
		}
		return $sources;
	}

	private static function build(CompoundTag $tag) : ?StructureTemplate{
		$size = $tag->getIntArray("size", []);
		if(count($size) !== 3){
			return null;
		}
		$palette = [];
		$paletteTag = $tag->getListTag("palette");
		if($paletteTag !== null){
			foreach($paletteTag as $entry){
				$palette[] = $entry instanceof CompoundTag ? self::readState($entry) : null;
			}
		}
		$jigsaws = [];
		$jigsawTag = $tag->getListTag("jigsaw");
		if($jigsawTag instanceof ListTag){
			foreach($jigsawTag as $entry){
				if(!$entry instanceof CompoundTag){
					continue;
				}
				$pos = $entry->getIntArray("pos", []);
				if(count($pos) !== 3){
					continue;
				}
				$finalState = $entry->getTag("final_state");
				$jigsaws[] = new JigsawConnector(
					$pos[0],
					$pos[1],
					$pos[2],
					$finalState instanceof CompoundTag ? self::readState($finalState, true) : null,
					self::readString($entry, "name"),
					self::readString($entry, "joint"),
					self::readString($entry, "pool"),
					self::readString($entry, "target"),
					self::readInt($entry, "placement_priority"),
					self::readInt($entry, "selection_priority")
				);
			}
		}
		$blocks = $tag->getByteArray("blocks", "");
		return new StructureTemplate($size[0], $size[1], $size[2], $palette, $blocks, $jigsaws);
	}

	private static function readState(CompoundTag $tag, bool $keepVoid = false) : ?BlockStateData{
		try{
			$state = GlobalBlockStateHandlers::getUpgrader()->getBlockStateUpgrader()->upgrade(BlockStateData::fromNbt($tag));
		}catch(\Exception){
			return null;
		}
		if(!$keepVoid && $state->getName() === "minecraft:structure_void"){
			return null;
		}
		return $state;
	}

	private static function readString(CompoundTag $tag, string $name) : string{
		$value = $tag->getTag($name);
		return $value instanceof StringTag ? $value->getValue() : "";
	}

	private static function readInt(CompoundTag $tag, string $name) : int{
		$value = $tag->getTag($name);
		return $value instanceof IntTag ? $value->getValue() : 0;
	}
}
