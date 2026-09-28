<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\state;

use behaviorpack\entity\behavior\EntitySystem;
use function is_bool;

/**
 * minecraft:pushable_by_block and minecraft:pushable_by_entity: whether
 * pistons and other entities push this entity.
 */
final class PushabilitySystem extends EntitySystem{

	public function isPushable() : bool{
		$value = $this->config["value"] ?? true;
		return !is_bool($value) || $value;
	}

	public function onAdd() : void{
		$this->apply($this->isPushable());
	}

	public function onRemove() : void{
		$this->apply(true);
	}

	private function apply(bool $pushable) : void{
		if($this->component === "minecraft:pushable_by_block"){
			$this->entity->setPushable($this->entity->isPushableByEntity(), $pushable);
			return;
		}
		$this->entity->setPushable($pushable, $this->entity->isPushableByBlock());
	}
}
