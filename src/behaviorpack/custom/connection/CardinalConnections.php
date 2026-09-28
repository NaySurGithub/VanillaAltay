<?php

declare(strict_types=1);

namespace behaviorpack\custom\connection;

use behaviorpack\custom\BehaviorBlock;
use behaviorpack\custom\BehaviorPermutableBlock;
use pocketmine\block\Block;
use pocketmine\block\Fence;
use pocketmine\block\FenceGate;
use pocketmine\block\Thin;
use pocketmine\block\utils\SupportType;
use pocketmine\block\Wall;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;

/**
 * The minecraft:connection trait with minecraft:cardinal_connections: one
 * boolean state per horizontal side, set when the block connects to the
 * neighbour on that side.
 */
final class CardinalConnections{

	public const STATES = [
		Facing::NORTH => "minecraft:connection_north",
		Facing::EAST => "minecraft:connection_east",
		Facing::SOUTH => "minecraft:connection_south",
		Facing::WEST => "minecraft:connection_west"
	];

	private function __construct(){
	}

	/**
	 * Sets the connection states of the block as if it stood at the given
	 * position.
	 */
	public static function apply(BehaviorPermutableBlock $block, World $world, Vector3 $position) : void{
		foreach(self::STATES as $side => $state){
			$neighbour = $world->getBlock($position->getSide($side));
			$block->setPropertyValue($state, self::connects($block, $side, $neighbour));
		}
	}

	/**
	 * Returns whether the block connects to the neighbour on the given side.
	 */
	public static function connects(Block $block, int $side, Block $neighbour) : bool{
		$opposite = Facing::opposite($side);
		if($neighbour instanceof BehaviorBlock){
			$rule = ConnectionRule::of($neighbour);
			if($rule !== null){
				return $rule->accepts($opposite, $block);
			}
			if(self::hasConnectionTrait($neighbour)){
				return true;
			}
			return $neighbour->getSupportType($opposite) === SupportType::FULL;
		}
		if($neighbour instanceof Fence || $neighbour instanceof Wall || $neighbour instanceof Thin){
			return true;
		}
		if($neighbour instanceof FenceGate){
			return Facing::axis($neighbour->getFacing()) !== Facing::axis($side) && Facing::axis($side) !== Axis::Y;
		}
		return $neighbour->getSupportType($opposite) === SupportType::FULL;
	}

	public static function hasConnectionTrait(Block $block) : bool{
		return $block instanceof BehaviorPermutableBlock && ($block->getDefinition()["traits"]["connection"] ?? false);
	}
}
