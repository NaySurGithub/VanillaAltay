<?php

declare(strict_types=1);

namespace dimension\portal\block;

use pocketmine\block\Transparent;
use pocketmine\block\utils\SupportType;
use pocketmine\item\Item;

/**
 * The end portal block: an unbreakable, non-solid light source. Entities
 * entering it are moved between the overworld and the end by the portal
 * listener.
 */
class EndPortal extends Transparent{

	public function getLightLevel() : int{
		return 15;
	}

	public function isSolid() : bool{
		return false;
	}

	public function canBeReplaced() : bool{
		return false;
	}

	protected function recalculateCollisionBoxes() : array{
		return [];
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE;
	}

	public function getDrops(Item $item) : array{
		return [];
	}
}
