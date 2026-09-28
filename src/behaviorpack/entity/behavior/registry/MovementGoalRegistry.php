<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\registry;

use behaviorpack\entity\behavior\goal\movement\CroakGoal;
use behaviorpack\entity\behavior\goal\movement\FloatGoal;
use behaviorpack\entity\behavior\goal\movement\GoHomeGoal;
use behaviorpack\entity\behavior\goal\movement\JumpToBlockGoal;
use behaviorpack\entity\behavior\goal\movement\LookAtEntityGoal;
use behaviorpack\entity\behavior\goal\movement\LookAtPlayerGoal;
use behaviorpack\entity\behavior\goal\movement\LookAtTargetGoal;
use behaviorpack\entity\behavior\goal\movement\MoveToLandGoal;
use behaviorpack\entity\behavior\goal\movement\MoveToWaterGoal;
use behaviorpack\entity\behavior\goal\movement\NapGoal;
use behaviorpack\entity\behavior\goal\movement\RandomHoverGoal;
use behaviorpack\entity\behavior\goal\movement\RandomLookAroundGoal;
use behaviorpack\entity\behavior\goal\movement\RandomStrollGoal;
use behaviorpack\entity\behavior\goal\movement\RandomSwimGoal;
use behaviorpack\entity\behavior\goal\movement\SwimIdleGoal;
use behaviorpack\entity\behavior\goal\movement\SwimWanderGoal;

final class MovementGoalRegistry{

	public const GOALS = [
		"minecraft:behavior.float" => FloatGoal::class,
		"minecraft:behavior.random_stroll" => RandomStrollGoal::class,
		"minecraft:behavior.look_at_player" => LookAtPlayerGoal::class,
		"minecraft:behavior.look_at_target" => LookAtTargetGoal::class,
		"minecraft:behavior.look_at_entity" => LookAtEntityGoal::class,
		"minecraft:behavior.random_look_around" => RandomLookAroundGoal::class,
		"minecraft:behavior.random_hover" => RandomHoverGoal::class,
		"minecraft:behavior.random_swim" => RandomSwimGoal::class,
		"minecraft:behavior.swim_idle" => SwimIdleGoal::class,
		"minecraft:behavior.swim_wander" => SwimWanderGoal::class,
		"minecraft:behavior.move_to_land" => MoveToLandGoal::class,
		"minecraft:behavior.move_to_water" => MoveToWaterGoal::class,
		"minecraft:behavior.jump_to_block" => JumpToBlockGoal::class,
		"minecraft:behavior.go_home" => GoHomeGoal::class,
		"minecraft:behavior.nap" => NapGoal::class,
		"minecraft:behavior.croak" => CroakGoal::class
	];
}
