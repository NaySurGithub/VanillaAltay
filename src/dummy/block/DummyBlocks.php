<?php

declare(strict_types=1);

namespace dummy\block;

use dummy\VanillaData;
use pocketmine\block\Block;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockToolType;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\bedrock\block\convert\BlockStateReader;
use pocketmine\item\StringToItemParser;
use pocketmine\item\ToolTier;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use function count;
use function is_float;
use function is_int;
use function max;
use function min;
use function round;

/**
 * Registers a dummy block for every block of the vanilla palette that has no
 * deserializer once every other module is loaded.
 */
final class DummyBlocks{

	private const TOOL_TAGS = [
		"minecraft:is_pickaxe_item_destructible" => BlockToolType::PICKAXE,
		"minecraft:is_axe_item_destructible" => BlockToolType::AXE,
		"minecraft:is_shovel_item_destructible" => BlockToolType::SHOVEL,
		"minecraft:is_hoe_item_destructible" => BlockToolType::HOE,
		"minecraft:is_shears_item_destructible" => BlockToolType::SHEARS,
		"minecraft:is_sword_item_destructible" => BlockToolType::SWORD
	];

	private function __construct(){
	}

	/**
	 * Picks a type id for every vanilla block the server cannot deserialize.
	 *
	 * @return array<string, int> block name => type id
	 */
	public static function findMissing() : array{
		$deserializer = GlobalBlockStateHandlers::getDeserializer();
		$typeIds = [];
		foreach(VanillaData::blockStates() as $name => $states){
			if($deserializer->getDeserializerForId($name) !== null || count($states) > 1 << Block::INTERNAL_STATE_DATA_BITS){
				continue;
			}
			$typeIds[$name] = BlockTypeIds::newId();
		}
		return $typeIds;
	}

	/**
	 * Registers the given blocks on the current thread. Every thread must use the type ids chosen on the main thread
	 * so runtime state ids match everywhere.
	 *
	 * @param array<string, int> $typeIds block name => type id
	 */
	public static function register(array $typeIds) : void{
		$palette = VanillaData::blockStates();
		$properties = VanillaData::blockProperties();
		$tags = VanillaData::blockTags();
		$registry = RuntimeBlockStateRegistry::getInstance();
		$serializer = GlobalBlockStateHandlers::getSerializer();
		$deserializer = GlobalBlockStateHandlers::getDeserializer();
		$parser = StringToItemParser::getInstance();

		foreach($typeIds as $name => $typeId){
			$states = $palette[$name] ?? null;
			if($states === null || $deserializer->getDeserializerForId($name) !== null){
				continue;
			}
			$blockProperties = $properties[$name] ?? [];
			$blockTags = $tags[$name] ?? [];
			$type = self::createType($name, $states, $blockProperties);
			$block = new DummyBlock(
				new BlockIdentifier($typeId),
				VanillaData::displayName($name),
				new BlockTypeInfo(self::createBreakInfo($blockProperties, $blockTags)),
				$type
			);

			$registry->register($block);
			$deserializer->map($name, fn(BlockStateReader $in) : Block => (clone $block)->setStateIndex($type->read($in)));
			$serializer->map($block, fn(DummyBlock $block) : BlockStateData => $block->getStateData());

			$alias = VanillaData::alias($name);
			if($parser->parse($alias) === null){
				$parser->registerBlock($alias, fn() : Block => clone $block);
			}
		}
	}

	/**
	 * @param list<BlockStateData>  $states
	 * @param array<string, mixed>  $properties
	 */
	private static function createType(string $name, array $states, array $properties) : DummyBlockType{
		$hardness = self::number($properties, "hardness");
		return new DummyBlockType(
			$name,
			$states,
			max(0, min(15, (int) round(self::number($properties, "brightness") * 15))),
			self::number($properties, "opacity", 1.0) < 1.0,
			$hardness === 0.0,
			self::number($properties, "friction", 0.6),
			(int) self::number($properties, "flameEncouragement"),
			(int) self::number($properties, "flammability")
		);
	}

	/**
	 * @param array<string, mixed> $properties
	 * @param array<string, true>  $tags
	 */
	private static function createBreakInfo(array $properties, array $tags) : BlockBreakInfo{
		$hardness = self::number($properties, "hardness");
		$blastResistance = self::number($properties, "blastResistance") * 5;
		if($hardness < 0){
			return BlockBreakInfo::indestructible();
		}

		$toolType = BlockToolType::NONE;
		foreach(self::TOOL_TAGS as $tag => $tool){
			if(isset($tags[$tag])){
				$toolType |= $tool;
			}
		}

		$tier = match(true){
			isset($tags["minecraft:diamond_tier_destructible"]) || isset($tags["minecraft:diamond_pick_diggable"]) => ToolTier::DIAMOND,
			isset($tags["minecraft:iron_tier_destructible"]) || isset($tags["minecraft:iron_pick_diggable"]) => ToolTier::IRON,
			isset($tags["minecraft:stone_tier_destructible"]) || isset($tags["minecraft:stone_pick_diggable"]) => ToolTier::STONE,
			default => null
		};

		return new BlockBreakInfo($hardness, $toolType, $tier?->getHarvestLevel() ?? 0, $blastResistance);
	}

	/**
	 * @param array<string, mixed> $properties
	 */
	private static function number(array $properties, string $key, float $default = 0.0) : float{
		$value = $properties[$key] ?? null;
		return is_float($value) || is_int($value) ? (float) $value : $default;
	}
}
