<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class MagmaCube extends Slime
{
	protected const NETWORK_ID = EntityIds::MAGMA_CUBE;

	protected const NAME = "Magma Cube";

	protected function isMagmaCube() : bool
	{
		return true;
	}

	protected function getAttackDamage() : float
	{
		return parent::getAttackDamage() + ($this->getMaxHealth() > 1 ? 1 : 0);
	}
}
