<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\movement;

use pocketmine\math\Vector3;
use function floor;
use function mt_rand;

/**
 * Moves out of water to a nearby land position.
 */
class MoveToLandGoal extends MovementGoal{

	private ?Vector3 $destination = null;

	protected function isInRightMedium() : bool{
		return !$this->entity->isInWater();
	}

	protected function isValidTarget(int $x, int $y, int $z) : bool{
		return $this->isStandable($x, $y, $z);
	}

	public function canUse() : bool{
		if($this->isSitting() || $this->isInRightMedium()){
			return false;
		}
		$this->destination = $this->search();
		return $this->destination !== null;
	}

	private function search() : ?Vector3{
		$range = $this->int("search_range", 0);
		$height = $this->int("search_height", 1);
		$count = $this->int("search_count", 10);
		$origin = $this->entity->getPosition();
		$ox = (int) floor($origin->x);
		$oy = (int) floor($origin->y);
		$oz = (int) floor($origin->z);
		$range = $range > 0 ? $range : 8;
		$attempts = $count > 0 ? $count : ($range * 2 + 1) ** 2;
		for($i = 0; $i < $attempts; ++$i){
			$x = $ox + mt_rand(-$range, $range);
			$z = $oz + mt_rand(-$range, $range);
			for($dy = -$height; $dy <= $height; ++$dy){
				if($this->isValidTarget($x, $oy + $dy, $z)){
					return new Vector3($x + 0.5, $oy + $dy, $z + 0.5);
				}
			}
		}
		return null;
	}

	public function canContinue() : bool{
		if($this->destination === null || $this->isSitting()){
			return false;
		}
		$radius = $this->num("goal_radius", 0.5);
		return $this->entity->getPosition()->distanceSquared($this->destination) > $radius * $radius && !$this->entity->getNavigator()->isDone();
	}

	public function start() : void{
		if($this->destination !== null){
			$this->entity->getNavigator()->moveTo($this->destination, $this->speed(), $this->num("goal_radius", 0.5));
		}
	}

	public function stop() : void{
		$this->entity->getNavigator()->stop();
		$this->destination = null;
	}
}
