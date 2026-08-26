<?php

declare(strict_types=1);

namespace VanillaAltay\entity\projectile;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\math\RayTraceResult;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class WitherSkull extends MagicProjectile
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::WITHER_SKULL;
	}

	public function getName() : string
	{
		return "Wither Skull";
	}

	protected function onHitEntity(Entity $entityHit, RayTraceResult $hitResult) : void
	{
		parent::onHitEntity($entityHit, $hitResult);
		if ($entityHit instanceof Living) {
			$entityHit->getEffects()->add(new EffectInstance(VanillaEffects::WITHER(), 200, 1));
		}
	}
}
