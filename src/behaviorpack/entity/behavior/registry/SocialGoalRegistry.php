<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\registry;

use behaviorpack\entity\behavior\goal\social\AvoidMobTypeGoal;
use behaviorpack\entity\behavior\goal\social\BreedGoal;
use behaviorpack\entity\behavior\goal\social\FollowMobGoal;
use behaviorpack\entity\behavior\goal\social\FollowOwnerGoal;
use behaviorpack\entity\behavior\goal\social\FollowParentGoal;
use behaviorpack\entity\behavior\goal\social\LookAtTradingPlayerGoal;
use behaviorpack\entity\behavior\goal\social\PlayerRideTamedGoal;
use behaviorpack\entity\behavior\goal\social\StayWhileSittingGoal;
use behaviorpack\entity\behavior\goal\social\TemptGoal;
use behaviorpack\entity\behavior\goal\social\TradeWithPlayerGoal;

final class SocialGoalRegistry{

	public const GOALS = [
		"minecraft:behavior.follow_owner" => FollowOwnerGoal::class,
		"minecraft:behavior.stay_while_sitting" => StayWhileSittingGoal::class,
		"minecraft:behavior.player_ride_tamed" => PlayerRideTamedGoal::class,
		"minecraft:behavior.tempt" => TemptGoal::class,
		"minecraft:behavior.breed" => BreedGoal::class,
		"minecraft:behavior.follow_parent" => FollowParentGoal::class,
		"minecraft:behavior.avoid_mob_type" => AvoidMobTypeGoal::class,
		"minecraft:behavior.trade_with_player" => TradeWithPlayerGoal::class,
		"minecraft:behavior.look_at_trading_player" => LookAtTradingPlayerGoal::class,
		"minecraft:behavior.follow_mob" => FollowMobGoal::class
	];
}
