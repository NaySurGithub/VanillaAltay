<?php

declare(strict_types=1);

namespace VanillaAltay\entity\projectile;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\math\RayTraceResult;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class ShulkerBullet extends MagicProjectile
{
	private ?Living $homingTarget = null;

	public function setHomingTarget(Living $target) : void
	{
		$this->homingTarget = $target;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool
	{
		if ($this->homingTarget !== null && !$this->homingTarget->isClosed() && $this->homingTarget->isAlive()) {
			$desired = $this->homingTarget->getPosition()->add(0, $this->homingTarget->getEyeHeight() * .6, 0)->subtractVector($this->getPosition())->normalize()->multiply(.6);
			$this->setMotion($this->getMotion()->multiply(.75)->addVector($desired->multiply(.25)));
		}return parent::entityBaseTick($tickDiff);
	}

	public static function getNetworkTypeId() : string
	{
		return EntityIds::SHULKER_BULLET;
	}

	public function getName() : string
	{
		return "Shulker Bullet";
	}

	protected function onHitEntity(Entity $entityHit, RayTraceResult $hitResult) : void
	{
		parent::onHitEntity($entityHit, $hitResult);
		if ($entityHit instanceof Living) {
			$entityHit->getEffects()->add(new EffectInstance(VanillaEffects::LEVITATION(), 200));
		}
	}
}
