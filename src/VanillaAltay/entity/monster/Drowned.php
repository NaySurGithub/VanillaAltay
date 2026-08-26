<?php

declare(strict_types=1);

namespace VanillaAltay\entity\monster;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Drowned extends Zombie
{
	protected function entityBaseTick(int $tickDiff = 1) : bool
	{
		$this->setHasGravity(!$this->isUnderwater());
		return parent::entityBaseTick($tickDiff);
	}

	public function walkToward(\pocketmine\math\Vector3 $destination, float $speed) : void
	{
		if (!$this->isUnderwater()) {
			parent::walkToward($destination, $speed);
			return;
		}$delta = $destination->subtractVector($this->getPosition());
		if ($delta->lengthSquared() < .001) {
			$this->setMotion(\pocketmine\math\Vector3::zero());
			return;
		}$this->setMotion($delta->normalize()->multiply($speed));
		$this->lookAtEntity($this->getTarget() ?? $this);
	}

	public static function getNetworkTypeId() : string
	{
		return EntityIds::DROWNED;
	}

	public function getName() : string
	{
		return "Drowned";
	}
}
