<?php

declare(strict_types=1);

namespace VanillaAltay\entity\projectile;

use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class SmallFireball extends MagicProjectile
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::SMALL_FIREBALL;
	}

	public function getName() : string
	{
		return "Small Fireball";
	}

	protected function initEntity(\pocketmine\nbt\tag\CompoundTag $nbt) : void
	{
		parent::initEntity($nbt);
		$this->setOnFire(1200);
	}

	protected function onHit(ProjectileHitEvent $event) : void
	{
		$this->flagForDespawn();
	}
}
