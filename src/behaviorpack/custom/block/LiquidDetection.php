<?php

declare(strict_types=1);

namespace behaviorpack\custom\block;

use behaviorpack\BehaviorPackException;
use pocketmine\block\Block;
use pocketmine\block\Lava;
use pocketmine\block\Liquid;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;

/**
 * The minecraft:liquid_detection component: how the block reacts when a
 * liquid touches it.
 */
final class LiquidDetection{

	public const BLOCKING = "blocking";
	public const BROKEN = "broken";
	public const POPPED = "popped";
	public const NO_REACTION = "no_reaction";

	private const REACTIONS = [self::BLOCKING, self::BROKEN, self::POPPED, self::NO_REACTION];
	private const DIRECTIONS = ["up", "down", "north", "south", "east", "west"];

	private function __construct(){
	}

	/**
	 * @return list<array{liquid_type: string, can_contain_liquid: bool, on_liquid_touches: string, stops_liquid_flowing_from_direction: list<string>, use_liquid_clipping: bool}>
	 * @throws BehaviorPackException
	 */
	public static function rules(mixed $value) : array{
		if(!is_array($value)){
			throw new BehaviorPackException("minecraft:liquid_detection must be an object");
		}
		$rawRules = $value["detection_rules"] ?? [];
		if(!is_array($rawRules)){
			throw new BehaviorPackException("detection_rules must be an array");
		}
		$rules = [];
		foreach($rawRules as $rule){
			if(!is_array($rule)){
				throw new BehaviorPackException("a detection rule must be an object");
			}
			$type = $rule["liquid_type"] ?? "water";
			$reaction = $rule["on_liquid_touches"] ?? self::BLOCKING;
			if(!is_string($type) || !is_string($reaction) || !in_array($reaction, self::REACTIONS, true)){
				throw new BehaviorPackException("invalid liquid_type or on_liquid_touches");
			}
			$directions = [];
			$rawDirections = $rule["stops_liquid_flowing_from_direction"] ?? [];
			if(!is_array($rawDirections)){
				throw new BehaviorPackException("stops_liquid_flowing_from_direction must be an array");
			}
			foreach($rawDirections as $direction){
				if(!is_string($direction) || !in_array($direction, self::DIRECTIONS, true)){
					throw new BehaviorPackException("invalid direction in stops_liquid_flowing_from_direction");
				}
				$directions[] = $direction;
			}
			$contain = $rule["can_contain_liquid"] ?? false;
			$clipping = $rule["use_liquid_clipping"] ?? true;
			$rules[] = [
				"liquid_type" => $type,
				"can_contain_liquid" => is_bool($contain) && $contain,
				"on_liquid_touches" => $reaction,
				"stops_liquid_flowing_from_direction" => $directions,
				"use_liquid_clipping" => !is_bool($clipping) || $clipping
			];
		}
		return $rules;
	}

	/**
	 * @throws BehaviorPackException
	 */
	public static function component(mixed $value) : TagComponent{
		$list = [];
		foreach(self::rules($value) as $rule){
			$directions = [];
			foreach($rule["stops_liquid_flowing_from_direction"] as $direction){
				$directions[] = new StringTag($direction);
			}
			$list[] = CompoundTag::create()
				->setString("liquid_type", $rule["liquid_type"])
				->setByte("can_contain_liquid", $rule["can_contain_liquid"] ? 1 : 0)
				->setString("on_liquid_touches", $rule["on_liquid_touches"])
				->setTag("stops_liquid_flowing_from_direction", new ListTag($directions))
				->setByte("use_liquid_clipping", $rule["use_liquid_clipping"] ? 1 : 0);
		}
		return new TagComponent("minecraft:liquid_detection", CompoundTag::create()->setTag("detection_rules", new ListTag($list)));
	}

	/**
	 * Returns the reaction of the block to the given liquid.
	 */
	public static function reaction(mixed $value, Liquid $liquid) : string{
		try{
			$rules = self::rules($value);
		}catch(BehaviorPackException){
			return self::BLOCKING;
		}
		$type = $liquid instanceof Lava ? "lava" : "water";
		foreach($rules as $rule){
			if($rule["liquid_type"] === $type){
				return $rule["on_liquid_touches"];
			}
		}
		return self::BLOCKING;
	}

	/**
	 * Returns whether a liquid may flow into the block and replace it.
	 */
	public static function canBeFlowedInto(mixed $value) : bool{
		try{
			$rules = self::rules($value);
		}catch(BehaviorPackException){
			return false;
		}
		foreach($rules as $rule){
			if($rule["on_liquid_touches"] === self::BROKEN || $rule["on_liquid_touches"] === self::POPPED){
				return true;
			}
		}
		return false;
	}

	/**
	 * Breaks or pops the block when a liquid touches it from above or from
	 * a side, returns whether the block was removed.
	 */
	public static function react(mixed $value, Block $block) : bool{
		$position = $block->getPosition();
		if(!$position->isValid()){
			return false;
		}
		$world = $position->getWorld();
		foreach(Facing::ALL as $side){
			if($side === Facing::DOWN){
				continue;
			}
			$neighbour = $world->getBlock($position->getSide($side));
			if(!$neighbour instanceof Liquid){
				continue;
			}
			$reaction = self::reaction($value, $neighbour);
			if($reaction === self::BROKEN){
				$world->setBlock($position, VanillaBlocks::AIR());
				return true;
			}
			if($reaction === self::POPPED){
				$world->useBreakOn($position);
				return true;
			}
		}
		return false;
	}
}
