<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\movement;

use pocketmine\block\Block;

/**
 * "minecraft:block_climber": lets the entity climb scaffolding in addition
 * to the climbable blocks.
 */
final class BlockClimberSystem extends CanClimbSystem{

	public function onAdd() : void{
		$this->entity->setCanClimb(true);
		$this->entity->setData("block_climber", true);
	}

	public function onRemove() : void{
		if(!$this->entity->hasComponent("minecraft:can_climb")){
			$this->entity->setCanClimb(false);
		}
		$this->entity->setData("block_climber", null);
	}

	protected function isClimbable(Block $block) : bool{
		return $block->canClimb() || PathNavigator::blockName($block) === "minecraft:scaffolding";
	}
}
