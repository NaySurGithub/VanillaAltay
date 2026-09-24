<?php

declare(strict_types=1);

namespace dimension\generator\structure\fortress;

use dimension\generator\random\RandomSource;
use dimension\generator\structure\BoundingBox;
use dimension\generator\structure\StructurePiece;
use pocketmine\math\Facing;
use function array_search;
use function array_splice;

/**
 * The bridge crossing a fortress grows from. It also holds the layout
 * state: pieces waiting to grow their children, the piece weights still
 * available and the last piece type placed.
 */
final class FortressStartPiece extends BridgeCrossing{

	private const HORIZONTAL = [Facing::NORTH, Facing::EAST, Facing::SOUTH, Facing::WEST];

	/** @var list<StructurePiece> */
	public array $pendingChildren = [];
	public ?FortressPieceWeight $previousPiece = null;
	/** @var list<FortressPieceWeight> */
	public array $availableBridgePieces;
	/** @var list<FortressPieceWeight> */
	public array $availableCastlePieces;

	public function __construct(RandomSource $random, int $x, int $z){
		$orientation = self::HORIZONTAL[$random->nextInt(4)];
		parent::__construct(0, new BoundingBox($x, 64, $z, $x + 19 - 1, 73, $z + 19 - 1), $orientation);
		$this->availableBridgePieces = FortressPieceWeight::bridgePieces();
		$this->availableCastlePieces = FortressPieceWeight::castlePieces();
	}

	public function removeWeight(FortressPieceWeight $weight, bool $isCastle) : void{
		if($isCastle){
			$index = array_search($weight, $this->availableCastlePieces, true);
			if($index !== false){
				array_splice($this->availableCastlePieces, $index, 1);
			}
		}else{
			$index = array_search($weight, $this->availableBridgePieces, true);
			if($index !== false){
				array_splice($this->availableBridgePieces, $index, 1);
			}
		}
	}
}
