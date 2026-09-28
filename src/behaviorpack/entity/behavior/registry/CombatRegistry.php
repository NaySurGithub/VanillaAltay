<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\registry;

use behaviorpack\entity\behavior\combat\AngrySystem;
use behaviorpack\entity\behavior\combat\DamageSensorSystem;
use behaviorpack\entity\behavior\combat\DashActionSystem;
use behaviorpack\entity\behavior\combat\EnvironmentSensorSystem;
use behaviorpack\entity\behavior\combat\ExplodeSystem;
use behaviorpack\entity\behavior\combat\FollowRangeSystem;
use behaviorpack\entity\behavior\combat\HurtOnConditionSystem;
use behaviorpack\entity\behavior\combat\OnTargetAcquiredSystem;
use behaviorpack\entity\behavior\combat\OnTargetEscapeSystem;
use behaviorpack\entity\behavior\combat\SpellEffectsSystem;
use behaviorpack\entity\behavior\combat\TargetNearbySensorSystem;
use behaviorpack\entity\behavior\combat\TimerSystem;

final class CombatRegistry{

	public const SYSTEMS = [
		"minecraft:damage_sensor" => DamageSensorSystem::class,
		"minecraft:hurt_on_condition" => HurtOnConditionSystem::class,
		"minecraft:follow_range" => FollowRangeSystem::class,
		"minecraft:angry" => AngrySystem::class,
		"minecraft:on_target_acquired" => OnTargetAcquiredSystem::class,
		"minecraft:on_target_escape" => OnTargetEscapeSystem::class,
		"minecraft:target_nearby_sensor" => TargetNearbySensorSystem::class,
		"minecraft:environment_sensor" => EnvironmentSensorSystem::class,
		"minecraft:timer" => TimerSystem::class,
		"minecraft:explode" => ExplodeSystem::class,
		"minecraft:spell_effects" => SpellEffectsSystem::class,
		"minecraft:dash_action" => DashActionSystem::class
	];
}
