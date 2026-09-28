<?php

declare(strict_types=1);

namespace behaviorpack\custom\connection;

use behaviorpack\custom\BehaviorBlock;
use behaviorpack\custom\BehaviorPermutableBlock;
use pocketmine\block\Block;
use pocketmine\block\Stair;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\World;
use Throwable;
use function array_flip;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function preg_match_all;
use function str_starts_with;
use function substr;

/**
 * The minecraft:corner_and_cardinal_direction placement state: stair-like
 * blocks take an inner or outer corner shape from the stairs behind or in
 * front of them.
 */
final class StairCorner{

	public const CORNER_AND_CARDINAL = "minecraft:corner_and_cardinal_direction";
	public const CORNER = "minecraft:corner";
	public const CORNER_VALUES = ["none", "inner_left", "inner_right", "outer_left", "outer_right"];
	public const CORNERABLE_TAG = "minecraft:cornerable_stairs";

	private function __construct(){
	}

	/**
	 * Reads the blocks_to_corner_with list of the placement_direction trait
	 * into descriptors: ["name", identifier] or ["tags", all, list of tags].
	 *
	 * @return list<array{string, bool|string, list<string>}>
	 */
	public static function readDescriptors(mixed $value) : array{
		if(!is_array($value)){
			return [];
		}
		$descriptors = [];
		foreach($value as $entry){
			if(is_string($entry)){
				$descriptors[] = ["name", $entry, []];
				continue;
			}
			if(!is_array($entry)){
				continue;
			}
			if(is_string($entry["name"] ?? null)){
				$descriptors[] = ["name", $entry["name"], []];
			}
			$tags = $entry["tags"] ?? null;
			if(is_string($tags)){
				$all = preg_match('/all_tags\s*\(/', $tags) === 1;
				preg_match_all('/[\'"]([^\'"]+)[\'"]/', $tags, $matches);
				$descriptors[] = ["tags", $all, $matches[1]];
			}
		}
		return $descriptors;
	}

	/**
	 * Sets the corner state of the block as if it stood at the given
	 * position, from its cardinal direction and vertical half.
	 */
	public static function apply(BehaviorPermutableBlock $block, World $world, Vector3 $position) : void{
		$self = self::stairInfo($block);
		if($self === null){
			return;
		}
		[$facing, $top] = $self;
		$clockwise = Facing::rotateY($facing, true);
		$corner = "none";
		$back = self::cornerFacing($block, $world, $position->getSide($facing), $facing, $top);
		if($back !== null && self::canTakeShape($block, $world, $position->getSide(Facing::opposite($back)), $facing, $top)){
			$corner = $back === $clockwise ? "outer_right" : "outer_left";
		}else{
			$front = self::cornerFacing($block, $world, $position->getSide(Facing::opposite($facing)), $facing, $top);
			if($front !== null && self::canTakeShape($block, $world, $position->getSide($front), $facing, $top)){
				$corner = $front === $clockwise ? "inner_right" : "inner_left";
			}
		}
		$block->setPropertyValue(self::CORNER, $corner);
	}

	/**
	 * Returns the facing of a perpendicular stair at the position that the
	 * block may corner with, or null.
	 */
	private static function cornerFacing(BehaviorPermutableBlock $block, World $world, Vector3 $position, int $facing, bool $top) : ?int{
		$side = $world->getBlock($position);
		if(!self::cornersWith($block, $side)){
			return null;
		}
		$info = self::stairInfo($side);
		if($info === null || $info[1] !== $top || Facing::axis($info[0]) === Facing::axis($facing)){
			return null;
		}
		return $info[0];
	}

	private static function canTakeShape(BehaviorPermutableBlock $block, World $world, Vector3 $position, int $facing, bool $top) : bool{
		$side = $world->getBlock($position);
		if(!self::cornersWith($block, $side)){
			return true;
		}
		$info = self::stairInfo($side);
		return $info === null || $info[0] !== $facing || $info[1] !== $top;
	}

	/**
	 * Returns [facing, top half] of a stair-like block, or null.
	 *
	 * @return array{int, bool}|null
	 */
	public static function stairInfo(Block $block) : ?array{
		if($block instanceof Stair){
			return [$block->getFacing(), $block->isUpsideDown()];
		}
		if(!$block instanceof BehaviorPermutableBlock || !($block->getDefinition()["traits"]["corner"] ?? false)){
			return null;
		}
		$direction = $block->getPropertyValue(BehaviorPermutableBlock::CARDINAL_DIRECTION);
		$facings = array_flip(BehaviorPermutableBlock::FACING_NAMES);
		if(!is_string($direction) || !isset($facings[$direction])){
			return null;
		}
		return [$facings[$direction], $block->getPropertyValue(BehaviorPermutableBlock::VERTICAL_HALF) === "top"];
	}

	/**
	 * Returns whether the block corners with the other block, following the
	 * blocks_to_corner_with list, or any stair-like block without one.
	 */
	private static function cornersWith(BehaviorPermutableBlock $block, Block $other) : bool{
		if(self::stairInfo($other) === null){
			return false;
		}
		$descriptors = $block->getDefinition()["traits"]["cornerWith"] ?? [];
		if(!is_array($descriptors) || $descriptors === []){
			return true;
		}
		$tags = self::tagsOf($other);
		$name = self::nameOf($other);
		foreach($descriptors as [$type, $value, $list]){
			if($type === "name" && $value === $name){
				return true;
			}
			if($type !== "tags"){
				continue;
			}
			$matched = 0;
			foreach($list as $tag){
				if(in_array($tag, $tags, true)){
					$matched++;
				}
			}
			if(($value === true && $matched === count($list) && $matched > 0) || ($value !== true && $matched > 0)){
				return true;
			}
		}
		return false;
	}

	/**
	 * @return list<string>
	 */
	private static function tagsOf(Block $block) : array{
		if($block instanceof Stair){
			return [self::CORNERABLE_TAG];
		}
		if(!$block instanceof BehaviorPermutableBlock){
			return [];
		}
		$tags = [];
		foreach($block->getDefinition()["components"] as $name => $value){
			$name = (string) $name;
			if(str_starts_with($name, "tag:")){
				$tags[] = substr($name, 4);
			}
		}
		return $tags;
	}

	private static function nameOf(Block $block) : ?string{
		if($block instanceof BehaviorBlock){
			return $block->getIdentifier();
		}
		try{
			return GlobalBlockStateHandlers::getSerializer()->serializeBlock($block)->getName();
		}catch(Throwable){
			return null;
		}
	}
}
