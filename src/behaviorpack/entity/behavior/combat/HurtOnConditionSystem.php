<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\combat;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\event\entity\EntityDamageEvent;
use function array_is_list;
use function is_array;

/**
 * minecraft:hurt_on_condition: damages the entity while a condition holds.
 */
final class HurtOnConditionSystem extends EntitySystem{

	public function tick(int $tickDiff) : void{
		$conditions = $this->config["damage_conditions"] ?? null;
		if(!is_array($conditions)){
			return;
		}
		if(!array_is_list($conditions)){
			$conditions = [$conditions];
		}
		foreach($conditions as $condition){
			if(!is_array($condition)){
				continue;
			}
			if(is_array($condition["filters"] ?? null) && !$this->entity->testFilter($condition["filters"])){
				continue;
			}
			$damage = BehaviorEntity::toFloat($condition["damage_per_tick"] ?? null, 1.0);
			if($damage <= 0){
				continue;
			}
			$cause = DamageCause::fromName($condition["cause"] ?? null) ?? EntityDamageEvent::CAUSE_CUSTOM;
			$this->entity->attack(new EntityDamageEvent($this->entity, $cause, $damage * $tickDiff));
			if($this->entity->isClosed() || !$this->entity->isAlive()){
				return;
			}
		}
	}
}
