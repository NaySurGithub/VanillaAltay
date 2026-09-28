<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\state;

use behaviorpack\entity\behavior\EntitySystem;

/**
 * minecraft:is_collidable: other entities collide with this one.
 */
final class CollidableSystem extends EntitySystem{

	public function onAdd() : void{
		$this->entity->setCollidable(true);
	}
}
