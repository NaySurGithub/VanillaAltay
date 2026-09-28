<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\movement;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use function in_array;
use function is_array;
use function strlen;
use function substr;

/**
 * "minecraft:navigation.*": installs the pathfinding navigator matching the
 * navigation type, configured with the component flags.
 */
final class NavigationSystem extends EntitySystem{

	private const AIRBORNE = ["hover", "fly", "float"];

	private const GRAVITY_FREE_COMPONENTS = [
		"minecraft:navigation.hover",
		"minecraft:navigation.fly",
		"minecraft:navigation.float",
		"minecraft:movement.hover",
		"minecraft:movement.fly"
	];

	private ?PathNavigator $navigator = null;

	public function getType() : string{
		return substr($this->component, strlen("minecraft:navigation."));
	}

	public function getNavigator() : ?PathNavigator{
		return $this->navigator;
	}

	public function onAdd() : void{
		$options = NavigationOptions::fromConfig($this->config);
		$type = $this->getType();
		if($type === "swim"){
			$options->canSwim = true;
		}
		if($this->navigator !== null && $this->entity->getNavigator() === $this->navigator){
			$this->navigator->setOptions($options);
		}else{
			$current = $this->entity->getNavigator();
			if($current instanceof PathNavigator){
				$current->release();
			}
			$this->navigator = match($type){
				"hover", "fly", "float" => new FlyNavigator($this->entity, $options),
				"swim" => new SwimNavigator($this->entity, $options),
				"climb" => new ClimbNavigator($this->entity, $options),
				default => new WalkNavigator($this->entity, $options)
			};
			$this->entity->setNavigator($this->navigator);
		}
		$this->entity->setData("navigation_type", $type);
		if(in_array($type, self::AIRBORNE, true)){
			$this->entity->setHasGravity(false);
		}
		$this->entity->setCanClimbWalls($type === "climb");
	}

	public function tick(int $tickDiff) : void{
		if($this->entity->hasGravity() && in_array($this->getType(), self::AIRBORNE, true)){
			$this->entity->setHasGravity(false);
		}
	}

	public function onRemove() : void{
		if($this->navigator !== null && $this->entity->getNavigator() === $this->navigator){
			$this->navigator->release();
			$this->entity->setNavigator(null);
		}
		$this->navigator = null;
		if($this->entity->getData("navigation_type") === $this->getType()){
			$this->entity->setData("navigation_type", null);
		}
		if($this->getType() === "climb"){
			$this->entity->setCanClimbWalls(false);
		}
		self::restoreGravity($this->entity, $this->component);
	}

	/**
	 * Restores the gravity of the physics component unless another active
	 * component keeps the entity in the air.
	 */
	public static function restoreGravity(BehaviorEntity $entity, string $removed) : void{
		foreach(self::GRAVITY_FREE_COMPONENTS as $component){
			if($component !== $removed && $entity->hasComponent($component)){
				$entity->setHasGravity(false);
				return;
			}
		}
		$physics = $entity->getComponent("minecraft:physics");
		$entity->setHasGravity(!is_array($physics) || ($physics["has_gravity"] ?? true) !== false);
	}
}
