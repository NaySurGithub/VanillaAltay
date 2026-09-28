<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\social;

use behaviorpack\entity\behavior\Goal;
use behaviorpack\entity\BehaviorEntity;

/**
 * "minecraft:behavior.follow_parent": a baby follows the nearest adult of
 * its type.
 */
class FollowParentGoal extends Goal{

	private ?BehaviorEntity $parent = null;
	private int $repathTicks = 0;

	public function canUse() : bool{
		if(!$this->entity->hasComponent("minecraft:is_baby")){
			return false;
		}
		$parent = SocialHelper::nearestSameType($this->entity, 8.0, function(BehaviorEntity $other) : bool{
			return !$other->hasComponent("minecraft:is_baby");
		});
		if($parent === null || $parent->getPosition()->distanceSquared($this->entity->getPosition()) < 9){
			return false;
		}
		$this->parent = $parent;
		return true;
	}

	public function canContinue() : bool{
		$parent = $this->parent;
		if(!$this->entity->hasComponent("minecraft:is_baby") || !SocialHelper::isValid($parent, $this->entity)){
			return false;
		}
		$distance = $parent->getPosition()->distanceSquared($this->entity->getPosition());
		return $distance >= 9 && $distance <= 256;
	}

	public function start() : void{
		$this->repathTicks = 0;
	}

	public function stop() : void{
		$this->parent = null;
		$this->entity->getNavigator()->stop();
	}

	public function tick(int $tickDiff) : void{
		$parent = $this->parent;
		if($parent === null){
			return;
		}
		$this->repathTicks -= $tickDiff;
		if($this->repathTicks > 0){
			return;
		}
		$this->repathTicks = 10;
		$this->entity->getNavigator()->moveTo($parent->getPosition(), BehaviorEntity::toFloat($this->config["speed_multiplier"] ?? null, 1.0), 2.0);
	}
}
