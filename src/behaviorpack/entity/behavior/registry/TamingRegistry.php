<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\registry;

use behaviorpack\entity\behavior\taming\AgeableSystem;
use behaviorpack\entity\behavior\taming\BalloonableSystem;
use behaviorpack\entity\behavior\taming\BreedableSystem;
use behaviorpack\entity\behavior\taming\FreeCameraControlledSystem;
use behaviorpack\entity\behavior\taming\HomeSystem;
use behaviorpack\entity\behavior\taming\InputGroundControlledSystem;
use behaviorpack\entity\behavior\taming\IsBabySystem;
use behaviorpack\entity\behavior\taming\IsChestedSystem;
use behaviorpack\entity\behavior\taming\IsSaddledSystem;
use behaviorpack\entity\behavior\taming\IsTamedSystem;
use behaviorpack\entity\behavior\taming\LeashableSystem;
use behaviorpack\entity\behavior\taming\OffspringSystem;
use behaviorpack\entity\behavior\taming\RideableSystem;
use behaviorpack\entity\behavior\taming\SittableSystem;
use behaviorpack\entity\behavior\taming\TameableSystem;

/**
 * Systems of the taming, riding, leashing and breeding components.
 */
final class TamingRegistry{

	public const SYSTEMS = [
		"minecraft:tameable" => TameableSystem::class,
		"minecraft:is_tamed" => IsTamedSystem::class,
		"minecraft:sittable" => SittableSystem::class,
		"minecraft:rideable" => RideableSystem::class,
		"minecraft:input_ground_controlled" => InputGroundControlledSystem::class,
		"minecraft:free_camera_controlled" => FreeCameraControlledSystem::class,
		"minecraft:leashable" => LeashableSystem::class,
		"minecraft:balloonable" => BalloonableSystem::class,
		"minecraft:ageable" => AgeableSystem::class,
		"minecraft:is_baby" => IsBabySystem::class,
		"minecraft:breedable" => BreedableSystem::class,
		"minecraft:home" => HomeSystem::class,
		"minecraft:is_saddled" => IsSaddledSystem::class,
		"minecraft:is_chested" => IsChestedSystem::class,
		"minecraft:offspring" => OffspringSystem::class
	];
}
