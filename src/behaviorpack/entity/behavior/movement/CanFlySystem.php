<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\movement;

use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;

/**
 * "minecraft:can_fly": marks the entity as able to fly.
 */
final class CanFlySystem extends EntitySystem{

	public function onAdd() : void{
		$this->entity->setFlag(EntityMetadataFlags::CAN_FLY, true);
		$this->entity->setData("can_fly", true);
	}

	public function onRemove() : void{
		$this->entity->setFlag(EntityMetadataFlags::CAN_FLY, false);
		$this->entity->setData("can_fly", null);
	}
}
