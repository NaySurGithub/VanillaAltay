<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\movement;

use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use function floor;

/**
 * "minecraft:can_climb": lets the entity climb ladders and vines. Inside a
 * climbable block it goes up while pushing against a wall and falls slowly
 * otherwise.
 */
class CanClimbSystem extends EntitySystem{

	public const CLIMB_SPEED = 0.2;
	public const MAX_CLIMB_FALL = 0.15;

	public function onAdd() : void{
		$this->entity->setCanClimb(true);
		$this->entity->setData("can_climb", true);
	}

	public function onRemove() : void{
		if(!$this->entity->hasComponent("minecraft:can_climb") && !$this->entity->hasComponent("minecraft:block_climber")){
			$this->entity->setCanClimb(false);
		}
		$this->entity->setData("can_climb", null);
	}

	protected function isClimbable(Block $block) : bool{
		return $block->canClimb();
	}

	public function tick(int $tickDiff) : void{
		$location = $this->entity->getLocation();
		$block = $this->entity->getWorld()->getBlockAt((int) floor($location->x), (int) floor($location->y), (int) floor($location->z));
		if(!$this->isClimbable($block)){
			return;
		}
		$motion = $this->entity->getMotion();
		if($this->entity->isCollidedHorizontally){
			$this->entity->setMotion(new Vector3($motion->x, self::CLIMB_SPEED, $motion->z));
		}elseif($motion->y < -self::MAX_CLIMB_FALL){
			$this->entity->setMotion(new Vector3($motion->x, -self::MAX_CLIMB_FALL, $motion->z));
		}
		$this->entity->resetFallDistance();
	}
}
