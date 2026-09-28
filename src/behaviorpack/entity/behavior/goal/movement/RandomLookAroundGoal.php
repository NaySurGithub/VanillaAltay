<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\movement;

use pocketmine\math\Vector3;
use pocketmine\utils\Utils;
use function cos;
use function deg2rad;
use function sin;

/**
 * Turns the head toward a random direction for a while.
 */
final class RandomLookAroundGoal extends MovementGoal{

	private int $remaining = 0;
	private float $yawOffset = 0.0;
	private ?Vector3 $target = null;

	public function getControls() : int{
		return self::LOOK;
	}

	public function canUse() : bool{
		return Utils::getRandomFloat() < 0.02;
	}

	public function canContinue() : bool{
		return $this->remaining > 0;
	}

	public function start() : void{
		$min = $this->num("min_angle_of_view_horizontal", -30.0);
		$max = $this->num("max_angle_of_view_horizontal", 30.0);
		$this->yawOffset = $this->randomFloat($min, $max);
		$this->remaining = isset($this->config["look_time"])
			? (int) ($this->range("look_time", 2.0) * 20)
			: 20 + (int) (Utils::getRandomFloat() * 20);
		$location = $this->entity->getLocation();
		$yaw = deg2rad($location->yaw + $this->yawOffset);
		$this->target = new Vector3(
			$location->x - sin($yaw) * 4,
			$location->y + $this->entity->getEyeHeight(),
			$location->z + cos($yaw) * 4
		);
	}

	public function stop() : void{
		$this->remaining = 0;
		$this->target = null;
	}

	public function tick(int $tickDiff) : void{
		$this->remaining -= $tickDiff;
		if($this->target !== null){
			$this->entity->lookAt($this->target);
		}
	}
}
