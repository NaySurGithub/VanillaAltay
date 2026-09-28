<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\movement;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;

/**
 * "minecraft:underwater_movement": the speed of the entity in water, read by
 * the navigators from the "underwater_speed" data.
 */
final class UnderwaterMovementSystem extends EntitySystem{

	public function onAdd() : void{
		$this->entity->setData("underwater_speed", BehaviorEntity::rangeValue($this->config["value"] ?? null, 0.02));
	}

	public function onRemove() : void{
		$this->entity->setData("underwater_speed", null);
	}
}
