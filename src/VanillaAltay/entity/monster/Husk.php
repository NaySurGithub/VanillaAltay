<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Husk extends Zombie
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::HUSK;
	}

	public function getName() : string
	{
		return "Husk";
	}

	protected function burnsInDaylight() : bool
	{
		return false;
	}

	protected function getAttackEffectFactory() : ?\Closure
	{
		return static fn() : EffectInstance => new EffectInstance(VanillaEffects::HUNGER(), 140);
	}
}
