<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\populator;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use function count;

/**
 * Scatters a patch of flowers around each attempt, since vanilla flowers grow in clusters rather than one by one.
 */
final class Flower extends SurfacePopulator{

	private const PATCH_RADIUS = 3;
	private const PATCH_ATTEMPTS = 12;

	/**
	 * @param Block[] $types
	 */
	public function __construct(private array $types){
		if(count($this->types) === 0){
			throw new \InvalidArgumentException("A flower populator needs at least one flower type");
		}
	}

	protected function place(ChunkManager $world, int $x, int $y, int $z, Random $random) : void{
		$type = $this->types[$random->nextBoundedInt(count($this->types))];

		for($i = 0; $i < self::PATCH_ATTEMPTS; ++$i){
			$patchX = $x + $random->nextRange(-self::PATCH_RADIUS, self::PATCH_RADIUS);
			$patchZ = $z + $random->nextRange(-self::PATCH_RADIUS, self::PATCH_RADIUS);
			$patchY = self::getHighestWorkableBlock($world, $patchX, $patchZ);

			if($patchY === -1 || self::getGroundType($world, $patchX, $patchY, $patchZ) !== BlockTypeIds::GRASS){
				continue;
			}

			if(self::isAir($world, $patchX, $patchY, $patchZ)){
				$world->setBlockAt($patchX, $patchY, $patchZ, $type);
			}
		}
	}
}
