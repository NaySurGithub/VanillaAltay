<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\populator;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\populator\Populator;

/**
 * Carves an ellipsoid pocket in the ground and fills its lower half with a fluid, the way vanilla scatters
 * surface ponds.
 */
final class Lake implements Populator
{
	private const RADIUS = 4;

	private const DEPTH = 3;

	public function __construct(private Block $fluid, private int $chance)

	{

	}

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void
	{
		if ($random->nextBoundedInt($this->chance) !== 0) {
			return;
		}

		$x = $random->nextRange($chunkX * Chunk::EDGE_LENGTH, ($chunkX * Chunk::EDGE_LENGTH) + Chunk::EDGE_LENGTH - 1);
		$z = $random->nextRange($chunkZ * Chunk::EDGE_LENGTH, ($chunkZ * Chunk::EDGE_LENGTH) + Chunk::EDGE_LENGTH - 1);

		$surface = SurfacePopulator::getHighestWorkableBlock($world, $x, $z);
		if ($surface === -1) {
			return;
		}

		//the pond is dug into the ground, so its rim sits just under the surface
		$y = $surface - 1;
		if (!$this->isDiggable($world, $x, $y, $z)) {
			return;
		}

		$air = VanillaBlocks::AIR();
		for ($dx = -self::RADIUS; $dx <= self::RADIUS; ++$dx) {
			for ($dz = -self::RADIUS; $dz <= self::RADIUS; ++$dz) {
				for ($dy = -self::DEPTH; $dy <= 1; ++$dy) {
					if ((($dx * $dx) + ($dz * $dz)) / (self::RADIUS * self::RADIUS) + (($dy * $dy) / (self::DEPTH * self::DEPTH)) > 1) {
						continue;
					}

					$world->setBlockAt($x + $dx, $y + $dy, $z + $dz, $dy <= 0 ? $this->fluid : $air);
				}
			}
		}
	}

	/**
	 * A pond only forms where the ground can hold it: every block of its shell has to be solid, otherwise the
	 * water would pour out of a cliff or hang in the air.
	 */
	private function isDiggable(ChunkManager $world, int $x, int $y, int $z) : bool
	{
		for ($dx = -self::RADIUS; $dx <= self::RADIUS; ++$dx) {
			for ($dz = -self::RADIUS; $dz <= self::RADIUS; ++$dz) {
				for ($dy = -self::DEPTH; $dy <= 0; ++$dy) {
					$distance = (($dx * $dx) + ($dz * $dz)) / (self::RADIUS * self::RADIUS) + (($dy * $dy) / (self::DEPTH * self::DEPTH));
					if ($distance > 1 || $distance < 0.75) {
						continue;
					}

					$block = $world->getBlockAt($x + $dx, $y + $dy, $z + $dz);
					if ($block->getTypeId() === BlockTypeIds::AIR || !$block->isSolid()) {
						return false;
					}
				}
			}
		}

		return true;
	}
}
