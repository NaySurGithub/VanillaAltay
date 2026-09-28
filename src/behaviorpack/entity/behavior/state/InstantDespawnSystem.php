<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\state;

use behaviorpack\entity\behavior\EntitySystem;
use function is_array;
use function is_int;

/**
 * minecraft:instant_despawn: removes the entity as soon as the component is
 * active, and optionally the entities it spawned.
 */
final class InstantDespawnSystem extends EntitySystem{

	public function onAdd() : void{
		if(($this->config["remove_child_entities"] ?? false) === true){
			$children = $this->entity->getData("child_entities", []);
			if(is_array($children)){
				$worldManager = $this->entity->getWorld()->getServer()->getWorldManager();
				foreach($children as $id){
					if(!is_int($id)){
						continue;
					}
					$child = $worldManager->findEntity($id);
					if($child !== null && !$child->isClosed()){
						$child->flagForDespawn();
					}
				}
			}
			$this->entity->setData("child_entities", null);
		}
		$this->entity->flagForDespawn();
	}
}
