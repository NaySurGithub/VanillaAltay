<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\combat;

use pocketmine\entity\Entity;

/**
 * minecraft:behavior.owner_hurt_by_target: targets the entity that last
 * attacked the owner.
 */
class OwnerHurtByTargetGoal extends OwnerHurtTargetGoal{

	protected function record(Entity $owner) : ?array{
		return OwnerCombatTracker::getLastAttacker($owner->getId());
	}
}
