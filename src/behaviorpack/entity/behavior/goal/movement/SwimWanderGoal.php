<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\movement;

use pocketmine\math\Vector3;
use pocketmine\utils\Utils;
use function cos;
use function deg2rad;
use function sin;

/**
 * Swims forward in water, turning away when the path ahead is blocked.
 */
final class SwimWanderGoal extends MovementGoal{

	private int $remaining = 0;

	public function canUse() : bool{
		return $this->entity->isInWater() && Utils::getRandomFloat() < $this->num("interval", 0.00833);
	}

	public function canContinue() : bool{
		return $this->remaining > 0 && $this->entity->isInWater();
	}

	public function start() : void{
		$this->remaining = (int) ($this->num("wander_time", 5.0) * 20);
		$this->pickDirection((float) $this->entity->getLocation()->yaw);
	}

	private function pickDirection(float $yaw) : void{
		$lookAhead = $this->num("look_ahead", 2.0);
		$location = $this->entity->getLocation();
		for($i = 0; $i < 8; ++$i){
			$angle = deg2rad($yaw + $this->randomFloat(-45.0, 45.0) + ($i * 45.0));
			$dx = -sin($angle);
			$dz = cos($angle);
			$ahead = $location->add($dx * $lookAhead, 0, $dz * $lookAhead);
			if(!$this->isWaterAt($ahead->x, $ahead->y, $ahead->z)){
				continue;
			}
			$far = $location->add($dx * 16, $this->randomFloat(-1.0, 1.0), $dz * 16);
			$this->entity->getNavigator()->moveTo($far, $this->speed(), 1.0);
			return;
		}
		$this->entity->getNavigator()->stop();
	}

	public function stop() : void{
		$this->remaining = 0;
		$this->entity->getNavigator()->stop();
	}

	public function tick(int $tickDiff) : void{
		$this->remaining -= $tickDiff;
		$location = $this->entity->getLocation();
		$lookAhead = $this->num("look_ahead", 2.0);
		$yaw = deg2rad($location->yaw);
		$ahead = new Vector3($location->x - sin($yaw) * $lookAhead, $location->y, $location->z + cos($yaw) * $lookAhead);
		if($this->entity->getNavigator()->isDone() || !$this->isWaterAt($ahead->x, $ahead->y, $ahead->z)){
			$this->pickDirection((float) $location->yaw + 180.0);
		}
	}
}
