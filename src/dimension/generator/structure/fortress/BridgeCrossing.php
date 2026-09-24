<?php

declare(strict_types=1);

namespace dimension\generator\structure\fortress;

use dimension\generator\object\BlockManager;
use dimension\generator\random\RandomSource;
use dimension\generator\structure\BoundingBox;
use dimension\generator\structure\StructurePiece;

/**
 * Crossing of two bridges, 19 by 19, open on all four sides.
 */
class BridgeCrossing extends FortressPiece{

	public function __construct(int $genDepth, BoundingBox $boundingBox, int $orientation){
		parent::__construct($genDepth);
		$this->setOrientation($orientation);
		$this->boundingBox = $boundingBox;
	}

	public static function createPiece(array $pieces, RandomSource $random, int $x, int $y, int $z, int $orientation, int $genDepth) : ?FortressPiece{
		$boundingBox = BoundingBox::orient($x, $y, $z, -8, -3, 0, 19, 10, 19, $orientation);
		return self::isOkBox($boundingBox) && StructurePiece::findCollisionPiece($pieces, $boundingBox) === null ? new BridgeCrossing($genDepth, $boundingBox, $orientation) : null;
	}

	public function addChildren(FortressStartPiece $start, array &$pieces, RandomSource $random) : void{
		$this->generateChildForward($start, $pieces, $random, 8, 3, false);
		$this->generateChildLeft($start, $pieces, $random, 3, 8, false);
		$this->generateChildRight($start, $pieces, $random, 3, 8, false);
	}

	public function postProcess(BlockManager $level, RandomSource $random, BoundingBox $boundingBox, int $chunkX, int $chunkZ) : bool{
		$bricks = FortressBlocks::netherBricks();
		$air = FortressBlocks::air();
		$this->generateBox($level, $boundingBox, 7, 3, 0, 11, 4, 18, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 3, 7, 18, 4, 11, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 8, 5, 0, 10, 7, 18, $air, $air, false);
		$this->generateBox($level, $boundingBox, 0, 5, 8, 18, 7, 10, $air, $air, false);
		$this->generateBox($level, $boundingBox, 7, 5, 0, 7, 5, 7, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 7, 5, 11, 7, 5, 18, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 11, 5, 0, 11, 5, 7, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 11, 5, 11, 11, 5, 18, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 5, 7, 7, 5, 7, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 11, 5, 7, 18, 5, 7, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 5, 11, 7, 5, 11, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 11, 5, 11, 18, 5, 11, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 7, 2, 0, 11, 2, 5, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 7, 2, 13, 11, 2, 18, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 7, 0, 0, 11, 1, 3, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 7, 0, 15, 11, 1, 18, $bricks, $bricks, false);

		for($x = 7; $x <= 11; ++$x){
			for($z = 0; $z <= 2; ++$z){
				$this->fillColumnDown($level, $bricks, $x, -1, $z, $boundingBox);
				$this->fillColumnDown($level, $bricks, $x, -1, 18 - $z, $boundingBox);
			}
		}

		$this->generateBox($level, $boundingBox, 0, 2, 7, 5, 2, 11, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 13, 2, 7, 18, 2, 11, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 0, 7, 3, 1, 11, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 15, 0, 7, 18, 1, 11, $bricks, $bricks, false);

		for($x = 0; $x <= 2; ++$x){
			for($z = 7; $z <= 11; ++$z){
				$this->fillColumnDown($level, $bricks, $x, -1, $z, $boundingBox);
				$this->fillColumnDown($level, $bricks, 18 - $x, -1, $z, $boundingBox);
			}
		}
		return true;
	}
}
