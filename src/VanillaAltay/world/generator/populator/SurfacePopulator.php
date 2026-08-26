<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\populator;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\Leaves;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\populator\Populator;

/**
 * Base for everything that scatters blocks on the surface of a chunk: it draws a random amount of attempts,
 * picks a column for each and hands the caller the first free block above the ground.
 */
abstract class SurfacePopulator implements Populator
{
	private int $baseAmount = 0;

	private int $randomAmount = 1;

	private int $chance = 1;

	public function setBaseAmount(int $amount) : void
	{
		$this->baseAmount = $amount;
	}

	public function setRandomAmount(int $amount) : void
	{
		$this->randomAmount = $amount;
	}

	/**
	 * Runs the populator in one chunk out of the given number, for things vanilla scatters over a wide area
	 * rather than putting in every chunk.
	 */
	public function setChance(int $oneInChunks) : void
	{
		$this->chance = $oneInChunks;
	}

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void
	{
		if ($this->chance > 1 && $random->nextBoundedInt($this->chance) !== 0) {
			return;
		}

		$amount = $random->nextRange(0, $this->randomAmount) + $this->baseAmount;

		for ($i = 0; $i < $amount; ++$i) {
			$x = $random->nextRange($chunkX * Chunk::EDGE_LENGTH, ($chunkX * Chunk::EDGE_LENGTH) + Chunk::EDGE_LENGTH - 1);
			$z = $random->nextRange($chunkZ * Chunk::EDGE_LENGTH, ($chunkZ * Chunk::EDGE_LENGTH) + Chunk::EDGE_LENGTH - 1);
			$y = $this->getPlacementY($world, $x, $z);

			if ($y !== -1) {
				$this->place($world, $x, $y, $z, $random);
			}
		}
	}

	abstract protected function place(ChunkManager $world, int $x, int $y, int $z, Random $random) : void;

	protected function getPlacementY(ChunkManager $world, int $x, int $z) : int
	{
		return self::getHighestWorkableBlock($world, $x, $z);
	}

	/**
	 * Returns the first free block above the ground, or -1 if the column has nothing to stand on.
	 */
	public static function getHighestWorkableBlock(ChunkManager $world, int $x, int $z) : int
	{
		$highest = $world->getChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE)?->getHighestBlockAt($x & Chunk::COORD_MASK, $z & Chunk::COORD_MASK);
		if ($highest === null) {
			return -1;
		}

		for ($y = $highest; $y >= $world->getMinY(); --$y) {
			$block = $world->getBlockAt($x, $y, $z);
			if ($block->getTypeId() !== BlockTypeIds::AIR && !($block instanceof Leaves) && $block->getTypeId() !== BlockTypeIds::SNOW_LAYER) {
				return $y + 1;
			}
		}

		return -1;
	}

	protected static function getGroundType(ChunkManager $world, int $x, int $y, int $z) : int
	{
		return $world->getBlockAt($x, $y - 1, $z)->getTypeId();
	}

	protected static function isAir(ChunkManager $world, int $x, int $y, int $z) : bool
	{
		return $world->getBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::AIR;
	}
}
