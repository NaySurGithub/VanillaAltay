<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\movement;

use pocketmine\math\Vector3;
use function abs;
use function sqrt;

/**
 * Moves freely through the air in three dimensions, without gravity, at the
 * flying speed of the entity.
 */
class FlyNavigator extends PathNavigator{

	/** @var list<array{int, int, int, float}>|null */
	private static ?array $offsets = null;

	/**
	 * @return list<array{int, int, int, float}>
	 */
	private static function offsets() : array{
		if(self::$offsets === null){
			self::$offsets = [];
			for($dx = -1; $dx <= 1; $dx++){
				for($dy = -1; $dy <= 1; $dy++){
					for($dz = -1; $dz <= 1; $dz++){
						if($dx === 0 && $dy === 0 && $dz === 0){
							continue;
						}
						self::$offsets[] = [$dx, $dy, $dz, sqrt($dx * $dx + $dy * $dy + $dz * $dz)];
					}
				}
			}
		}
		return self::$offsets;
	}

	protected function canOccupy(int $x, int $y, int $z) : bool{
		if(!$this->isPassable($x, $y, $z)){
			return false;
		}
		if($this->isWater($x, $y, $z)){
			return $this->options->canSwim || !$this->options->avoidWater;
		}
		return true;
	}

	protected function isNodeValid(array $node) : bool{
		return $this->canOccupy($node[0], $node[1], $node[2]);
	}

	protected function isGoal(array $node, array $goal) : bool{
		return $node[0] === $goal[0] && $node[2] === $goal[2] && abs($node[1] - $goal[1]) <= 0;
	}

	protected function neighbors(array $node) : array{
		[$x, $y, $z] = $node;
		$result = [];
		foreach(self::offsets() as [$dx, $dy, $dz, $cost]){
			$nx = $x + $dx;
			$ny = $y + $dy;
			$nz = $z + $dz;
			if(!$this->canOccupy($nx, $ny, $nz)){
				continue;
			}
			if(($dx !== 0 && !$this->canOccupy($x + $dx, $y, $z)) || ($dz !== 0 && !$this->canOccupy($x, $y, $z + $dz)) || ($dy !== 0 && !$this->canOccupy($x, $y + $dy, $z))){
				continue;
			}
			$result[] = [[$nx, $ny, $nz], $cost + $this->cellPenalty($nx, $ny, $nz)];
		}
		return $result;
	}

	protected function nodePoint(array $node) : Vector3{
		return new Vector3($node[0] + 0.5, $node[1] + 0.1, $node[2] + 0.5);
	}

	protected function hasReachedNode(array $node, Vector3 $point) : bool{
		$location = $this->entity->getLocation();
		return $location->distanceSquared($point) <= 0.5;
	}

	protected function hasReachedDestination(Vector3 $destination) : bool{
		return $this->entity->getLocation()->distanceSquared($destination) <= $this->reachDistance * $this->reachDistance + 0.25;
	}

	protected function speed() : float{
		return $this->getFlyingSpeed() * $this->speedMultiplier;
	}

	protected function steer(Vector3 $point, bool $final) : void{
		$location = $this->entity->getLocation();
		$dx = $point->x - $location->x;
		$dy = $point->y - $location->y;
		$dz = $point->z - $location->z;
		$length = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
		if($length < 0.0001){
			return;
		}
		$speed = $this->speed();
		if($length < $speed){
			$speed = $length;
		}
		$this->entity->setMotion($this->applySway(new Vector3($dx / $length * $speed, $dy / $length * $speed, $dz / $length * $speed)));
		$this->face($dx, $dy, $dz, true);
	}

	protected function idle() : void{
		if($this->entity->hasGravity()){
			return;
		}
		$motion = $this->entity->getMotion();
		$this->entity->setMotion(new Vector3($motion->x * 0.9, $motion->y * 0.6, $motion->z * 0.9));
	}
}
