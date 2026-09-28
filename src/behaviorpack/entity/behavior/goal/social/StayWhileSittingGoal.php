<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\social;

use behaviorpack\entity\behavior\Goal;

/**
 * "minecraft:behavior.stay_while_sitting": keeps the entity in place while
 * it sits.
 */
class StayWhileSittingGoal extends Goal{

	public function getControls() : int{
		return self::MOVE | self::JUMP;
	}

	public function canUse() : bool{
		return $this->entity->getData("sitting", false) === true;
	}

	public function isInterruptable() : bool{
		return false;
	}

	public function start() : void{
		$this->entity->getNavigator()->stop();
	}

	public function tick(int $tickDiff) : void{
		$navigator = $this->entity->getNavigator();
		if(!$navigator->isDone()){
			$navigator->stop();
		}
	}
}
