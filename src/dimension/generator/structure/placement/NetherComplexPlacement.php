<?php

declare(strict_types=1);

namespace dimension\generator\structure\placement;

use dimension\generator\ChunkHash;
use dimension\generator\math\Int64;
use dimension\generator\random\RandomSource;

/**
 * Shared placement of nether fortresses and bastion remnants: one start per
 * region of 30 by 30 chunks, which is a bastion or a fortress depending on
 * a second roll made per region.
 */
final class NetherComplexPlacement{

	public const REGION_SIZE_CHUNKS = 30;
	public const EDGE_EXCLUSION_CHUNKS = 4;
	private const CANDIDATE_OFFSET_MAX = self::REGION_SIZE_CHUNKS - self::EDGE_EXCLUSION_CHUNKS - 1;
	private const PLACEMENT_SALT = 0x42415354494F4E;
	private const SELECTOR_SALT = 0x4E45544845524C;

	private function __construct(){
	}

	/**
	 * Start chunk of the region, as [chunkX, chunkZ].
	 *
	 * @return array{int, int}
	 */
	public static function regionStart(int $levelSeed, int $regionX, int $regionZ, RandomSource $random) : array{
		$random->setSeed(Int64::add($levelSeed ^ self::PLACEMENT_SALT, ChunkHash::hash($regionX, $regionZ)));
		$candidateX = $regionX * self::REGION_SIZE_CHUNKS + $random->nextBoundedInt(self::CANDIDATE_OFFSET_MAX);
		$candidateZ = $regionZ * self::REGION_SIZE_CHUNKS + $random->nextBoundedInt(self::CANDIDATE_OFFSET_MAX);
		return [$candidateX, $candidateZ];
	}

	public static function isNetherComplexStart(int $levelSeed, int $chunkX, int $chunkZ, RandomSource $random) : bool{
		$regionX = StructurePlacement::floorDiv($chunkX, self::REGION_SIZE_CHUNKS);
		$regionZ = StructurePlacement::floorDiv($chunkZ, self::REGION_SIZE_CHUNKS);
		[$candidateX, $candidateZ] = self::regionStart($levelSeed, $regionX, $regionZ, $random);
		return $candidateX === $chunkX && $candidateZ === $chunkZ;
	}

	public static function shouldGenerateBastion(int $levelSeed, int $chunkX, int $chunkZ, RandomSource $random) : bool{
		$regionX = StructurePlacement::floorDiv($chunkX, self::REGION_SIZE_CHUNKS);
		$regionZ = StructurePlacement::floorDiv($chunkZ, self::REGION_SIZE_CHUNKS);
		$random->setSeed(Int64::add($levelSeed ^ self::SELECTOR_SALT, ChunkHash::hash($regionX, $regionZ)));
		return $random->nextBoundedInt(2) !== 0;
	}
}
