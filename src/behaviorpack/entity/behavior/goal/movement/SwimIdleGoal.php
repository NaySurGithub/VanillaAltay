<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\movement;

use pocketmine\math\Vector3;
use pocketmine\utils\Utils;

/**
 * Stays still in water for a while.
 */
final class SwimIdleGoal extends MovementGoal{

	private int $remaining = 0;

	public function canUse() : bool{
		return $this->entity->isInWater() && Utils::getRandomFloat() < $this->num("success_rate", 0.1);
	}

	public function canContinue() : bool{
		return $this->remaining > 0 && $this->entity->isInWater();
	}

	public function start() : void{
		$this->remaining = (int) ($this->num("idle_time", 5.0) * 20);
		$this->entity->getNavigator()->stop();
	}

	public function stop() : void{
		$this->remaining = 0;
	}

	public function tick(int $tickDiff) : void{
		$this->remaining -= $tickDiff;
		$motion = $this->entity->getMotion();
		$this->entity->setMotion(new Vector3($motion->x * 0.8, $motion->y, $motion->z * 0.8));
	}
}
