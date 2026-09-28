<?php

declare(strict_types=1);

namespace behaviorpack\custom\block;

use behaviorpack\BehaviorPackException;
use behaviorpack\custom\BehaviorBlock;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeTags;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\World;
use Throwable;
use function array_map;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function preg_match_all;
use function str_contains;
use function str_ends_with;

/**
 * The minecraft:placement_filter component: the faces and the blocks a
 * block may be placed on.
 */
final class PlacementFilter{

	private const FACES = [
		"down" => [Facing::DOWN],
		"up" => [Facing::UP],
		"north" => [Facing::NORTH],
		"south" => [Facing::SOUTH],
		"west" => [Facing::WEST],
		"east" => [Facing::EAST],
		"side" => [Facing::NORTH, Facing::SOUTH, Facing::WEST, Facing::EAST],
		"all" => Facing::ALL
	];

	private const VANILLA_TAGS = [
		"dirt" => ["minecraft:dirt", "minecraft:grass_block", "minecraft:podzol", "minecraft:mycelium", "minecraft:coarse_dirt", "minecraft:dirt_with_roots", "minecraft:farmland", "minecraft:moss_block", "minecraft:mud", "minecraft:muddy_mangrove_roots"],
		"grass" => ["minecraft:grass_block"],
		"sand" => ["minecraft:sand", "minecraft:red_sand"],
		"gravel" => ["minecraft:gravel"],
		"stone" => ["minecraft:stone", "minecraft:cobblestone", "minecraft:granite", "minecraft:diorite", "minecraft:andesite", "minecraft:deepslate", "minecraft:cobbled_deepslate", "minecraft:tuff", "minecraft:calcite", "minecraft:blackstone", "minecraft:basalt", "minecraft:netherrack", "minecraft:end_stone"],
		"snow" => ["minecraft:snow", "minecraft:snow_layer", "minecraft:powder_snow"],
		"fertilize_area" => ["minecraft:grass_block", "minecraft:dirt", "minecraft:podzol", "minecraft:mycelium", "minecraft:moss_block"]
	];

	private function __construct(){
	}

	/**
	 * @return list<array{faces: list<int>, filters: list<array{name: ?string, tags: ?string}>}>
	 * @throws BehaviorPackException
	 */
	public static function conditions(mixed $value) : array{
		if(!is_array($value)){
			throw new BehaviorPackException("minecraft:placement_filter must be an object");
		}
		$rawConditions = $value["conditions"] ?? [];
		if(!is_array($rawConditions)){
			throw new BehaviorPackException("conditions must be an array");
		}
		$conditions = [];
		foreach($rawConditions as $condition){
			if(!is_array($condition)){
				throw new BehaviorPackException("a placement condition must be an object");
			}
			$faces = [];
			$rawFaces = $condition["allowed_faces"] ?? ["all"];
			if(!is_array($rawFaces)){
				throw new BehaviorPackException("allowed_faces must be an array");
			}
			foreach($rawFaces as $face){
				if(!is_string($face) || !isset(self::FACES[$face])){
					throw new BehaviorPackException("invalid face in allowed_faces");
				}
				foreach(self::FACES[$face] as $facing){
					if(!in_array($facing, $faces, true)){
						$faces[] = $facing;
					}
				}
			}
			$filters = [];
			$rawFilters = $condition["block_filter"] ?? [];
			if(!is_array($rawFilters)){
				throw new BehaviorPackException("block_filter must be an array");
			}
			foreach($rawFilters as $filter){
				if(is_string($filter)){
					$filters[] = ["name" => self::normalizeName($filter), "tags" => null];
				}elseif(is_array($filter) && is_string($filter["name"] ?? null)){
					$filters[] = ["name" => self::normalizeName($filter["name"]), "tags" => null];
				}elseif(is_array($filter) && is_string($filter["tags"] ?? null)){
					$filters[] = ["name" => null, "tags" => $filter["tags"]];
				}else{
					throw new BehaviorPackException("a block_filter entry needs a name or tags");
				}
			}
			$conditions[] = ["faces" => $faces, "filters" => $filters];
		}
		return $conditions;
	}

	/**
	 * @throws BehaviorPackException
	 */
	public static function component(mixed $value) : TagComponent{
		$list = [];
		foreach(self::conditions($value) as $condition){
			$mask = 0;
			foreach($condition["faces"] as $facing){
				$mask |= 1 << $facing;
			}
			$filters = [];
			foreach($condition["filters"] as $filter){
				$tag = CompoundTag::create();
				if($filter["name"] !== null){
					$tag->setString("name", $filter["name"]);
				}
				if($filter["tags"] !== null){
					$tag->setString("tags", $filter["tags"]);
				}
				$filters[] = $tag;
			}
			$list[] = CompoundTag::create()
				->setByte("allowed_faces", $mask)
				->setTag("block_filter", new ListTag($filters));
		}
		return new TagComponent("minecraft:placement_filter", CompoundTag::create()->setTag("conditions", new ListTag($list)));
	}

	/**
	 * Returns whether the block may stand at the position when placed
	 * against the given face.
	 */
	public static function allowsFace(mixed $value, World $world, Vector3 $position, int $face) : bool{
		try{
			$conditions = self::conditions($value);
		}catch(BehaviorPackException){
			return true;
		}
		if(count($conditions) === 0){
			return true;
		}
		$support = $world->getBlock($position->getSide(Facing::opposite($face)));
		foreach($conditions as $condition){
			if(in_array($face, $condition["faces"], true) && self::matches($condition["filters"], $support)){
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns whether any allowed face of the block still has a valid
	 * supporting block.
	 */
	public static function isSupported(mixed $value, Block $block) : bool{
		$position = $block->getPosition();
		if(!$position->isValid()){
			return true;
		}
		foreach(Facing::ALL as $face){
			if(self::allowsFace($value, $position->getWorld(), $position, $face)){
				return true;
			}
		}
		return false;
	}

	/**
	 * @param list<array{name: ?string, tags: ?string}> $filters
	 */
	private static function matches(array $filters, Block $block) : bool{
		if(count($filters) === 0){
			return true;
		}
		$name = self::identifierOf($block);
		foreach($filters as $filter){
			if($filter["name"] !== null && $filter["name"] === $name){
				return true;
			}
			if($filter["tags"] !== null && self::matchesTags($filter["tags"], $block, $name)){
				return true;
			}
		}
		return false;
	}

	private static function matchesTags(string $expression, Block $block, ?string $name) : bool{
		if(preg_match_all("/'([^']*)'|\"([^\"]*)\"/", $expression, $matches) === 0){
			return false;
		}
		$tags = array_map(static fn(string $single, string $double) : string => $single !== "" ? $single : $double, $matches[1], $matches[2]);
		$all = preg_match("/all_tags/", $expression) === 1;
		foreach($tags as $tag){
			$has = self::hasTag($block, $name, $tag);
			if($all && !$has){
				return false;
			}
			if(!$all && $has){
				return true;
			}
		}
		return $all;
	}

	private static function hasTag(Block $block, ?string $name, string $tag) : bool{
		if($block instanceof BehaviorBlock){
			return $block->hasBlockTag($tag);
		}
		if($tag === "dirt" && $block->hasTypeTag(BlockTypeTags::DIRT)){
			return true;
		}
		if($tag === "sand" && $block->hasTypeTag(BlockTypeTags::SAND)){
			return true;
		}
		if($name === null){
			return false;
		}
		if(($tag === "log" || $tag === "wood") && (str_ends_with($name, "_log") || str_ends_with($name, "_wood") || str_ends_with($name, "_stem"))){
			return true;
		}
		return in_array($name, self::VANILLA_TAGS[$tag] ?? [], true);
	}

	private static function identifierOf(Block $block) : ?string{
		if($block instanceof BehaviorBlock){
			return $block->getIdentifier();
		}
		try{
			return GlobalBlockStateHandlers::getSerializer()->serializeBlock($block)->getName();
		}catch(Throwable){
			return null;
		}
	}

	private static function normalizeName(string $name) : string{
		return str_contains($name, ":") ? $name : "minecraft:" . $name;
	}
}
