<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\state;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use function is_array;
use function max;

/**
 * minecraft:conditional_bandwidth_optimization: how far and how often the
 * movement of the entity is sent, by default and under conditions.
 */
final class BandwidthOptimizationSystem extends EntitySystem{

	public function onAdd() : void{
		$this->entity->setData("bandwidth_optimization", $this->config);
	}

	public function onRemove() : void{
		$this->entity->setData("bandwidth_optimization", null);
	}

	/**
	 * Returns the active settings: the first conditional value whose filters
	 * pass, else the default values.
	 *
	 * @return array{max_optimized_distance: float, max_dropped_ticks: int, use_motion_prediction_hints: bool}
	 */
	public function getActiveValues() : array{
		$conditions = $this->config["conditional_values"] ?? [];
		if(is_array($conditions)){
			foreach($conditions as $condition){
				if(!is_array($condition)){
					continue;
				}
				$filters = $condition["conditional_values"] ?? $condition["filters"] ?? null;
				if(is_array($filters) && $this->entity->testFilter($filters)){
					return self::values($condition);
				}
			}
		}
		$default = $this->config["default_values"] ?? [];
		return self::values(is_array($default) ? $default : []);
	}

	/**
	 * @param array<mixed> $values
	 * @return array{max_optimized_distance: float, max_dropped_ticks: int, use_motion_prediction_hints: bool}
	 */
	private static function values(array $values) : array{
		return [
			"max_optimized_distance" => max(0.0, BehaviorEntity::toFloat($values["max_optimized_distance"] ?? null, 0.0)),
			"max_dropped_ticks" => max(0, (int) BehaviorEntity::toFloat($values["max_dropped_ticks"] ?? null, 0.0)),
			"use_motion_prediction_hints" => ($values["use_motion_prediction_hints"] ?? false) === true
		];
	}
}
