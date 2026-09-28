<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\combat;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use function array_intersect;
use function count;
use function is_array;
use function is_int;
use function is_string;
use function mt_rand;
use function strtolower;

/**
 * minecraft:angry: the entity is angry at a target for a duration, can make
 * nearby entities angry too, and runs the calm event when it cools down.
 * The remaining ticks are stored as "angry_until" (-1 for no end).
 */
final class AngrySystem extends EntitySystem{

	public function onAdd() : void{
		$this->entity->setFlag(EntityMetadataFlags::ANGRY, true);
		if($this->entity->getData("angry_until") === null){
			$this->startAnger($this->entity->getTargetEntity() ?? $this->entity->getLastHurtBy(), true);
		}
	}

	public function onRemove() : void{
		$this->entity->setFlag(EntityMetadataFlags::ANGRY, false);
		$this->entity->setData("angry_until", null);
	}

	public function afterDamage(EntityDamageEvent $source) : void{
		if($source instanceof EntityDamageByEntityEvent && ($damager = $source->getDamager()) !== null){
			$this->makeAngry($damager, true);
		}
	}

	public function onTargetChanged(?Entity $previous, ?Entity $target) : void{
		if($target !== null){
			$this->makeAngry($target, false);
		}
	}

	/**
	 * Makes the entity angry at a target, when the filters accept it.
	 */
	public function makeAngry(Entity $target, bool $broadcast) : void{
		if(is_array($this->config["filters"] ?? null) && !$this->entity->testFilter($this->config["filters"], ["other" => $target, "target" => $target])){
			return;
		}
		$this->startAnger($target, $broadcast);
	}

	private function startAnger(?Entity $target, bool $broadcast) : void{
		$this->entity->setData("angry_until", $this->computeEnd());
		$this->entity->setFlag(EntityMetadataFlags::ANGRY, true);
		if($target !== null && $target !== $this->entity && !($target instanceof Player && ($target->isCreative() || $target->isSpectator()))){
			$this->entity->setTargetEntity($target);
			if($broadcast && ($this->config["broadcast_anger"] ?? false) === true){
				$this->broadcast($target);
			}
		}
	}

	private function computeEnd() : int{
		$duration = BehaviorEntity::toFloat($this->config["duration"] ?? null, 25.0);
		if($duration < 0){
			return -1;
		}
		$delta = BehaviorEntity::toFloat($this->config["duration_delta"] ?? null, 0.0);
		$seconds = $duration + ($delta > 0 ? mt_rand((int) (-$delta * 20), (int) ($delta * 20)) / 20 : 0.0);
		return (int) ($seconds * 20);
	}

	private function broadcast(Entity $target) : void{
		$range = BehaviorEntity::toFloat($this->config["broadcast_range"] ?? null, 20.0);
		$families = [];
		foreach(is_array($this->config["broadcast_targets"] ?? null) ? $this->config["broadcast_targets"] : [] as $family){
			if(is_string($family)){
				$families[] = strtolower($family);
			}
		}
		$filters = is_array($this->config["broadcast_filters"] ?? null) ? $this->config["broadcast_filters"] : null;
		$box = $this->entity->getBoundingBox()->expandedCopy($range, $range, $range);
		foreach($this->entity->getWorld()->getNearbyEntities($box, $this->entity) as $other){
			if(!$other instanceof BehaviorEntity || !$other->isAlive() || $other === $target){
				continue;
			}
			if(count($families) > 0){
				if(count(array_intersect($families, $other->getFamilies())) === 0){
					continue;
				}
			}elseif($other->getIdentifier() !== $this->entity->getIdentifier()){
				continue;
			}
			if($filters !== null && !$this->entity->testFilter($filters, ["other" => $other, "target" => $target])){
				continue;
			}
			$system = $other->getSystem($this->component);
			if($system instanceof self){
				$system->makeAngry($target, false);
			}else{
				$other->setTargetEntity($target);
			}
		}
	}

	public function tick(int $tickDiff) : void{
		$until = $this->entity->getData("angry_until");
		if(!is_int($until) || $until < 0){
			return;
		}
		$until -= $tickDiff;
		if($until > 0){
			$this->entity->setData("angry_until", $until);
			return;
		}
		$this->entity->setData("angry_until", null);
		$this->entity->setFlag(EntityMetadataFlags::ANGRY, false);
		$this->entity->setTargetEntity(null);
		$calm = $this->config["calm_event"] ?? null;
		if($calm !== null){
			$this->entity->runTrigger($calm);
		}
	}
}
