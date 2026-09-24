<?php

declare(strict_types=1);

namespace dimension\generator\structure\fortress;

use dimension\generator\structure\template\StateBlocks;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\Tag;

/**
 * Block states used by the nether fortress pieces, in piece coordinates.
 */
final class FortressBlocks{

	/** @var array<string, BlockStateData> */
	private static array $states = [];

	private function __construct(){
	}

	public static function air() : BlockStateData{
		return self::get("minecraft:air");
	}

	public static function netherBricks() : BlockStateData{
		return self::get("minecraft:nether_brick");
	}

	public static function netherBrickFence() : BlockStateData{
		return self::get("minecraft:nether_brick_fence");
	}

	public static function soulSand() : BlockStateData{
		return self::get("minecraft:soul_sand");
	}

	public static function netherWart() : BlockStateData{
		return self::get("minecraft:nether_wart", ["age" => new IntTag(3)]);
	}

	public static function lava() : BlockStateData{
		return self::get("minecraft:lava");
	}

	public static function fallingLava() : BlockStateData{
		return self::get("minecraft:flowing_lava", ["liquid_depth" => new IntTag(8)]);
	}

	public static function spawner() : BlockStateData{
		return self::get("minecraft:mob_spawner");
	}

	/**
	 * @param int $direction weirdo direction: 0 east, 1 west, 2 south, 3 north
	 */
	public static function netherBrickStairs(int $direction) : BlockStateData{
		return self::get("minecraft:nether_brick_stairs", ["weirdo_direction" => new IntTag($direction)]);
	}

	public static function chest(string $cardinal) : BlockStateData{
		return self::get("minecraft:chest", ["minecraft:cardinal_direction" => new StringTag($cardinal)]);
	}

	/**
	 * @param array<string, Tag> $states
	 */
	private static function get(string $name, array $states = []) : BlockStateData{
		$key = $name;
		foreach($states as $state => $tag){
			$key .= ";" . $state . "=" . $tag->getValue();
		}
		return self::$states[$key] ??= StateBlocks::state($name, $states) ?? BlockStateData::current($name, $states);
	}
}
