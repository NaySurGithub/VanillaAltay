<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\registry;

use behaviorpack\entity\behavior\state\AmbientSoundSystem;
use behaviorpack\entity\behavior\state\BandwidthOptimizationSystem;
use behaviorpack\entity\behavior\state\CollidableSystem;
use behaviorpack\entity\behavior\state\CustomHitTestSystem;
use behaviorpack\entity\behavior\state\ExperienceRewardSystem;
use behaviorpack\entity\behavior\state\HiddenWhenInvisibleSystem;
use behaviorpack\entity\behavior\state\InsomniaSystem;
use behaviorpack\entity\behavior\state\InstantDespawnSystem;
use behaviorpack\entity\behavior\state\MovementTrackingSystem;
use behaviorpack\entity\behavior\state\PersistentSystem;
use behaviorpack\entity\behavior\state\PlayerAttributeSystem;
use behaviorpack\entity\behavior\state\PushabilitySystem;
use behaviorpack\entity\behavior\state\RaidOmenSystem;
use behaviorpack\entity\behavior\state\RaidTriggerSystem;
use behaviorpack\entity\behavior\state\SpawnEntitySystem;
use behaviorpack\entity\behavior\state\TickWorldSystem;
use behaviorpack\entity\behavior\state\ValueStateSystem;

/**
 * Systems of the state components: variants, despawn, collision, sounds,
 * world ticking, spawning, attributes and raids.
 */
final class StateRegistry{

	public const SYSTEMS = [
		"minecraft:variant" => ValueStateSystem::class,
		"minecraft:mark_variant" => ValueStateSystem::class,
		"minecraft:skin_id" => ValueStateSystem::class,
		"minecraft:experience_reward" => ExperienceRewardSystem::class,
		"minecraft:persistent" => PersistentSystem::class,
		"minecraft:instant_despawn" => InstantDespawnSystem::class,
		"minecraft:is_collidable" => CollidableSystem::class,
		"minecraft:pushable_by_block" => PushabilitySystem::class,
		"minecraft:pushable_by_entity" => PushabilitySystem::class,
		"minecraft:is_hidden_when_invisible" => HiddenWhenInvisibleSystem::class,
		"minecraft:conditional_bandwidth_optimization" => BandwidthOptimizationSystem::class,
		"minecraft:custom_hit_test" => CustomHitTestSystem::class,
		"minecraft:ambient_sound_interval" => AmbientSoundSystem::class,
		"minecraft:tick_world" => TickWorldSystem::class,
		"minecraft:spawn_entity" => SpawnEntitySystem::class,
		"minecraft:game_event_movement_tracking" => MovementTrackingSystem::class,
		"minecraft:player.exhaustion" => PlayerAttributeSystem::class,
		"minecraft:player.experience" => PlayerAttributeSystem::class,
		"minecraft:player.level" => PlayerAttributeSystem::class,
		"minecraft:player.saturation" => PlayerAttributeSystem::class,
		"minecraft:exhaustion_values" => PlayerAttributeSystem::class,
		"minecraft:insomnia" => InsomniaSystem::class,
		"minecraft:raid_trigger" => RaidTriggerSystem::class,
		"minecraft:add_raid_omen" => RaidOmenSystem::class,
		"minecraft:gain_raid_omen" => RaidOmenSystem::class,
		"minecraft:clear_add_raid_omen" => RaidOmenSystem::class,
		"minecraft:clear_raid_omen_spell_effect" => RaidOmenSystem::class,
		"minecraft:remove_raid_trigger" => RaidOmenSystem::class,
		"minecraft:trigger_raid" => RaidOmenSystem::class
	];
}
