<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\movement;

use pocketmine\math\Vector3;

/**
 * Walks to a random land position from time to time.
 */
class RandomStrollGoal extends MovementGoal{

	private ?Vector3 $destination = null;

	public function canUse() : bool{
		if($this->isSitting() || !$this->chance($this->int("interval", 120))){
			return false;
		}
		$this->destination = $this->findDestination();
		return $this->destination !== null;
	}

	protected function findDestination() : ?Vector3{
		return $this->randomLandPosition($this->int("xz_dist", 10), $this->int("y_dist", 7));
	}

	public function canContinue() : bool{
		return !$this->isSitting() && !$this->entity->getNavigator()->isDone();
	}

	public function start() : void{
		if($this->destination !== null){
			$this->entity->getNavigator()->moveTo($this->destination, $this->speed(), 0.5);
		}
	}

	public function stop() : void{
		$this->entity->getNavigator()->stop();
		$this->destination = null;
	}
}
