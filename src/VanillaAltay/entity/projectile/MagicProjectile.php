<?php

declare(strict_types=1);

namespace VanillaAltay\entity\projectile;

use pocketmine\block\Block;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\projectile\Projectile;
use pocketmine\math\RayTraceResult;

abstract class MagicProjectile extends Projectile
{
	protected function getInitialSizeInfo() : EntitySizeInfo
	{
		return new EntitySizeInfo(.3125, .3125);
	}

	protected function getInitialDragMultiplier() : float
	{
		return .01;
	}

	protected function getInitialGravity() : float
	{
		return 0;
	}

	protected function onHitBlock(Block $blockHit, RayTraceResult $hitResult) : void
	{
		parent::onHitBlock($blockHit, $hitResult);
		$this->flagForDespawn();
	}

	public function getName() : string
	{
		return "Projectile";
	}

	public function getDrops() : array
	{
		return [];
	}
}
