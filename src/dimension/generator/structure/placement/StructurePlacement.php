<?php

declare(strict_types=1);

namespace dimension\generator\structure\placement;

use dimension\generator\ChunkHash;
use dimension\generator\math\Int64;
use dimension\generator\random\RandomSource;
use function floor;

/**
 * Grid placement of a structure: the world is cut into square regions of
 * $maxDistance chunks and each region holds one candidate start chunk, at a
 * random offset below $maxDistance - $minDistance from the region corner.
 */
final class StructurePlacement{

	public const DEFAULT_SALT = 0x76694565C616765;

	/**
	 * @param \Closure(int) : bool|null $biomeFilter
	 */
	public function __construct(
		private int $salt,
		private int $minDistance,
		private int $maxDistance,
		private ?\Closure $biomeFilter = null
	){}

	public function isValidBiome(int $biome) : bool{
		return $this->biomeFilter === null || ($this->biomeFilter)($biome);
	}

	public function canGenerate(int $levelSeed, RandomSource $random, int $chunkX, int $chunkZ, int $biome) : bool{
		$regionX = self::floorDiv($chunkX, $this->maxDistance);
		$regionZ = self::floorDiv($chunkZ, $this->maxDistance);
		$random->setSeed(Int64::add($levelSeed ^ $this->salt, ChunkHash::hash($regionX, $regionZ)));
		return $this->isValidBiome($biome)
			&& $regionX * $this->maxDistance + $random->nextBoundedInt($this->maxDistance - $this->minDistance) === $chunkX
			&& $regionZ * $this->maxDistance + $random->nextBoundedInt($this->maxDistance - $this->minDistance) === $chunkZ;
	}

	public static function floorDiv(int $value, int $divisor) : int{
		return (int) floor($value / $divisor);
	}
}
