<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\movement;

use behaviorpack\entity\BehaviorEntity;
use behaviorpack\entity\behavior\EntitySystem;
use pocketmine\entity\Living;

/**
 * "minecraft:jump.static": sets the initial upward velocity of the jumps.
 */
final class JumpStaticSystem extends EntitySystem{

	public const DEFAULT_POWER = 0.42;

	public function onAdd() : void{
		$power = BehaviorEntity::toFloat($this->config["jump_power"] ?? null, self::DEFAULT_POWER);
		$this->entity->setData("jump_power", $power);
		self::setJumpVelocity($this->entity, $power);
	}

	public function onRemove() : void{
		$this->entity->setData("jump_power", null);
		self::setJumpVelocity($this->entity, self::DEFAULT_POWER);
	}

	public static function setJumpVelocity(Living $entity, float $velocity) : void{
		$setter = \Closure::bind(function(float $value) : void{
			$this->jumpVelocity = $value;
		}, $entity, Living::class);
		$setter($velocity);
	}
}
