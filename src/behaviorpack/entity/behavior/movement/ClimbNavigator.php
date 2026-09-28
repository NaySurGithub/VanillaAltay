<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\movement;

use pocketmine\math\Vector3;

/**
 * Walks like the walk navigation and also climbs up and down the walls.
 */
class ClimbNavigator extends WalkNavigator{

	private const SIDES = [[1, 0], [-1, 0], [0, 1], [0, -1]];

	protected function isAgainstWall(int $x, int $y, int $z) : bool{
		foreach(self::SIDES as [$dx, $dz]){
			if($this->isObstacle($this->block($x + $dx, $y, $z + $dz))){
				return true;
			}
		}
		return false;
	}

	protected function canStand(int $x, int $y, int $z) : bool{
		return parent::canStand($x, $y, $z) || $this->isAgainstWall($x, $y, $z);
	}

	protected function neighbors(array $node) : array{
		$result = parent::neighbors($node);
		[$x, $y, $z] = $node;
		foreach([1, -1] as $dy){
			$ny = $y + $dy;
			if(!$this->isPassable($x, $ny, $z)){
				continue;
			}
			if($this->isAgainstWall($x, $ny, $z) || $this->isAgainstWall($x, $y, $z)){
				$result[] = [[$x, $ny, $z], 1.5 + $this->cellPenalty($x, $ny, $z)];
			}
		}
		return $result;
	}

	protected function climbing() : bool{
		if(parent::climbing()){
			return true;
		}
		return $this->entity->isCollidedHorizontally && !$this->entity->isOnGround();
	}

	protected function steer(Vector3 $point, bool $final) : void{
		parent::steer($point, $final);
		$location = $this->entity->getLocation();
		if($this->entity->isCollidedHorizontally && $point->y > $location->y + 0.1){
			$motion = $this->entity->getMotion();
			$this->entity->setMotion(new Vector3($motion->x, 0.2, $motion->z));
		}
	}
}
