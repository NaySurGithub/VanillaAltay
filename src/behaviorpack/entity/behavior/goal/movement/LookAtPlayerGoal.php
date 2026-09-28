<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\goal\movement;

use pocketmine\entity\Entity;
use pocketmine\player\Player;

/**
 * Looks at a nearby player for a while.
 */
final class LookAtPlayerGoal extends LookAtEntityGoal{

	protected function acceptsEntity(Entity $entity) : bool{
		return $entity instanceof Player && parent::acceptsEntity($entity);
	}
}
