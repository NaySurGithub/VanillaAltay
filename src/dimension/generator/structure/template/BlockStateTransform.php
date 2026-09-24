<?php

declare(strict_types=1);

namespace dimension\generator\structure\template;

use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\Tag;
use function str_ends_with;
use function str_replace;

/**
 * Turns and mirrors the orientation states of a Bedrock block state so that
 * a block keeps facing the same way relative to a rotated or mirrored
 * structure.
 */
final class BlockStateTransform{

	private const WALL_EAST = "wall_connection_type_east";
	private const WALL_NORTH = "wall_connection_type_north";
	private const WALL_SOUTH = "wall_connection_type_south";
	private const WALL_WEST = "wall_connection_type_west";
	private const CONNECTION_EAST = "minecraft:connection_east";
	private const CONNECTION_NORTH = "minecraft:connection_north";
	private const CONNECTION_SOUTH = "minecraft:connection_south";
	private const CONNECTION_WEST = "minecraft:connection_west";

	private const LEVER_DIRECTIONS = [
		"down_east_west",
		"east",
		"west",
		"south",
		"north",
		"up_north_south",
		"up_east_west",
		"down_north_south"
	];

	private function __construct(){
	}

	public static function rotate(BlockStateData $state, int $rotation) : BlockStateData{
		return match($rotation & 3){
			Rotation::ROTATE_90 => self::clockwise90($state),
			Rotation::ROTATE_180 => self::clockwise180($state),
			Rotation::ROTATE_270 => self::counterclockwise90($state),
			default => $state
		};
	}

	public static function clockwise180(BlockStateData $state) : BlockStateData{
		return self::clockwise90(self::clockwise90($state));
	}

	public static function counterclockwise90(BlockStateData $state) : BlockStateData{
		return self::clockwise90(self::clockwise90(self::clockwise90($state)));
	}

	/**
	 * Mirror flipping north and south, east and west unchanged.
	 */
	public static function mirrorZ(BlockStateData $state) : BlockStateData{
		return self::clockwise180(self::mirrorX($state));
	}

	public static function clockwise90(BlockStateData $state) : BlockStateData{
		$name = $state->getName();
		$states = $state->getStates();
		if($states === []){
			return $state;
		}
		$isJigsaw = $name === "minecraft:jigsaw";
		$isTrapdoor = str_ends_with($name, "trapdoor");
		$result = $states;
		foreach($states as $key => $tag){
			$value = $tag->getValue();
			switch($key){
				case "torch_facing_direction":
				case "minecraft:facing_direction":
				case "minecraft:block_face":
				case "minecraft:cardinal_direction":
					$result[$key] = new StringTag(self::rotateFaceName((string) $value));
					break;
				case "rail_direction":
					$result[$key] = new IntTag(match((int) $value){
						0 => 1,
						2 => 5,
						3 => 4,
						4 => 2,
						5 => 3,
						6 => 7,
						7 => 8,
						8 => 9,
						9 => 6,
						default => 0
					});
					break;
				case "weirdo_direction":
					$result[$key] = new IntTag(match((int) $value){
						0 => 2,
						1 => 3,
						2 => 1,
						default => 0
					});
					break;
				case "direction":
					$result[$key] = new IntTag($isTrapdoor ? self::rotateTrapdoorDirection((int) $value) : ((int) $value + 1) % 4);
					break;
				case "ground_sign_direction":
					$result[$key] = new IntTag(((int) $value + 4) % 16);
					break;
				case "facing_direction":
					$result[$key] = new IntTag($isJigsaw ? self::rotateFacingCounterclockwise((int) $value) : self::rotateFacingClockwise((int) $value));
					break;
				case "rotation":
					if($isJigsaw){
						$result[$key] = new IntTag(((int) $value + 1) & 3);
					}
					break;
				case "lever_direction":
					$result[$key] = new StringTag(self::rotateLever((string) $value));
					break;
				case "pillar_axis":
					$result[$key] = new StringTag(match((string) $value){
						"x" => "z",
						"z" => "x",
						default => (string) $value
					});
					break;
				case "huge_mushroom_bits":
					$bits = (int) $value;
					$result[$key] = new IntTag($bits <= 10 ? ($bits * 3) % 10 : $bits);
					break;
				case "vine_direction_bits":
					$bits = (int) $value;
					$result[$key] = new IntTag((($bits << 1) | ($bits >> 3)) & 0xf);
					break;
			}
		}
		self::moveSides($states, $result, self::WALL_NORTH, self::WALL_EAST, self::WALL_SOUTH, self::WALL_WEST);
		self::moveSides($states, $result, self::CONNECTION_NORTH, self::CONNECTION_EAST, self::CONNECTION_SOUTH, self::CONNECTION_WEST);
		return BlockStateData::current($name, $result);
	}

	/**
	 * Mirror flipping east and west, north and south unchanged. Handed
	 * states such as door hinges and stair corners switch sides.
	 */
	public static function mirrorX(BlockStateData $state) : BlockStateData{
		$name = $state->getName();
		$states = $state->getStates();
		if($states === []){
			return $state;
		}
		$isJigsaw = $name === "minecraft:jigsaw";
		$isTrapdoor = str_ends_with($name, "trapdoor");
		$result = $states;
		foreach($states as $key => $tag){
			$value = $tag->getValue();
			switch($key){
				case "torch_facing_direction":
				case "minecraft:facing_direction":
				case "minecraft:block_face":
				case "minecraft:cardinal_direction":
					$result[$key] = new StringTag(match((string) $value){
						"east" => "west",
						"west" => "east",
						default => (string) $value
					});
					break;
				case "rail_direction":
					$result[$key] = new IntTag(match((int) $value){
						2 => 3,
						3 => 2,
						6 => 7,
						7 => 6,
						8 => 9,
						9 => 8,
						default => (int) $value
					});
					break;
				case "weirdo_direction":
					$result[$key] = new IntTag(match((int) $value){
						0 => 1,
						1 => 0,
						default => (int) $value
					});
					break;
				case "direction":
					if($isTrapdoor){
						$result[$key] = new IntTag(match((int) $value){
							0 => 1,
							1 => 0,
							default => (int) $value
						});
					}else{
						$result[$key] = new IntTag(match((int) $value){
							1 => 3,
							3 => 1,
							default => (int) $value
						});
					}
					break;
				case "ground_sign_direction":
					$result[$key] = new IntTag((16 - (int) $value) % 16);
					break;
				case "facing_direction":
					$facing = (int) $value;
					$extra = $facing & ~0x7;
					$result[$key] = new IntTag(match($facing & 0x7){
						4 => 5,
						5 => 4,
						default => $facing & 0x7
					} | $extra);
					break;
				case "rotation":
					if($isJigsaw){
						$result[$key] = new IntTag((4 - ((int) $value & 0x3)) & 0x3);
					}
					break;
				case "lever_direction":
					$result[$key] = new StringTag(match((string) $value){
						"east" => "west",
						"west" => "east",
						default => (string) $value
					});
					break;
				case "vine_direction_bits":
					$bits = (int) $value;
					$result[$key] = new IntTag(($bits & 0x1) | ($bits & 0x4) | (($bits & 0x2) << 2) | (($bits & 0x8) >> 2));
					break;
				case "door_hinge_bit":
					$result[$key] = new ByteTag((int) $value !== 0 ? 0 : 1);
					break;
				case "minecraft:corner":
					$result[$key] = new StringTag(self::swapHand((string) $value));
					break;
			}
		}
		self::swapSides($states, $result, self::WALL_EAST, self::WALL_WEST);
		self::swapSides($states, $result, self::CONNECTION_EAST, self::CONNECTION_WEST);
		return BlockStateData::current($name, $result);
	}

	private static function rotateFaceName(string $face) : string{
		return match($face){
			"north" => "east",
			"east" => "south",
			"south" => "west",
			"west" => "north",
			default => $face
		};
	}

	private static function swapHand(string $value) : string{
		return str_replace(["left", "right", "\0"], ["\0", "left", "right"], $value);
	}

	private static function rotateFacingClockwise(int $facing) : int{
		$extra = $facing & ~0x7;
		return match($facing & 0x7){
			2 => 5,
			5 => 3,
			3 => 4,
			4 => 2,
			default => $facing & 0x7
		} | $extra;
	}

	private static function rotateFacingCounterclockwise(int $facing) : int{
		$extra = $facing & ~0x7;
		return match($facing & 0x7){
			2 => 4,
			4 => 3,
			3 => 5,
			5 => 2,
			default => $facing & 0x7
		} | $extra;
	}

	/**
	 * Trapdoor directions are east, west, south, north for 0 to 3.
	 */
	private static function rotateTrapdoorDirection(int $direction) : int{
		return match($direction){
			0 => 2,
			2 => 1,
			1 => 3,
			3 => 0,
			default => $direction
		};
	}

	private static function rotateLever(string $direction) : string{
		$index = -1;
		foreach(self::LEVER_DIRECTIONS as $i => $name){
			if($name === $direction){
				$index = $i;
				break;
			}
		}
		if($index === -1){
			return $direction;
		}
		return self::LEVER_DIRECTIONS[match($index){
			1 => 3,
			2 => 4,
			3 => 2,
			4 => 1,
			5 => 6,
			6 => 5,
			7 => 0,
			default => 7
		}];
	}

	/**
	 * Each side takes the value of the side a quarter turn counterclockwise
	 * from it.
	 *
	 * @param array<string, Tag> $states
	 * @param array<string, Tag> $result
	 */
	private static function moveSides(array $states, array &$result, string $north, string $east, string $south, string $west) : void{
		if(!isset($states[$north], $states[$east], $states[$south], $states[$west])){
			return;
		}
		$result[$east] = $states[$north];
		$result[$south] = $states[$east];
		$result[$west] = $states[$south];
		$result[$north] = $states[$west];
	}

	/**
	 * @param array<string, Tag> $states
	 * @param array<string, Tag> $result
	 */
	private static function swapSides(array $states, array &$result, string $first, string $second) : void{
		if(!isset($states[$first], $states[$second])){
			return;
		}
		$result[$first] = $states[$second];
		$result[$second] = $states[$first];
	}
}
