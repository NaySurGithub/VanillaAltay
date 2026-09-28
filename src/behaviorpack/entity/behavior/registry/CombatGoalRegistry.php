<?php

declare(strict_types=1);

namespace behaviorpack\entity\behavior\registry;

use behaviorpack\entity\behavior\goal\combat\DelayedAttackGoal;
use behaviorpack\entity\behavior\goal\combat\HurtByTargetGoal;
use behaviorpack\entity\behavior\goal\combat\KnockbackRoarGoal;
use behaviorpack\entity\behavior\goal\combat\MeleeAttackGoal;
use behaviorpack\entity\behavior\goal\combat\MeleeBoxAttackGoal;
use behaviorpack\entity\behavior\goal\combat\NearestAttackableTargetGoal;
use behaviorpack\entity\behavior\goal\combat\NearestPrioritizedAttackableTargetGoal;
use behaviorpack\entity\behavior\goal\combat\OwnerHurtByTargetGoal;
use behaviorpack\entity\behavior\goal\combat\OwnerHurtTargetGoal;
use behaviorpack\entity\behavior\goal\combat\PanicGoal;
use behaviorpack\entity\behavior\goal\combat\RangedAttackGoal;

final class CombatGoalRegistry{

	public const GOALS = [
		"minecraft:behavior.melee_attack" => MeleeAttackGoal::class,
		"minecraft:behavior.melee_box_attack" => MeleeBoxAttackGoal::class,
		"minecraft:behavior.delayed_attack" => DelayedAttackGoal::class,
		"minecraft:behavior.nearest_attackable_target" => NearestAttackableTargetGoal::class,
		"minecraft:behavior.nearest_prioritized_attackable_target" => NearestPrioritizedAttackableTargetGoal::class,
		"minecraft:behavior.hurt_by_target" => HurtByTargetGoal::class,
		"minecraft:behavior.owner_hurt_target" => OwnerHurtTargetGoal::class,
		"minecraft:behavior.owner_hurt_by_target" => OwnerHurtByTargetGoal::class,
		"minecraft:behavior.ranged_attack" => RangedAttackGoal::class,
		"minecraft:behavior.knockback_roar" => KnockbackRoarGoal::class,
		"minecraft:behavior.panic" => PanicGoal::class
	];
}
