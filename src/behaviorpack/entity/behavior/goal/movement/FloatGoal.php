<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\movement;

use pocketmine\math\Vector3;
use function count;
use function max;
use function mt_rand;

/**
 * Keeps the entity at the surface of water by swimming up.
 */
final class FloatGoal extends MovementGoal{

	public function getControls() : int{
		return self::JUMP;
	}

	public function canUse() : bool{
		if(!$this->entity->isInWater()){
			return false;
		}
		if($this->bool("sink_with_passengers", false) && count($this->entity->getPassengers()) > 0){
			return false;
		}
		return true;
	}

	public function isInterruptable() : bool{
		return false;
	}

	public function tick(int $tickDiff) : void{
		if(mt_rand(0, 99) >= 80){
			return;
		}
		$motion = $this->entity->getMotion();
		$this->entity->setMotion(new Vector3($motion->x, max($motion->y, 0.04 * 1.5), $motion->z));
	}
}
