<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\state;

use behaviorpack\entity\behavior\EntitySystem;

/**
 * minecraft:persistent: the entity never despawns.
 */
final class PersistentSystem extends EntitySystem{

	public function onAdd() : void{
		$this->entity->setData("persistent", true);
	}

	public function onRemove() : void{
		$this->entity->setData("persistent", null);
	}
}
