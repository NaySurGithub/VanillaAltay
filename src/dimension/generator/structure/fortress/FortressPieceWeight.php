<?php

declare(strict_types=1);

namespace dimension\generator\structure\fortress;

/**
 * Weight and placement limit of one fortress piece type while a fortress
 * is laid out. A limit of 0 means unlimited.
 */
final class FortressPieceWeight{

	public int $placeCount = 0;

	/**
	 * @param class-string<FortressPiece> $pieceType
	 */
	public function __construct(
		public readonly string $pieceType,
		public readonly int $weight,
		public readonly int $maxPlaceCount,
		public readonly bool $allowInRow = false
	){}

	public function doPlace(int $genDepth) : bool{
		return $this->maxPlaceCount === 0 || $this->placeCount < $this->maxPlaceCount;
	}

	public function isValid() : bool{
		return $this->maxPlaceCount === 0 || $this->placeCount < $this->maxPlaceCount;
	}

	/**
	 * @return list<FortressPieceWeight>
	 */
	public static function bridgePieces() : array{
		return [
			new FortressPieceWeight(BridgeStraight::class, 30, 0, true),
			new FortressPieceWeight(BridgeCrossing::class, 10, 4),
			new FortressPieceWeight(RoomCrossing::class, 10, 4),
			new FortressPieceWeight(StairsRoom::class, 10, 3),
			new FortressPieceWeight(MonsterThrone::class, 5, 2),
			new FortressPieceWeight(CastleEntrance::class, 5, 1)
		];
	}

	/**
	 * @return list<FortressPieceWeight>
	 */
	public static function castlePieces() : array{
		return [
			new FortressPieceWeight(CastleSmallCorridor::class, 25, 0, true),
			new FortressPieceWeight(CastleSmallCorridorCrossing::class, 15, 5),
			new FortressPieceWeight(CastleSmallCorridorRightTurn::class, 5, 10),
			new FortressPieceWeight(CastleSmallCorridorLeftTurn::class, 5, 10),
			new FortressPieceWeight(CastleCorridorStairs::class, 10, 3, true),
			new FortressPieceWeight(CastleCorridorTBalcony::class, 7, 2),
			new FortressPieceWeight(CastleStalkRoom::class, 5, 2)
		];
	}
}
