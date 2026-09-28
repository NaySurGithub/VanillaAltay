<?php

declare(strict_types=1);

namespace behaviorpack\custom\connection;

use behaviorpack\BehaviorPackException;
use behaviorpack\custom\BehaviorBlock;
use behaviorpack\custom\BehaviorPermutableBlock;
use customiesdevs\customies\block\component\BlockComponent;
use pocketmine\block\Block;
use pocketmine\block\Fence;
use pocketmine\math\Facing;
use function in_array;
use function is_array;
use function is_string;

/**
 * The minecraft:connection_rule block component: which blocks with the
 * minecraft:connection trait may connect to this block, and on which sides.
 */
final class ConnectionRule{

	public const ACCEPT_ALL = "all";
	public const ACCEPT_ONLY_FENCES = "only_fences";
	public const ACCEPT_NONE = "none";

	public const DIRECTION_FACINGS = [
		"north" => Facing::NORTH,
		"east" => Facing::EAST,
		"south" => Facing::SOUTH,
		"west" => Facing::WEST
	];

	/**
	 * @param list<int> $directions
	 */
	private function __construct(
		private string $acceptsFrom,
		private array $directions
	){
	}

	/**
	 * Validates a minecraft:connection_rule value for BlockComponentMapper.
	 * The rule is resolved on the server only, nothing is sent to the client.
	 *
	 * @throws BehaviorPackException
	 */
	public static function map(mixed $value) : ?BlockComponent{
		self::parse($value);
		return null;
	}

	/**
	 * @throws BehaviorPackException
	 */
	public static function parse(mixed $value) : self{
		if(!is_array($value)){
			throw new BehaviorPackException("minecraft:connection_rule must be an object");
		}
		$accepts = $value["accepts_connections_from"] ?? self::ACCEPT_ALL;
		if(!is_string($accepts) || !in_array($accepts, [self::ACCEPT_ALL, self::ACCEPT_ONLY_FENCES, self::ACCEPT_NONE], true)){
			throw new BehaviorPackException("accepts_connections_from must be \"all\", \"only_fences\" or \"none\"");
		}
		$rawDirections = $value["enabled_directions"] ?? null;
		if($rawDirections === null){
			return new self($accepts, [Facing::NORTH, Facing::EAST, Facing::SOUTH, Facing::WEST]);
		}
		if(!is_array($rawDirections)){
			throw new BehaviorPackException("enabled_directions must be a list");
		}
		$directions = [];
		foreach($rawDirections as $direction){
			if(!is_string($direction) || !isset(self::DIRECTION_FACINGS[$direction])){
				throw new BehaviorPackException("enabled_directions contains an invalid direction");
			}
			$directions[] = self::DIRECTION_FACINGS[$direction];
		}
		return new self($accepts, $directions);
	}

	/**
	 * Returns the rule of a behavior pack block in its current state, or null
	 * when the block has none.
	 */
	public static function of(Block $block) : ?self{
		if(!$block instanceof BehaviorBlock){
			return null;
		}
		$value = self::activeComponent($block, "minecraft:connection_rule");
		if($value === null){
			return null;
		}
		try{
			return self::parse($value);
		}catch(BehaviorPackException){
			return null;
		}
	}

	/**
	 * Returns the value of a component of a behavior pack block in its
	 * current state, permutations applied, or null when it is absent.
	 */
	public static function activeComponent(BehaviorBlock $block, string $name) : mixed{
		if($block instanceof BehaviorPermutableBlock){
			return $block->getActiveComponent($name);
		}
		$definition = (fn() : array => $this->definition)->call($block);
		return $definition["components"][$name] ?? null;
	}

	/**
	 * Returns whether the block with this rule accepts a connection coming
	 * from the given side of it, made by the given block.
	 */
	public function accepts(int $side, Block $source) : bool{
		if($this->acceptsFrom === self::ACCEPT_NONE || !in_array($side, $this->directions, true)){
			return false;
		}
		if($this->acceptsFrom === self::ACCEPT_ONLY_FENCES){
			return self::isFence($source);
		}
		return true;
	}

	/**
	 * Returns whether a block counts as a fence for the only_fences rule.
	 */
	public static function isFence(Block $block) : bool{
		if($block instanceof Fence){
			return true;
		}
		if(!$block instanceof BehaviorBlock){
			return false;
		}
		return self::activeComponent($block, "tag:minecraft:fence") !== null || self::activeComponent($block, "tag:fence") !== null;
	}
}
