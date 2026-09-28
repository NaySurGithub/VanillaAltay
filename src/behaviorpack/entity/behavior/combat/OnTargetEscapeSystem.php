<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\combat;

use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\entity\Entity;

/**
 * minecraft:on_target_escape: runs its event when the entity loses its target.
 */
final class OnTargetEscapeSystem extends EntitySystem{

	public function onTargetChanged(?Entity $previous, ?Entity $target) : void{
		if($target !== null || $previous === null){
			return;
		}
		$this->entity->runTrigger($this->config, ["other" => $previous]);
	}
}
