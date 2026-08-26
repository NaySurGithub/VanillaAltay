<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\populator;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;

final class SugarCane extends SurfacePopulator
{
	private const MIN_HEIGHT = 2;

	private const MAX_HEIGHT = 4;

	protected function place(ChunkManager $world, int $x, int $y, int $z, Random $random) : void
	{
		$ground = self::getGroundType($world, $x, $y, $z);
		if ($ground !== BlockTypeIds::GRASS && $ground !== BlockTypeIds::DIRT && $ground !== BlockTypeIds::SAND) {
			return;
		}

		if (!self::isAir($world, $x, $y, $z) || !$this->isNextToWater($world, $x, $y - 1, $z)) {
			return;
		}

		$block = VanillaBlocks::SUGARCANE();
		$height = $random->nextRange(self::MIN_HEIGHT, self::MAX_HEIGHT);
		for ($i = 0; $i < $height; ++$i) {
			if (!self::isAir($world, $x, $y + $i, $z)) {
				return;
			}
			$world->setBlockAt($x, $y + $i, $z, $block);
		}
	}

	private function isNextToWater(ChunkManager $world, int $x, int $y, int $z) : bool
	{
		foreach (Facing::HORIZONTAL as $facing) {
			[$dx, $dy, $dz] = Facing::OFFSET[$facing];
			if ($world->getBlockAt($x + $dx, $y + $dy, $z + $dz)->getTypeId() === BlockTypeIds::WATER) {
				return true;
			}
		}

		return false;
	}
}
