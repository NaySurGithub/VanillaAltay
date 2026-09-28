<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\taming;

use behaviorpack\entity\behavior\EntitySystem;

/**
 * A marker component shown to the clients as an actor flag while it is
 * active.
 */
abstract class FlagSystem extends EntitySystem{

	abstract protected function getFlag() : int;

	public function onAdd() : void{
		$this->entity->setFlag($this->getFlag(), true);
	}

	public function onRemove() : void{
		$this->entity->setFlag($this->getFlag(), false);
	}
}
