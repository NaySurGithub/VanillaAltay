<?php

declare(strict_types=1);

namespace redstone\vanilla;

use pocketmine\block\Block;

class Redstone extends \pocketmine\item\Redstone{

	public function getBlock(?int $clickedFace = null) : Block{
		return ExtraVanillaBlocks::REDSTONE_WIRE();
	}
}