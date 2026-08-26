<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure;

use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use VanillaAltay\world\generator\structure\mineshaft\BoundingBox;
use VanillaAltay\world\generator\structure\stronghold\PieceGenerator;
use VanillaAltay\world\generator\structure\stronghold\StairsDown;
use VanillaAltay\world\generator\structure\stronghold\StrongholdPiece;

use function array_key_first;
use function count;

final class Stronghold implements SpreadStructure
{
	private const SALT = 0x76694565C616765;

	private const MIN_DISTANCE = 3;

	private const MAX_DISTANCE = 32;

	/**
	 * Measured over sixty layouts: the furthest block sat a hundred and twenty-three from the origin, eight chunks
	 * away, so every chunk within nine of it has to rebuild it to write its own share.
	 */
	private const CHUNK_REACH = 9;

	private const LAYOUT_CACHE_SIZE = 8;

	private const SEA_LEVEL = 63;

	private const MIN_DEPTH_BELOW_SEA = 10;

	private const MAX_ATTEMPTS = 10;

	/**
	 * @var array[]
	 * @phpstan-var array<int, array{StrongholdPiece[], BoundingBox}>
	 */
	private array $layouts = [];

	public function getName() : string
	{
		return "stronghold";
	}

	public function getPlacement() : StructurePlacement
	{
		return new StructurePlacement(self::SALT, self::MIN_DISTANCE, self::MAX_DISTANCE, fn(int $biomeId) => true);
	}

	public function getChunkReach() : int
	{
		return self::CHUNK_REACH;
	}

	public function place(ChunkManager $world, Random $random, int $x, int $y, int $z) : void
	{
		$this->placeAround($world, $random, $x >> 4, $z >> 4, $x >> 4, $z >> 4);
	}

	public function placeAround(ChunkManager $world, Random $random, int $originChunkX, int $originChunkZ, int $targetChunkX, int $targetChunkZ) : void
	{
		$layoutSeed = $random->getSeed();
		[$pieces] = $this->getLayout($random, $layoutSeed, $originChunkX, $originChunkZ);

		$clip = new BoundingBox(
			($targetChunkX - 1) << 4,
			$world->getMinY(),
			($targetChunkZ - 1) << 4,
			(($targetChunkX + 2) << 4) - 1,
			$world->getMaxY() - 1,
			(($targetChunkZ + 2) << 4) - 1,
		);

		//iron doors put their buttons one block outside the piece, so the skip test has a block of slack
		$reachable = new BoundingBox($clip->x0 - 1, $clip->y0, $clip->z0 - 1, $clip->x1 + 1, $clip->y1, $clip->z1 + 1);

		foreach ($pieces as $index => $piece) {
			if (!$piece->boundingBox->intersects($reachable)) {
				continue;
			}

			$random->setSeed($layoutSeed ^ ($index * 0x9E3779B1));
			$piece->postProcess($world, $random, $clip);
		}
	}

	/**
	 * @return array{StrongholdPiece[], BoundingBox}
	 */
	private function getLayout(Random $random, int $layoutSeed, int $chunkX, int $chunkZ) : array
	{
		if (isset($this->layouts[$layoutSeed])) {
			return $this->layouts[$layoutSeed];
		}

		$generator = null;
		$total = null;

		for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; ++$attempt) {
			$random->setSeed($layoutSeed + $attempt);
			$random->setSeed(($chunkX * $random->nextSignedInt()) ^ ($chunkZ * $random->nextSignedInt()) ^ $layoutSeed);

			$generator = new PieceGenerator();
			$start = StairsDown::createSource($random, ($chunkX << 4) + 2, ($chunkZ << 4) + 2);
			$generator->setStart($start);
			$start->addChildren($generator, $random);
			$generator->drainPendingChildren($random);

			$total = self::totalBox($generator->pieces);
			$offset = self::belowSeaLevelOffset($random, $total);
			foreach ($generator->pieces as $piece) {
				$piece->move(0, $offset, 0);
			}
			$total->move(0, $offset, 0);

			if ($generator->portalRoom !== null) {
				break;
			}
		}

		if (count($this->layouts) >= self::LAYOUT_CACHE_SIZE) {
			//the keys are layout seeds, so shifting would renumber them and hand back the wrong layout
			unset($this->layouts[array_key_first($this->layouts)]);
		}

		return $this->layouts[$layoutSeed] = [$generator->pieces, $total];
	}

	/**
	 * @param StrongholdPiece[] $pieces
	 */
	private static function totalBox(array $pieces) : BoundingBox
	{
		$first = $pieces[0]->boundingBox;
		$total = new BoundingBox($first->x0, $first->y0, $first->z0, $first->x1, $first->y1, $first->z1);

		foreach ($pieces as $piece) {
			$total->expand($piece->boundingBox);
		}

		return $total;
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
