<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\movement;

use function is_array;
use function is_bool;
use function is_string;
use function str_contains;

/**
 * The pathfinding flags of a "minecraft:navigation.*" component.
 */
final class NavigationOptions{

	/**
	 * @param list<string> $blocksToAvoid
	 */
	public function __construct(
		public bool $avoidWater = false,
		public bool $avoidSun = false,
		public bool $avoidDamageBlocks = false,
		public bool $avoidPortals = false,
		public bool $canPathOverWater = false,
		public bool $canPathOverLava = false,
		public bool $canFloat = false,
		public bool $canSwim = false,
		public bool $canWalk = true,
		public bool $canBreach = false,
		public bool $canJump = true,
		public bool $canOpenDoors = false,
		public bool $canOpenIronDoors = false,
		public bool $canPassDoors = true,
		public bool $canPathFromAir = false,
		public bool $canSink = true,
		public bool $canWalkInLava = false,
		public bool $isAmphibious = false,
		public array $blocksToAvoid = []
	){}

	/**
	 * @param array<mixed> $config
	 */
	public static function fromConfig(array $config) : self{
		$blocks = [];
		foreach(is_array($config["blocks_to_avoid"] ?? null) ? $config["blocks_to_avoid"] : [] as $entry){
			$name = is_array($entry) ? ($entry["name"] ?? null) : $entry;
			if(is_string($name)){
				$blocks[] = str_contains($name, ":") ? $name : "minecraft:" . $name;
			}
		}
		$amphibious = self::flag($config, "is_amphibious", false);
		return new self(
			self::flag($config, "avoid_water", false),
			self::flag($config, "avoid_sun", false),
			self::flag($config, "avoid_damage_blocks", false),
			self::flag($config, "avoid_portals", false),
			self::flag($config, "can_path_over_water", false),
			self::flag($config, "can_path_over_lava", false),
			self::flag($config, "can_float", false),
			self::flag($config, "can_swim", false) || $amphibious,
			self::flag($config, "can_walk", true),
			self::flag($config, "can_breach", false),
			self::flag($config, "can_jump", true),
			self::flag($config, "can_open_doors", false),
			self::flag($config, "can_open_iron_doors", false),
			self::flag($config, "can_pass_doors", true),
			self::flag($config, "can_path_from_air", false),
			self::flag($config, "can_sink", true),
			self::flag($config, "can_walk_in_lava", false),
			$amphibious,
			$blocks
		);
	}

	/**
	 * @param array<mixed> $config
	 */
	private static function flag(array $config, string $key, bool $default) : bool{
		$value = $config[$key] ?? null;
		return is_bool($value) ? $value : $default;
	}
}
