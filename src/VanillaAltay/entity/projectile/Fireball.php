<?php

declare(strict_types=1);

namespace VanillaAltay\entity\projectile;

use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\Explosion;
use pocketmine\world\Position;

final class Fireball extends MagicProjectile
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::FIREBALL;
	}

	public function getName() : string
	{
		return "Fireball";
	}

	protected function onHit(ProjectileHitEvent $event) : void
	{
		$explosion = new Explosion(Position::fromObject($this->getPosition(), $this->getWorld()), 1.5, $this);
		$explosion->explodeA();
		$explosion->explodeB();
		$this->flagForDespawn();
	}
}
