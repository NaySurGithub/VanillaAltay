<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\state;

use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;

/**
 * minecraft:is_hidden_when_invisible: hostile mobs do not see the entity
 * while it is invisible.
 */
final class HiddenWhenInvisibleSystem extends EntitySystem{

	public function onAdd() : void{
		$this->entity->setFlag(EntityMetadataFlags::HIDDEN_WHEN_INVISIBLE, true);
	}

	public function onRemove() : void{
		$this->entity->setFlag(EntityMetadataFlags::HIDDEN_WHEN_INVISIBLE, false);
	}

	public function isHidden() : bool{
		return $this->entity->isInvisible();
	}
}
