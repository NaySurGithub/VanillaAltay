<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\movement;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;

/**
 * "minecraft:flying_speed": the speed of the entity while flying, read by the
 * flying navigators from the "flying_speed" data.
 */
final class FlyingSpeedSystem extends EntitySystem{

	public function onAdd() : void{
		$this->entity->setData("flying_speed", BehaviorEntity::toFloat($this->config["value"] ?? null, 0.02));
	}

	public function onRemove() : void{
		$this->entity->setData("flying_speed", null);
	}
}
