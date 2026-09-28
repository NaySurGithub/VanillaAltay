<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\registry;

use behaviorpack\entity\behavior\movement\BlockClimberSystem;
use behaviorpack\entity\behavior\movement\BreathableSystem;
use behaviorpack\entity\behavior\movement\CanClimbSystem;
use behaviorpack\entity\behavior\movement\CanFlySystem;
use behaviorpack\entity\behavior\movement\FlyingSpeedSystem;
use behaviorpack\entity\behavior\movement\JumpStaticSystem;
use behaviorpack\entity\behavior\movement\MovementTypeSystem;
use behaviorpack\entity\behavior\movement\NavigationSystem;
use behaviorpack\entity\behavior\movement\UnderwaterMovementSystem;
use behaviorpack\entity\behavior\movement\VariableMaxAutoStepSystem;

/**
 * The movement, navigation and breathing components.
 */
final class MovementRegistry{

	public const SYSTEMS = [
		"minecraft:movement.basic" => MovementTypeSystem::class,
		"minecraft:movement.generic" => MovementTypeSystem::class,
		"minecraft:movement.hover" => MovementTypeSystem::class,
		"minecraft:movement.amphibious" => MovementTypeSystem::class,
		"minecraft:movement.sway" => MovementTypeSystem::class,
		"minecraft:movement.fly" => MovementTypeSystem::class,
		"minecraft:movement.jump" => MovementTypeSystem::class,
		"minecraft:movement.skip" => MovementTypeSystem::class,
		"minecraft:movement.glide" => MovementTypeSystem::class,
		"minecraft:navigation.walk" => NavigationSystem::class,
		"minecraft:navigation.generic" => NavigationSystem::class,
		"minecraft:navigation.hover" => NavigationSystem::class,
		"minecraft:navigation.climb" => NavigationSystem::class,
		"minecraft:navigation.fly" => NavigationSystem::class,
		"minecraft:navigation.swim" => NavigationSystem::class,
		"minecraft:navigation.float" => NavigationSystem::class,
		"minecraft:jump.static" => JumpStaticSystem::class,
		"minecraft:can_fly" => CanFlySystem::class,
		"minecraft:can_climb" => CanClimbSystem::class,
		"minecraft:block_climber" => BlockClimberSystem::class,
		"minecraft:flying_speed" => FlyingSpeedSystem::class,
		"minecraft:underwater_movement" => UnderwaterMovementSystem::class,
		"minecraft:breathable" => BreathableSystem::class,
		"minecraft:variable_max_auto_step" => VariableMaxAutoStepSystem::class
	];
}
