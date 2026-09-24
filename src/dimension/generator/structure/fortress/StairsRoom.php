<?php

declare(strict_types=1);

namespace dimension\generator\structure\fortress;

use dimension\generator\object\BlockManager;
use dimension\generator\random\RandomSource;
use dimension\generator\structure\BoundingBox;
use dimension\generator\structure\StructurePiece;

/**
 * Room with a staircase climbing to an upper exit on its right side.
 */
final class StairsRoom extends FortressPiece{

	public function __construct(int $genDepth, BoundingBox $boundingBox, int $orientation){
		parent::__construct($genDepth);
		$this->setOrientation($orientation);
		$this->boundingBox = $boundingBox;
	}

	public static function createPiece(array $pieces, RandomSource $random, int $x, int $y, int $z, int $orientation, int $genDepth) : ?FortressPiece{
		$boundingBox = BoundingBox::orient($x, $y, $z, -2, 0, 0, 7, 11, 7, $orientation);
		return self::isOkBox($boundingBox) && StructurePiece::findCollisionPiece($pieces, $boundingBox) === null ? new StairsRoom($genDepth, $boundingBox, $orientation) : null;
	}

	public function addChildren(FortressStartPiece $start, array &$pieces, RandomSource $random) : void{
		$this->generateChildRight($start, $pieces, $random, 6, 2, false);
	}

	public function postProcess(BlockManager $level, RandomSource $random, BoundingBox $boundingBox, int $chunkX, int $chunkZ) : bool{
		$bricks = FortressBlocks::netherBricks();
		$fence = FortressBlocks::netherBrickFence();
		$air = FortressBlocks::air();
		$this->generateBox($level, $boundingBox, 0, 0, 0, 6, 1, 6, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 2, 0, 6, 10, 6, $air, $air, false);
		$this->generateBox($level, $boundingBox, 0, 2, 0, 1, 8, 0, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 5, 2, 0, 6, 8, 0, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 2, 1, 0, 8, 6, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 6, 2, 1, 6, 8, 6, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 1, 2, 6, 5, 8, 6, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 3, 2, 0, 5, 4, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 6, 3, 2, 6, 5, 2, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 6, 3, 4, 6, 5, 4, $fence, $fence, false);
		$this->placeBlock($level, $bricks, 5, 2, 5, $boundingBox);
		$this->generateBox($level, $boundingBox, 4, 2, 5, 4, 3, 5, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 3, 2, 5, 3, 4, 5, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 2, 2, 5, 2, 5, 5, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 1, 2, 5, 1, 6, 5, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 1, 7, 1, 5, 7, 4, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 6, 8, 2, 6, 8, 4, $air, $air, false);
		$this->generateBox($level, $boundingBox, 2, 6, 0, 4, 8, 0, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 2, 5, 0, 4, 5, 0, $fence, $fence, false);

		for($x = 0; $x <= 6; ++$x){
			for($z = 0; $z <= 6; ++$z){
				$this->fillColumnDown($level, $bricks, $x, -1, $z, $boundingBox);
			}
		}
		return true;
	}
}
