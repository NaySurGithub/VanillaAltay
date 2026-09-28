<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\combat;

use behaviorpack\entity\behavior\EntitySystem;
use function array_is_list;
use function is_array;

/**
 * minecraft:environment_sensor: runs the event of each trigger every tick
 * its filters pass.
 */
final class EnvironmentSensorSystem extends EntitySystem{

	public function tick(int $tickDiff) : void{
		$triggers = $this->config["triggers"] ?? null;
		if(!is_array($triggers)){
			return;
		}
		if(!array_is_list($triggers)){
			$triggers = [$triggers];
		}
		foreach($triggers as $trigger){
			if(!is_array($trigger)){
				continue;
			}
			$context = [];
			$target = $this->entity->getTargetEntity();
			if($target !== null){
				$context["target"] = $target;
			}
			$this->entity->runTrigger($trigger, $context);
			if($this->entity->isClosed() || !$this->entity->isAlive() || $this->entity->getSystem($this->component) !== $this){
				return;
			}
		}
	}
}
