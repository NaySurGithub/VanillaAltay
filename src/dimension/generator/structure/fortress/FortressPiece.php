<?php

declare(strict_types=1);

namespace dimension\generator\structure\fortress;

use dimension\generator\random\RandomSource;
use dimension\generator\structure\BoundingBox;
use dimension\generator\structure\StructurePiece;
use pocketmine\math\Facing;
use function abs;

/**
 * A nether fortress piece. Pieces grow the fortress by asking for children
 * at their exits: bridge pieces lead to bridge pieces, castle pieces to
 * castle pieces, and a dead end is closed by a crumbling bridge end.
 */
abstract class FortressPiece extends StructurePiece{

	/**
	 * @param list<StructurePiece> $pieces
	 */
	abstract public static function createPiece(array $pieces, RandomSource $random, int $x, int $y, int $z, int $orientation, int $genDepth) : ?FortressPiece;

	protected static function isOkBox(?BoundingBox $boundingBox) : bool{
		return $boundingBox !== null && $boundingBox->y0 > 10;
	}

	/**
	 * @param list<StructurePiece> $pieces
	 */
	public function addChildren(FortressStartPiece $start, array &$pieces, RandomSource $random) : void{
	}

	/**
	 * @param list<FortressPieceWeight> $weights
	 */
	private function updatePieceWeight(array $weights) : int{
		$success = false;
		$total = 0;
		foreach($weights as $weight){
			if($weight->maxPlaceCount > 0 && $weight->placeCount < $weight->maxPlaceCount){
				$success = true;
			}
			$total += $weight->weight;
		}
		return $success ? $total : -1;
	}

	/**
	 * @param list<StructurePiece> $pieces
	 */
	private function generatePiece(FortressStartPiece $start, bool $isCastle, array $pieces, RandomSource $random, int $x, int $y, int $z, int $orientation, int $genDepth) : ?FortressPiece{
		$weights = $isCastle ? $start->availableCastlePieces : $start->availableBridgePieces;
		$total = $this->updatePieceWeight($weights);
		if($total > 0 && $genDepth <= 30){
			for($i = 0; $i < 5; ++$i){
				$target = $random->nextBoundedInt($total);
				foreach($weights as $weight){
					$target -= $weight->weight;
					if($target < 0){
						if(!$weight->doPlace($genDepth) || ($weight === $start->previousPiece && !$weight->allowInRow)){
							break;
						}
						$type = $weight->pieceType;
						$piece = $type::createPiece($pieces, $random, $x, $y, $z, $orientation, $genDepth);
						if($piece !== null){
							++$weight->placeCount;
							$start->previousPiece = $weight;
							if(!$weight->isValid()){
								$start->removeWeight($weight, $isCastle);
							}
							return $piece;
						}
					}
				}
			}
		}
		return BridgeEndFiller::createPiece($pieces, $random, $x, $y, $z, $orientation, $genDepth);
	}

	/**
	 * @param list<StructurePiece> $pieces
	 */
	private function generateAndAddPiece(FortressStartPiece $start, array &$pieces, RandomSource $random, int $x, int $y, int $z, int $orientation, int $genDepth, bool $isCastle) : ?StructurePiece{
		$startBox = $start->getBoundingBox();
		if(abs($x - $startBox->x0) > 112 || abs($z - $startBox->z0) > 112){
			return BridgeEndFiller::createPiece($pieces, $random, $x, $y, $z, $orientation, $genDepth);
		}
		$piece = $this->generatePiece($start, $isCastle, $pieces, $random, $x, $y, $z, $orientation, $genDepth + 1);
		if($piece !== null){
			$pieces[] = $piece;
			$start->pendingChildren[] = $piece;
		}
		return $piece;
	}

	/**
	 * @param list<StructurePiece> $pieces
	 */
	protected function generateChildForward(FortressStartPiece $start, array &$pieces, RandomSource $random, int $horizontalOffset, int $yOffset, bool $isCastle) : ?StructurePiece{
		$orientation = $this->getOrientation();
		if($orientation === null){
			return null;
		}
		$box = $this->boundingBox;
		return match($orientation){
			Facing::SOUTH => $this->generateAndAddPiece($start, $pieces, $random, $box->x0 + $horizontalOffset, $box->y0 + $yOffset, $box->z1 + 1, $orientation, $this->genDepth, $isCastle),
			Facing::WEST => $this->generateAndAddPiece($start, $pieces, $random, $box->x0 - 1, $box->y0 + $yOffset, $box->z0 + $horizontalOffset, $orientation, $this->genDepth, $isCastle),
			Facing::EAST => $this->generateAndAddPiece($start, $pieces, $random, $box->x1 + 1, $box->y0 + $yOffset, $box->z0 + $horizontalOffset, $orientation, $this->genDepth, $isCastle),
			default => $this->generateAndAddPiece($start, $pieces, $random, $box->x0 + $horizontalOffset, $box->y0 + $yOffset, $box->z0 - 1, $orientation, $this->genDepth, $isCastle)
		};
	}

	/**
	 * @param list<StructurePiece> $pieces
	 */
	protected function generateChildLeft(FortressStartPiece $start, array &$pieces, RandomSource $random, int $yOffset, int $horizontalOffset, bool $isCastle) : ?StructurePiece{
		$orientation = $this->getOrientation();
		if($orientation === null){
			return null;
		}
		$box = $this->boundingBox;
		return match($orientation){
			Facing::WEST, Facing::EAST => $this->generateAndAddPiece($start, $pieces, $random, $box->x0 + $horizontalOffset, $box->y0 + $yOffset, $box->z0 - 1, Facing::NORTH, $this->genDepth, $isCastle),
			default => $this->generateAndAddPiece($start, $pieces, $random, $box->x0 - 1, $box->y0 + $yOffset, $box->z0 + $horizontalOffset, Facing::WEST, $this->genDepth, $isCastle)
		};
	}

	/**
	 * @param list<StructurePiece> $pieces
	 */
	protected function generateChildRight(FortressStartPiece $start, array &$pieces, RandomSource $random, int $yOffset, int $horizontalOffset, bool $isCastle) : ?StructurePiece{
		$orientation = $this->getOrientation();
		if($orientation === null){
			return null;
		}
		$box = $this->boundingBox;
		return match($orientation){
			Facing::WEST, Facing::EAST => $this->generateAndAddPiece($start, $pieces, $random, $box->x0 + $horizontalOffset, $box->y0 + $yOffset, $box->z1 + 1, Facing::SOUTH, $this->genDepth, $isCastle),
			default => $this->generateAndAddPiece($start, $pieces, $random, $box->x1 + 1, $box->y0 + $yOffset, $box->z0 + $horizontalOffset, Facing::EAST, $this->genDepth, $isCastle)
		};
	}
}
