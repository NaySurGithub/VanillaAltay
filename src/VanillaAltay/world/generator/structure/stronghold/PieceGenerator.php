<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\stronghold;

use pocketmine\utils\Random;
use function abs;
use function array_splice;
use function array_values;
use function count;

final class PieceGenerator{

	/** @var StrongholdPiece[] */
	public array $pieces = [];
	/** @var StrongholdPiece[] */
	public array $pendingChildren = [];

	public ?PortalRoom $portalRoom = null;

	private StrongholdPiece $start;

	/** @var PieceWeight[] */
	private array $currentPieces;
	private int $totalWeight = 0;
	/** @phpstan-var class-string<StrongholdPiece>|null */
	private ?string $imposedPiece = null;
	private ?PieceWeight $previousPiece = null;

	public function __construct(){
		$this->currentPieces = [
			new PieceWeight(Straight::class, 40, 0),
			new PieceWeight(PrisonHall::class, 5, 5),
			new PieceWeight(LeftTurn::class, 20, 0),
			new PieceWeight(RightTurn::class, 20, 0),
			new PieceWeight(RoomCrossing::class, 10, 6),
			new PieceWeight(StraightStairsDown::class, 5, 5),
			new PieceWeight(StairsDown::class, 5, 5),
			new PieceWeight(FiveCrossing::class, 5, 4),
			new PieceWeight(ChestCorridor::class, 5, 4),
			new PieceWeight(Library::class, 10, 2, 4),
			new PieceWeight(PortalRoom::class, 20, 1, 5)
		];
	}

	public function setStart(StrongholdPiece $start) : void{
		$this->start = $start;
		$this->pieces[] = $start;
	}

	/**
	 * @phpstan-param class-string<StrongholdPiece> $pieceClass
	 */
	public function impose(string $pieceClass) : void{
		$this->imposedPiece = $pieceClass;
	}

	public function generateAndAddPiece(Random $random, int $x, int $y, int $z, ?int $orientation, int $genDepth) : ?StrongholdPiece{
		if($genDepth > StrongholdPiece::MAX_DEPTH){
			return null;
		}
		if(abs($x - $this->start->boundingBox->x0) > StrongholdPiece::MAX_SPREAD || abs($z - $this->start->boundingBox->z0) > StrongholdPiece::MAX_SPREAD){
			return null;
		}

		$piece = $this->generatePieceFromSmallDoor($random, $x, $y, $z, $orientation, $genDepth + 1);
		if($piece !== null){
			$this->pieces[] = $piece;
			$this->pendingChildren[] = $piece;
		}

		return $piece;
	}

	public function drainPendingChildren(Random $random) : void{
		while(count($this->pendingChildren) > 0){
			$index = $random->nextBoundedInt(count($this->pendingChildren));
			$child = $this->pendingChildren[$index];
			array_splice($this->pendingChildren, $index, 1);
			$child->addChildren($this, $random);
		}
	}

	private function generatePieceFromSmallDoor(Random $random, int $x, int $y, int $z, ?int $orientation, int $genDepth) : ?StrongholdPiece{
		if(!$this->updatePieceWeight()){
			return null;
		}

		if($this->imposedPiece !== null){
			$imposed = $this->imposedPiece;
			$this->imposedPiece = null;
			$piece = $imposed::createPiece($this->pieces, $random, $x, $y, $z, $orientation, $genDepth);
			if($piece !== null){
				return $piece;
			}
		}

		for($attempt = 0; $attempt < 5; ++$attempt){
			$target = $random->nextBoundedInt($this->totalWeight);

			foreach($this->currentPieces as $index => $weight){
				$target -= $weight->weight;
				if($target >= 0){
					continue;
				}

				if(!$weight->doPlace($genDepth) || $weight === $this->previousPiece){
					break;
				}

				$class = $weight->pieceClass;
				$piece = $class::createPiece($this->pieces, $random, $x, $y, $z, $orientation, $genDepth);
				if($piece !== null){
					++$weight->placeCount;
					$this->previousPiece = $weight;
					if(!$weight->isValid()){
						unset($this->currentPieces[$index]);
						$this->currentPieces = array_values($this->currentPieces);
					}

					return $piece;
				}
			}
		}

		$box = FillerCorridor::findPieceBox($this->pieces, $x, $y, $z, $orientation);

		return $box !== null && $box->y0 > 1 ? new FillerCorridor($genDepth, $box, $orientation) : null;
	}

	private function updatePieceWeight() : bool{
		$success = false;
		$this->totalWeight = 0;

		foreach($this->currentPieces as $weight){
			if($weight->maxPlaceCount > 0 && $weight->placeCount < $weight->maxPlaceCount){
				$success = true;
			}
			$this->totalWeight += $weight->weight;
		}

		return $success;
	}
}
