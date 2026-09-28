<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\movement;

use pocketmine\math\Vector3;
use function ceil;
use function floor;
use function max;
use function sqrt;

/**
 * Walks on the ground: steps up blocks within the step or jump height, drops
 * down up to three blocks, climbs ladders when the entity can climb and swims
 * when the navigation allows it.
 */
class WalkNavigator extends PathNavigator{

	public const MAX_DROP = 3;

	private const DIRECTIONS = [
		[1, 0], [-1, 0], [0, 1], [0, -1],
		[1, 1], [1, -1], [-1, 1], [-1, -1]
	];

	protected function maxRise() : float{
		$step = max(0.0, $this->entity->getStepHeight());
		return $this->options->canJump ? max($step, 1.25) : $step;
	}

	protected function startNode() : array{
		$node = parent::startNode();
		if($this->options->canPathFromAir || $this->entity->isOnGround() || $this->canSwimAt($node[0], $node[1], $node[2])){
			return $node;
		}
		for($drop = 1; $drop <= self::MAX_DROP + 1; $drop++){
			if(!$this->isPassable($node[0], $node[1] - $drop, $node[2])){
				break;
			}
			if($this->supportHeight($node[0], $node[1] - $drop, $node[2]) !== null){
				return [$node[0], $node[1] - $drop, $node[2]];
			}
		}
		return $node;
	}

	protected function canSwimAt(int $x, int $y, int $z) : bool{
		return $this->options->canSwim && $this->isWater($x, $y, $z);
	}

	/**
	 * Returns whether the entity can stay in a cell: on a support, in water it
	 * swims in, or on a ladder it climbs.
	 */
	protected function canStand(int $x, int $y, int $z) : bool{
		if($this->supportHeight($x, $y, $z) !== null){
			return true;
		}
		if($this->canSwimAt($x, $y, $z)){
			return true;
		}
		return $this->canClimbAt($x, $y, $z);
	}

	protected function canClimbAt(int $x, int $y, int $z) : bool{
		return $this->entity->canClimb() && $this->block($x, $y, $z)->canClimb();
	}

	protected function standHeight(int $x, int $y, int $z) : float{
		return $this->supportHeight($x, $y, $z) ?? (float) $y;
	}

	protected function neighbors(array $node) : array{
		[$x, $y, $z] = $node;
		$result = [];
		$maxRise = $this->maxRise();
		$maxUp = (int) ceil($maxRise);
		$here = $this->standHeight($x, $y, $z);
		foreach(self::DIRECTIONS as [$dx, $dz]){
			$diagonal = $dx !== 0 && $dz !== 0;
			if($diagonal && (!$this->isPassable($x + $dx, $y, $z) || !$this->isPassable($x, $y, $z + $dz))){
				continue;
			}
			$nx = $x + $dx;
			$nz = $z + $dz;
			$target = null;
			$extra = 0.0;
			for($up = 0; $up <= $maxUp; $up++){
				$ny = $y + $up;
				if($up > 0 && !$this->isPassable($x, $ny, $z)){
					break;
				}
				if(!$this->isPassable($nx, $ny, $nz)){
					continue;
				}
				if($this->canStand($nx, $ny, $nz)){
					if($this->standHeight($nx, $ny, $nz) - $here <= $maxRise + 0.001){
						$target = $ny;
						$extra = $up * 0.5;
					}
				}elseif($up === 0){
					for($drop = 1; $drop <= self::MAX_DROP; $drop++){
						if(!$this->isPassable($nx, $ny - $drop, $nz)){
							break;
						}
						if($this->canStand($nx, $ny - $drop, $nz)){
							$target = $ny - $drop;
							$extra = $drop * 0.25;
							break;
						}
					}
				}
				break;
			}
			if($target === null){
				continue;
			}
			if(!$this->options->canWalk && !$this->canSwimAt($nx, $target, $nz)){
				continue;
			}
			if($this->options->avoidWater && $this->isWater($nx, $target, $nz) && !$this->isWater($x, $y, $z)){
				continue;
			}
			$result[] = [[$nx, $target, $nz], ($diagonal ? 1.4142 : 1.0) + $extra + $this->cellPenalty($nx, $target, $nz)];
		}
		foreach([1, -1] as $dy){
			$ny = $y + $dy;
			$vertical = ($this->canSwimAt($x, $y, $z) && ($this->canSwimAt($x, $ny, $z) || $dy > 0)) || $this->canClimbAt($x, $dy > 0 ? $y : $ny, $z);
			if($vertical && $this->isPassable($x, $ny, $z) && $this->canStand($x, $ny, $z)){
				$result[] = [[$x, $ny, $z], 1.0 + $this->cellPenalty($x, $ny, $z)];
			}
		}
		return $result;
	}

	protected function nodePoint(array $node) : Vector3{
		return new Vector3($node[0] + 0.5, $this->standHeight($node[0], $node[1], $node[2]), $node[2] + 0.5);
	}

	protected function steer(Vector3 $point, bool $final) : void{
		$location = $this->entity->getLocation();
		$dx = $point->x - $location->x;
		$dy = $point->y - $location->y;
		$dz = $point->z - $location->z;
		$distance = sqrt($dx * $dx + $dz * $dz);
		$inWater = $this->entity->isInWater();
		$speed = $this->getBaseSpeed() * $this->speedMultiplier;
		if($inWater && $this->options->canSwim){
			$length = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
			if($length > 0.0001){
				$velocity = $this->applySway(new Vector3($dx / $length * $speed, $dy / $length * $speed, $dz / $length * $speed));
				$this->entity->setMotion($velocity);
			}
			$this->face($dx, $dy, $dz, true);
			return;
		}
		if($distance > 0.0001){
			$velocity = $this->applySway(new Vector3($dx / $distance * $speed, 0, $dz / $distance * $speed));
			$this->applyHorizontal($velocity->x, $velocity->z);
		}
		$this->face($dx, $dy, $dz, false);
		$motion = $this->entity->getMotion();
		if($this->climbing()){
			if($dy > 0.1 || $this->entity->isCollidedHorizontally){
				$this->entity->setMotion(new Vector3($motion->x, 0.2, $motion->z));
			}elseif($motion->y < -0.15){
				$this->entity->setMotion(new Vector3($motion->x, -0.15, $motion->z));
			}
			return;
		}
		if($inWater){
			if($this->options->canFloat || $dy > 0.1){
				$this->entity->setMotion(new Vector3($motion->x, max($motion->y, 0.08), $motion->z));
			}
			return;
		}
		if(!$this->isHopping() && $this->options->canJump && $this->entity->isOnGround()){
			$step = max(0.0, $this->entity->getStepHeight());
			if(($dy > $step + 0.05 && $distance < 1.5) || ($this->entity->isCollidedHorizontally && $dy > -0.5)){
				$this->entity->jump();
			}
		}
	}

	protected function climbing() : bool{
		$location = $this->entity->getLocation();
		return $this->canClimbAt((int) floor($location->x), (int) floor($location->y), (int) floor($location->z));
	}

	protected function idle() : void{
		if(!$this->entity->isInWater()){
			return;
		}
		$motion = $this->entity->getMotion();
		if($this->options->canFloat || (!$this->options->canSink && $this->options->canSwim)){
			$this->entity->setMotion(new Vector3($motion->x, max($motion->y, $this->options->canFloat ? 0.04 : 0.0), $motion->z));
		}
	}
}
