<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\combat;

use pocketmine\entity\Entity;

/**
 * minecraft:behavior.melee_box_attack: melee attack whose reach is the
 * attacker's bounding box grown horizontally by "horizontal_reach".
 */
class MeleeBoxAttackGoal extends MeleeAttackGoal{

	protected function isInReach(Entity $target) : bool{
		$reach = $this->float("horizontal_reach", 0.8);
		$box = $this->entity->getBoundingBox()->expandedCopy($reach, 0.0, $reach);
		return $box->intersectsWith($target->getBoundingBox());
	}
}
