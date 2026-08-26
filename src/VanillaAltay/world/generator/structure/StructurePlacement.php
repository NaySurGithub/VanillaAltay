<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure;

use pocketmine\utils\Random;
use pocketmine\world\World;

use function intdiv;

/**
 * Decides in which chunks a structure may appear.
 *
 * The world is cut into square regions of maxDistance chunks; each region draws one candidate chunk from a
 * seed built from the world seed, the structure's own salt and the region coordinates. Two structures of the
 * same kind are therefore never closer than minDistance nor further apart than maxDistance, and any thread can
 * answer the question for any chunk without generating anything.
 */
final class StructurePlacement
{
	/**
	 * @param \Closure(int) : bool $isBiomeValid
	 */
	public function __construct(
		private int $salt,
		private int $minDistance,
		private int $maxDistance,
		private \Closure $isBiomeValid,
	) {
	}

	public function canGenerate(int $worldSeed, Random $random, int $chunkX, int $chunkZ, int $biomeId) : bool
	{
		if (!($this->isBiomeValid)($biomeId)) {
			return false;
		}

		[$candidateX, $candidateZ] = $this->getCandidateChunk($worldSeed, $random, self::regionCoord($chunkX, $this->maxDistance), self::regionCoord($chunkZ, $this->maxDistance));

		return $candidateX === $chunkX && $candidateZ === $chunkZ;
	}

	/**
	 * @return int[]
	 * @phpstan-return array{int, int}
	 */
	public function getCandidateChunk(int $worldSeed, Random $random, int $regionX, int $regionZ) : array
	{
		// chunkHash() may use the complete signed 64-bit range. Adding another
		// integer can overflow and make PHP promote the result to float, which
		// Random::setSeed() correctly rejects. XOR keeps the mix deterministic
		// while guaranteeing that the result remains an integer.
		$seed = $worldSeed ^ $this->salt ^ World::chunkHash($regionX, $regionZ);
		$random->setSeed($seed);

		$spread = $this->maxDistance - $this->minDistance;

		return [
			$regionX * $this->maxDistance + $random->nextBoundedInt($spread),
			$regionZ * $this->maxDistance + $random->nextBoundedInt($spread),
		];
	}

	public function getMaxDistance() : int
	{
		return $this->maxDistance;
	}

	public function isBiomeValid(int $biomeId) : bool
	{
		return ($this->isBiomeValid)($biomeId);
	}

	public static function regionCoord(int $chunk, int $spacing) : int
	{
		return $chunk < 0 ? intdiv($chunk - $spacing - 1, $spacing) : intdiv($chunk, $spacing);
	}
}
