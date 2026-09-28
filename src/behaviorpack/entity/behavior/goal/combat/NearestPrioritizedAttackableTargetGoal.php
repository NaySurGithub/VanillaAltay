<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\combat;

use behaviorpack\entity\BehaviorEntity;
use pocketmine\entity\Entity;

/**
 * minecraft:behavior.nearest_prioritized_attackable_target: like the
 * nearest attackable target, but entity types with a lower "priority" win
 * over closer ones.
 */
class NearestPrioritizedAttackableTargetGoal extends NearestAttackableTargetGoal{

	protected function score(Entity $candidate, array $type, float $distanceSquared) : float{
		return BehaviorEntity::toFloat($type["priority"] ?? null, 0.0) * 1000000.0 + $distanceSquared;
	}
}
