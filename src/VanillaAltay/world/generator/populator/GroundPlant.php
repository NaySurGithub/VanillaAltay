<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\populator;

use pocketmine\block\Block;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;

use function in_array;

/**
 * Places a single block wherever the ground underneath is one of the accepted types. Covers everything that is
 * just "one block on top of another": pumpkins, dead bushes, mushrooms, lily pads.
 */
final class GroundPlant extends SurfacePopulator
{
	/**
	 * @param int[] $allowedGround
	 */
	public function __construct(private Block $plant, private array $allowedGround)
	{
	}

	protected function place(ChunkManager $world, int $x, int $y, int $z, Random $random) : void
	{
		if (!self::isAir($world, $x, $y, $z)) {
			return;
		}

		if (!in_array(self::getGroundType($world, $x, $y, $z), $this->allowedGround, true)) {
			return;
		}

		$world->setBlockAt($x, $y, $z, $this->plant);
	}
}
