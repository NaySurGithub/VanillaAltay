<?php

declare(strict_types=1);

namespace dimension\generator\structure\fortress;

use dimension\generator\object\BlockManager;
use dimension\generator\random\RandomSource;
use dimension\generator\structure\BoundingBox;
use dimension\generator\structure\StructurePiece;

/**
 * Small walled room with three exits.
 */
final class RoomCrossing extends FortressPiece{

	public function __construct(int $genDepth, BoundingBox $boundingBox, int $orientation){
		parent::__construct($genDepth);
		$this->setOrientation($orientation);
		$this->boundingBox = $boundingBox;
	}

	public static function createPiece(array $pieces, RandomSource $random, int $x, int $y, int $z, int $orientation, int $genDepth) : ?FortressPiece{
		$boundingBox = BoundingBox::orient($x, $y, $z, -2, 0, 0, 7, 9, 7, $orientation);
		return self::isOkBox($boundingBox) && StructurePiece::findCollisionPiece($pieces, $boundingBox) === null ? new RoomCrossing($genDepth, $boundingBox, $orientation) : null;
	}

	public function addChildren(FortressStartPiece $start, array &$pieces, RandomSource $random) : void{
		$this->generateChildForward($start, $pieces, $random, 2, 0, false);
		$this->generateChildLeft($start, $pieces, $random, 0, 2, false);
		$this->generateChildRight($start, $pieces, $random, 0, 2, false);
	}

	public function postProcess(BlockManager $level, RandomSource $random, BoundingBox $boundingBox, int $chunkX, int $chunkZ) : bool{
		$bricks = FortressBlocks::netherBricks();
		$fence = FortressBlocks::netherBrickFence();
		$air = FortressBlocks::air();
		$this->generateBox($level, $boundingBox, 0, 0, 0, 6, 1, 6, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 2, 0, 6, 7, 6, $air, $air, false);
		$this->generateBox($level, $boundingBox, 0, 2, 0, 1, 6, 0, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 2, 6, 1, 6, 6, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 5, 2, 0, 6, 6, 0, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 5, 2, 6, 6, 6, 6, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 2, 0, 0, 6, 1, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 2, 5, 0, 6, 6, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 6, 2, 0, 6, 6, 1, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 6, 2, 5, 6, 6, 6, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 2, 6, 0, 4, 6, 0, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 2, 5, 0, 4, 5, 0, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 2, 6, 6, 4, 6, 6, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 2, 5, 6, 4, 5, 6, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 0, 6, 2, 0, 6, 4, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 5, 2, 0, 5, 4, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 6, 6, 2, 6, 6, 4, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 6, 5, 2, 6, 5, 4, $fence, $fence, false);

		for($x = 0; $x <= 6; ++$x){
			for($z = 0; $z <= 6; ++$z){
				$this->fillColumnDown($level, $bricks, $x, -1, $z, $boundingBox);
			}
		}
		return true;
	}
}
