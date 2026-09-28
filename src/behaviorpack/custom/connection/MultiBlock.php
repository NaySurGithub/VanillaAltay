<?php

declare(strict_types=1);

namespace behaviorpack\custom\connection;

use behaviorpack\custom\BehaviorPermutableBlock;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function is_int;

/**
 * The minecraft:multi_block trait: a block made of several parts stacked
 * vertically, each one holding its index in minecraft:multi_block_part.
 */
final class MultiBlock{

	public const PART = "minecraft:multi_block_part";

	private function __construct(){
	}

	public static function parts(BehaviorPermutableBlock $block) : int{
		return (int) ($block->getDefinition()["traits"]["multiBlockParts"] ?? 0);
	}

	/**
	 * Returns the side on which part N+1 sits relative to part N.
	 */
	public static function step(BehaviorPermutableBlock $block) : int{
		return ($block->getDefinition()["traits"]["multiBlockUp"] ?? true) ? Facing::UP : Facing::DOWN;
	}

	public static function partOf(BehaviorPermutableBlock $block) : int{
		$part = $block->getPropertyValue(self::PART);
		return is_int($part) ? $part : 0;
	}

	/**
	 * Returns the position of every part of the multi block the given part
	 * belongs to, indexed by part.
	 *
	 * @return array<int, Vector3>
	 */
	public static function positions(BehaviorPermutableBlock $block, Vector3 $position, int $part) : array{
		$step = self::step($block);
		$origin = $position->getSide(Facing::opposite($step), $part);
		$positions = [];
		for($i = 0, $parts = self::parts($block); $i < $parts; $i++){
			$positions[$i] = $origin->getSide($step, $i);
		}
		return $positions;
	}

	/**
	 * Returns whether every part of the multi block is still in place.
	 */
	public static function isIntact(BehaviorPermutableBlock $block, World $world, Vector3 $position) : bool{
		foreach(self::positions($block, $position, self::partOf($block)) as $i => $partPosition){
			$other = $world->getBlock($partPosition);
			if(!$other instanceof BehaviorPermutableBlock || $other->getTypeId() !== $block->getTypeId() || self::partOf($other) !== $i){
				return false;
			}
		}
		return true;
	}

	/**
	 * Removes the other parts of the multi block without drops.
	 */
	public static function removeOthers(BehaviorPermutableBlock $block, World $world, Vector3 $position) : void{
		$part = self::partOf($block);
		foreach(self::positions($block, $position, $part) as $i => $partPosition){
			if($i === $part){
				continue;
			}
			$other = $world->getBlock($partPosition);
			if($other instanceof BehaviorPermutableBlock && $other->getTypeId() === $block->getTypeId() && self::partOf($other) === $i){
				$world->setBlock($partPosition, VanillaBlocks::AIR());
			}
		}
	}
}
