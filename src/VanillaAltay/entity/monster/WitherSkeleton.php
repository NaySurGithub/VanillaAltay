<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class WitherSkeleton extends ConfiguredMonster
{
	protected const NETWORK_ID = EntityIds::WITHER_SKELETON;

	protected const NAME = "Wither Skeleton";

	protected const HEIGHT = 2.4;

	protected const WIDTH = 0.7;

	protected const HEALTH = 20;

	protected const SPEED = 0.25;

	protected const DAMAGE = 8.0;

	protected function getAttackEffectFactory() : ?\Closure
	{
		return static fn() : EffectInstance => new EffectInstance(VanillaEffects::WITHER(), 200);
	}
}
