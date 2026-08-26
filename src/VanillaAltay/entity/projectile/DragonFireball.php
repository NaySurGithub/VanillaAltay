<?php

declare(strict_types=1);

namespace VanillaAltay\entity\projectile;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class DragonFireball extends MagicProjectile
{
	public static function getNetworkTypeId() : string
	{
		return EntityIds::DRAGON_FIREBALL;
	}

	public function getName() : string
	{
		return "Dragon Fireball";
	}
}
