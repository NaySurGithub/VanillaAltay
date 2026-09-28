<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\movement;

/**
 * Moves from land to a nearby water block.
 */
final class MoveToWaterGoal extends MoveToLandGoal{

	protected function isInRightMedium() : bool{
		return $this->entity->isInWater();
	}

	protected function isValidTarget(int $x, int $y, int $z) : bool{
		return $this->isWaterAt($x, $y, $z);
	}
}
