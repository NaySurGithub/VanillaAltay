<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\state;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use function is_array;
use function max;
use function min;
use function substr;

/**
 * minecraft:player.exhaustion, player.experience, player.level,
 * player.saturation and exhaustion_values: the hunger and experience
 * attributes of the entity, kept in the data store under "attributes".
 */
final class PlayerAttributeSystem extends EntitySystem{

	private const DEFAULTS = [
		"minecraft:player.exhaustion" => [0.0, 20.0],
		"minecraft:player.experience" => [0.0, 1.0],
		"minecraft:player.level" => [0.0, 24791.0],
		"minecraft:player.saturation" => [5.0, 20.0]
	];

	private const EXHAUSTION_DEFAULTS = [
		"attack" => 0.1,
		"damage" => 0.1,
		"heal" => 6.0,
		"jump" => 0.05,
		"lunge" => 0.05,
		"mine" => 0.005,
		"sprint" => 0.1,
		"sprint_jump" => 0.2,
		"swim" => 0.01,
		"walk" => 0.0
	];

	private function name() : string{
		return substr($this->component, 17);
	}

	public function onAdd() : void{
		if($this->component === "minecraft:exhaustion_values"){
			$values = [];
			foreach(self::EXHAUSTION_DEFAULTS as $key => $default){
				$values[$key] = BehaviorEntity::toFloat($this->config[$key] ?? null, $default);
			}
			$this->entity->setData("exhaustion_values", $values);
			return;
		}
		$defaults = self::DEFAULTS[$this->component] ?? [0.0, 1.0];
		$attributes = self::attributes($this->entity);
		$max = BehaviorEntity::toFloat($this->config["max"] ?? null, $defaults[1]);
		$current = $attributes[$this->name()]["value"] ?? BehaviorEntity::toFloat($this->config["value"] ?? null, $defaults[0]);
		$attributes[$this->name()] = ["value" => max(0.0, min($max, (float) $current)), "max" => $max];
		$this->entity->setData("attributes", $attributes);
	}

	public function onRemove() : void{
		if($this->component === "minecraft:exhaustion_values"){
			$this->entity->setData("exhaustion_values", null);
			return;
		}
		$attributes = self::attributes($this->entity);
		unset($attributes[$this->name()]);
		$this->entity->setData("attributes", $attributes === [] ? null : $attributes);
	}

	/**
	 * @return array<string, array{value: float, max: float}>
	 */
	public static function attributes(BehaviorEntity $entity) : array{
		$attributes = $entity->getData("attributes", []);
		return is_array($attributes) ? $attributes : [];
	}

	/**
	 * Returns an attribute by short name ("exhaustion", "experience",
	 * "level", "saturation").
	 */
	public static function getAttribute(BehaviorEntity $entity, string $name) : ?float{
		$value = self::attributes($entity)[$name]["value"] ?? null;
		return $value === null ? null : (float) $value;
	}

	public static function setAttribute(BehaviorEntity $entity, string $name, float $value) : void{
		$attributes = self::attributes($entity);
		if(!isset($attributes[$name])){
			return;
		}
		$attributes[$name]["value"] = max(0.0, min((float) $attributes[$name]["max"], $value));
		$entity->setData("attributes", $attributes);
	}

	/**
	 * Returns the exhaustion an action adds ("attack", "jump", "mine"...).
	 */
	public static function getExhaustion(BehaviorEntity $entity, string $action) : float{
		$values = $entity->getData("exhaustion_values");
		if(is_array($values) && isset($values[$action])){
			return (float) $values[$action];
		}
		return self::EXHAUSTION_DEFAULTS[$action] ?? 0.0;
	}

	/**
	 * Adds the exhaustion of an action; every 4 points of exhaustion take one
	 * point of saturation.
	 */
	public static function exhaust(BehaviorEntity $entity, string $action) : void{
		$exhaustion = self::getAttribute($entity, "exhaustion");
		if($exhaustion === null){
			return;
		}
		$exhaustion += self::getExhaustion($entity, $action);
		while($exhaustion >= 4.0){
			$exhaustion -= 4.0;
			$saturation = self::getAttribute($entity, "saturation");
			if($saturation !== null){
				self::setAttribute($entity, "saturation", $saturation - 1.0);
			}
		}
		self::setAttribute($entity, "exhaustion", $exhaustion);
	}
}
