<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\movement;

use pocketmine\entity\Entity;

/**
 * Looks at the current attack target when it is nearby.
 */
final class LookAtTargetGoal extends LookAtEntityGoal{

	protected function findTarget() : ?Entity{
		$target = $this->entity->getTargetEntity();
		if($target === null){
			return null;
		}
		$distance = $this->num("look_distance", 8.0);
		return $target->getPosition()->distanceSquared($this->entity->getPosition()) <= $distance * $distance ? $target : null;
	}
}
