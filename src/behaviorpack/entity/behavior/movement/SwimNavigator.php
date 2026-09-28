<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\movement;

use pocketmine\math\Vector3;
use function sqrt;

/**
 * Swims through water in three dimensions. With "can_breach" the path may
 * leave the water for the cell right above its surface.
 */
class SwimNavigator extends FlyNavigator{

	protected function canOccupy(int $x, int $y, int $z) : bool{
		if(!$this->isPassable($x, $y, $z)){
			return false;
		}
		if($this->isWater($x, $y, $z)){
			return true;
		}
		if($this->options->canBreach && $this->isWater($x, $y - 1, $z)){
			return true;
		}
		return $this->options->canWalk && $this->supportHeight($x, $y, $z) !== null;
	}

	protected function speed() : float{
		return $this->getBaseSpeed() * $this->speedMultiplier;
	}

	protected function steer(Vector3 $point, bool $final) : void{
		if($this->entity->isInWater()){
			parent::steer($point, $final);
			return;
		}
		$location = $this->entity->getLocation();
		$dx = $point->x - $location->x;
		$dz = $point->z - $location->z;
		$length = sqrt($dx * $dx + $dz * $dz);
		if(!$this->options->canWalk || $length < 0.0001){
			return;
		}
		$speed = $this->speed();
		$this->applyHorizontal($dx / $length * $speed, $dz / $length * $speed);
		$this->face($dx, 0, $dz, false);
	}

	protected function idle() : void{
		if(!$this->entity->isInWater() || $this->options->canSink){
			return;
		}
		$motion = $this->entity->getMotion();
		$this->entity->setMotion(new Vector3($motion->x * 0.9, $motion->y < 0 ? 0.0 : $motion->y, $motion->z * 0.9));
	}
}
