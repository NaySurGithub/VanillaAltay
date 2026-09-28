<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\combat;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use function max;
use function min;

/**
 * minecraft:follow_range: the distance the entity tracks its target from,
 * stored in the data store as "follow_range".
 */
final class FollowRangeSystem extends EntitySystem{

	public const DEFAULT_RANGE = 16.0;

	public function onAdd() : void{
		$value = BehaviorEntity::toFloat($this->config["value"] ?? null, self::DEFAULT_RANGE);
		$max = BehaviorEntity::toFloat($this->config["max"] ?? null, $value);
		$this->entity->setData("follow_range", max(0.0, min($value, $max)));
	}

	public function onRemove() : void{
		$this->entity->setData("follow_range", null);
	}

	public static function of(BehaviorEntity $entity) : float{
		return BehaviorEntity::toFloat($entity->getData("follow_range"), self::DEFAULT_RANGE);
	}
}
