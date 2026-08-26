<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure;

use pocketmine\data\bedrock\BiomeIds;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use VanillaAltay\world\generator\structure\mineshaft\BoundingBox;
use VanillaAltay\world\generator\structure\mineshaft\MineshaftPiece;
use VanillaAltay\world\generator\structure\mineshaft\MineshaftRoom;

use function array_key_first;
use function count;
use function in_array;
use function intdiv;

final class Mineshaft implements SpreadStructure
{
	private const SALT = 30915278;

	/**
	 * Measured over sixty layouts: a shaft reaches at most a hundred blocks from its origin, so every chunk
	 * within seven of it has to rebuild it to write its own share.
	 */
	private const CHUNK_REACH = 7;

	private const LAYOUT_CACHE_SIZE = 8;

	/**
	 * @var array[]
	 * @phpstan-var array<int, array{MineshaftPiece[], BoundingBox}>
	 */
	private array $layouts = [];

	private const SEA_LEVEL = 63;

	private const MIN_DEPTH_BELOW_SEA = 10;

	private const MESA_BIOMES = [
		BiomeIds::MESA,
		BiomeIds::MESA_PLATEAU,
		BiomeIds::MESA_PLATEAU_STONE,
		BiomeIds::MESA_BRYCE,
		BiomeIds::MESA_PLATEAU_MUTATED,
		BiomeIds::MESA_PLATEAU_STONE_MUTATED,
	];

	public function getName() : string
	{
		return "mineshaft";
	}

	public function getPlacement() : StructurePlacement
	{
		return new StructurePlacement(self::SALT, 4, 16, fn(int $biomeId) => true);
	}

	public function getChunkReach() : int
	{
		return self::CHUNK_REACH;
	}

	/**
	 * The layout is rebuilt identically from every chunk it reaches, and each of them writes only the part that
	 * falls inside the chunks it has loaded. A shaft therefore spans as far as it wants, one chunk at a time,
	 * instead of being cut to the chunk it started from.
	 */
	public function place(ChunkManager $world, Random $random, int $x, int $y, int $z) : void
	{
		$this->placeAround($world, $random, $x >> 4, $z >> 4, $x >> 4, $z >> 4);
	}

	public function placeAround(ChunkManager $world, Random $random, int $originChunkX, int $originChunkZ, int $targetChunkX, int $targetChunkZ) : void
	{
		$origin = $world->getChunk($originChunkX, $originChunkZ);
		$mesa = $origin !== null && in_array($origin->getBiomeId(7, 0, 7), self::MESA_BIOMES, true);

		$layoutSeed = $random->getSeed();
		[$pieces] = $this->getLayout($random, $layoutSeed, $originChunkX, $originChunkZ, $mesa);

		$clip = new BoundingBox(
			($targetChunkX - 1) << 4,
			$world->getMinY(),
			($targetChunkZ - 1) << 4,
			(($targetChunkX + 2) << 4) - 1,
			$world->getMaxY() - 1,
			(($targetChunkZ + 2) << 4) - 1,
		);

		foreach ($pieces as $index => $piece) {
			if (!$piece->boundingBox->intersects($clip)) {
				continue;
			}

			//each piece decorates from its own seed, so the chunks that share it write exactly the same blocks
			$random->setSeed($layoutSeed ^ ($index * 0x9E3779B1));
			$piece->postProcess($world, $random, $clip);
		}
	}

	/**
	 * Building a layout costs more than writing it, and every chunk a shaft crosses asks for the same one, so
	 * the last few are kept.
	 *
	 * @return array{MineshaftPiece[], BoundingBox}
	 */
	private function getLayout(Random $random, int $layoutSeed, int $chunkX, int $chunkZ, bool $mesa) : array
	{
		if (isset($this->layouts[$layoutSeed])) {
			return $this->layouts[$layoutSeed];
		}

		$room = new MineshaftRoom(0, $random, ($chunkX << 4) + 2, ($chunkZ << 4) + 2, $mesa);
		$pieces = [$room];
		$room->addChildren($room, $pieces, $random);

		$total = new BoundingBox($room->boundingBox->x0, $room->boundingBox->y0, $room->boundingBox->z0, $room->boundingBox->x1, $room->boundingBox->y1, $room->boundingBox->z1);
		foreach ($pieces as $piece) {
			$total->expand($piece->boundingBox);
		}

		$offset = $mesa
			? 64 - $total->y1 + intdiv($total->getYSpan(), 2) + 5
			: self::belowSeaLevelOffset($random, $total);

		foreach ($pieces as $piece) {
			$piece->move(0, $offset, 0);
		}
		$total->move(0, $offset, 0);

		if (count($this->layouts) >= self::LAYOUT_CACHE_SIZE) {
			//the keys are layout seeds, so shifting would renumber them and hand back the wrong layout
			unset($this->layouts[array_key_first($this->layouts)]);
		}

		return $this->layouts[$layoutSeed] = [$pieces, $total];
	}

	private static function belowSeaLevelOffset(Random $random, BoundingBox $total) : int
	{
		$range = self::SEA_LEVEL - self::MIN_DEPTH_BELOW_SEA;
		$top = $total->getYSpan() - 64 + 1;
		if ($top < $range) {
			$top += $random->nextBoundedInt($range - $top);
		}

		return $top - $total->y1;
	}
}
