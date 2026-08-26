<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class CaveSpider extends Spider
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::CAVE_SPIDER;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(0.5, 0.7);
	}

	protected function getVanillaMaxHealth() : int
	{
		return 12;
	}

	public function getName() : string
	{
		return "Cave Spider";
	}

	protected function getAttackEffectFactory() : ?\Closure
	{
		return static fn() : EffectInstance => new EffectInstance(VanillaEffects::POISON(), 140);
	}
}
