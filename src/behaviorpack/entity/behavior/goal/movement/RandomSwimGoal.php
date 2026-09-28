<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\movement;

use pocketmine\math\Vector3;

/**
 * Swims to a random water position from time to time.
 */
final class RandomSwimGoal extends RandomStrollGoal{

	public function canUse() : bool{
		return $this->entity->isInWater() && parent::canUse();
	}

	protected function findDestination() : ?Vector3{
		return $this->randomWaterPosition($this->int("xz_dist", 10), $this->int("y_dist", 7), $this->bool("avoid_surface", true));
	}
}
