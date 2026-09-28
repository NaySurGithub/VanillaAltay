<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\movement;

use behaviorpack\entity\BehaviorEntity;
use pocketmine\math\Vector3;
use function floor;
use function is_array;
use function max;
use function min;
use function mt_rand;

/**
 * Hovers to a random position in the air, keeping between the hover heights
 * above the ground.
 */
final class RandomHoverGoal extends MovementGoal{

	private ?Vector3 $destination = null;

	public function canUse() : bool{
		if($this->isSitting() || !$this->chance($this->int("interval", 120))){
			return false;
		}
		$this->destination = $this->findDestination();
		return $this->destination !== null;
	}

	private function findDestination() : ?Vector3{
		$xz = $this->int("xz_dist", 10);
		$yDist = $this->int("y_dist", 7);
		$yOffset = $this->num("y_offset", 0.0);
		$heights = $this->config["hover_height"] ?? null;
		$minHeight = is_array($heights) ? BehaviorEntity::toFloat($heights[0] ?? null, 0.0) : 0.0;
		$maxHeight = is_array($heights) ? BehaviorEntity::toFloat($heights[1] ?? null, 0.0) : 0.0;
		$origin = $this->entity->getPosition();
		for($i = 0; $i < 10; ++$i){
			$x = floor($origin->x) + mt_rand(-$xz, $xz) + 0.5;
			$z = floor($origin->z) + mt_rand(-$xz, $xz) + 0.5;
			$y = floor($origin->y) + mt_rand(-$yDist, $yDist) + $yOffset;
			if(!$this->isPassableAt($x, $y, $z)){
				continue;
			}
			if($maxHeight > 0){
				$ground = $this->groundHeight($x, $y, $z, (int) $maxHeight + 4);
				if($ground !== null){
					$y = max($ground + $minHeight, min($ground + $maxHeight, $y));
				}
				if(!$this->isPassableAt($x, $y, $z)){
					continue;
				}
			}
			return new Vector3($x, $y, $z);
		}
		return null;
	}

	private function groundHeight(float $x, float $y, float $z, int $depth) : ?float{
		for($dy = 0; $dy <= $depth; ++$dy){
			if($this->blockAt($x, $y - $dy, $z)->isSolid()){
				return floor($y - $dy) + 1;
			}
		}
		return null;
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
