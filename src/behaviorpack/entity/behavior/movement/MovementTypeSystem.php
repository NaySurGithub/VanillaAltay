<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\movement;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\math\Vector3;
use function array_is_list;
use function count;
use function in_array;
use function is_array;
use function max;
use function strlen;
use function substr;

/**
 * "minecraft:movement.*": stores how the entity moves so that the
 * navigators apply it (mode, maximum turn per tick, sway, hop delay) and
 * removes the gravity of the hovering and flying movements.
 */
final class MovementTypeSystem extends EntitySystem{

	private const GRAVITY_FREE = ["hover", "fly"];

	private const KEYS = ["movement_mode", "movement_max_turn", "movement_sway_amplitude", "movement_sway_frequency", "movement_jump_delay"];

	public function getMode() : string{
		return substr($this->component, strlen("minecraft:movement."));
	}

	public function onAdd() : void{
		$mode = $this->getMode();
		$this->entity->setData("movement_mode", $mode);
		$this->entity->setData("movement_max_turn", max(0.0, BehaviorEntity::toFloat($this->config["max_turn"] ?? null, 30.0)));
		if($mode === "sway"){
			$this->entity->setData("movement_sway_amplitude", BehaviorEntity::toFloat($this->config["sway_amplitude"] ?? null, 0.05));
			$this->entity->setData("movement_sway_frequency", BehaviorEntity::toFloat($this->config["sway_frequency"] ?? null, 0.5));
		}else{
			$this->entity->setData("movement_sway_amplitude", null);
			$this->entity->setData("movement_sway_frequency", null);
		}
		if($mode === "jump" || $mode === "skip"){
			$this->entity->setData("movement_jump_delay", $this->jumpDelay());
		}else{
			$this->entity->setData("movement_jump_delay", null);
		}
		if(in_array($mode, self::GRAVITY_FREE, true)){
			$this->entity->setHasGravity(false);
		}
	}

	/**
	 * @return array{int, int}
	 */
	private function jumpDelay() : array{
		$delay = $this->config["jump_delay"] ?? null;
		$min = 0.0;
		$max = 0.0;
		if(is_array($delay) && array_is_list($delay) && count($delay) === 2){
			$min = BehaviorEntity::toFloat($delay[0], 0.0);
			$max = BehaviorEntity::toFloat($delay[1], $min);
		}elseif(is_array($delay)){
			$min = BehaviorEntity::toFloat($delay["range_min"] ?? $delay["min"] ?? null, 0.0);
			$max = BehaviorEntity::toFloat($delay["range_max"] ?? $delay["max"] ?? null, $min);
		}elseif($delay !== null){
			$min = $max = BehaviorEntity::toFloat($delay, 0.0);
		}
		return [(int) ($min * 20), (int) (max($min, $max) * 20)];
	}

	public function tick(int $tickDiff) : void{
		if($this->entity->hasGravity() && in_array($this->getMode(), self::GRAVITY_FREE, true)){
			$this->entity->setHasGravity(false);
		}
		if($this->getMode() !== "glide" || $this->entity->isOnGround()){
			return;
		}
		$motion = $this->entity->getMotion();
		if($motion->y < -0.1){
			$this->entity->setMotion(new Vector3($motion->x, -0.1, $motion->z));
		}
	}

	public function onRemove() : void{
		if($this->entity->getData("movement_mode") === $this->getMode()){
			foreach(self::KEYS as $key){
				$this->entity->setData($key, null);
			}
		}
		NavigationSystem::restoreGravity($this->entity, $this->component);
	}
}
