<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\combat;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use function array_is_list;
use function is_array;
use function is_bool;
use function is_string;
use function max;
use function strtolower;

/**
 * minecraft:damage_sensor: runs events on damage and changes or cancels the
 * damage, by cause and filters. The first matching trigger decides.
 */
final class DamageSensorSystem extends EntitySystem{

	/**
	 * @return list<array<mixed>>
	 */
	private function triggers() : array{
		$triggers = $this->config["triggers"] ?? null;
		if(!is_array($triggers)){
			return [];
		}
		if(!array_is_list($triggers)){
			return [$triggers];
		}
		$result = [];
		foreach($triggers as $trigger){
			if(is_array($trigger)){
				$result[] = $trigger;
			}
		}
		return $result;
	}

	public function beforeDamage(EntityDamageEvent $source) : void{
		$cause = $source->getCause();
		$context = ["damage_cause" => DamageCause::name($cause)];
		if($source instanceof EntityDamageByEntityEvent && ($damager = $source->getDamager()) !== null){
			$context["other"] = $damager;
			$context["damager"] = $damager;
			if($source instanceof EntityDamageByChildEntityEvent && ($child = $source->getChild()) !== null){
				$context["projectile"] = $child;
			}
		}
		foreach($this->triggers() as $trigger){
			if(!DamageCause::matches($trigger["cause"] ?? null, $cause)){
				continue;
			}
			$onDamage = $trigger["on_damage"] ?? null;
			if(is_array($onDamage)){
				if(is_array($onDamage["filters"] ?? null) && !$this->entity->testFilter($onDamage["filters"], $context)){
					continue;
				}
				if(is_string($onDamage["event"] ?? null)){
					$this->entity->runTrigger(["event" => $onDamage["event"], "target" => $onDamage["target"] ?? "self"], $context);
				}
			}
			$this->applyDamageRules($trigger, $source);
			return;
		}
	}

	/**
	 * @param array<mixed> $trigger
	 */
	private function applyDamageRules(array $trigger, EntityDamageEvent $source) : void{
		$deals = $trigger["deals_damage"] ?? true;
		if(is_string($deals)){
			$deals = strtolower($deals);
			if($deals === "no" || $deals === "no_but_side_effects_apply"){
				$source->cancel();
				return;
			}
		}elseif(is_bool($deals) && !$deals){
			$source->cancel();
			return;
		}
		$multiplier = BehaviorEntity::toFloat($trigger["damage_multiplier"] ?? null, 1.0);
		$modifier = BehaviorEntity::toFloat($trigger["damage_modifier"] ?? null, 0.0);
		if($multiplier !== 1.0 || $modifier !== 0.0){
			$source->setBaseDamage(max(0.0, $source->getBaseDamage() * $multiplier + $modifier));
		}
	}
}
