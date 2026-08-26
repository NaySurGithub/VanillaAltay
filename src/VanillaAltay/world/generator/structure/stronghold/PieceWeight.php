<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\stronghold;

final class PieceWeight
{
	public int $placeCount = 0;

	/**
	 * @phpstan-param class-string<StrongholdPiece> $pieceClass
	 */
	public function __construct(
		public string $pieceClass,
		public int $weight,
		public int $maxPlaceCount,
		public int $minGenDepth = 0,
	) {}

	public function doPlace(int $genDepth) : bool
	{
		return $this->isValid() && $genDepth > $this->minGenDepth;
	}

	public function isValid() : bool
	{
		return $this->maxPlaceCount === 0 || $this->placeCount < $this->maxPlaceCount;
	}
}
