<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\combat;

use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\entity\Entity;

/**
 * minecraft:on_target_acquired: runs its event when the entity gets a target.
 */
final class OnTargetAcquiredSystem extends EntitySystem{

	public function onTargetChanged(?Entity $previous, ?Entity $target) : void{
		if($target === null){
			return;
		}
		$this->entity->runTrigger($this->config, ["other" => $target, "target" => $target]);
	}
}
