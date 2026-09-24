<?php

declare(strict_types=1);

namespace dimension\generator\structure\fortress;

use dimension\generator\object\BlockManager;
use dimension\generator\random\RandomSource;
use dimension\generator\structure\BoundingBox;
use dimension\generator\structure\StructurePiece;

/**
 * Straight roofed castle corridor.
 */
final class CastleSmallCorridor extends FortressPiece{

	public function __construct(int $genDepth, BoundingBox $boundingBox, int $orientation){
		parent::__construct($genDepth);
		$this->setOrientation($orientation);
		$this->boundingBox = $boundingBox;
	}

	public static function createPiece(array $pieces, RandomSource $random, int $x, int $y, int $z, int $orientation, int $genDepth) : ?FortressPiece{
		$boundingBox = BoundingBox::orient($x, $y, $z, -1, 0, 0, 5, 7, 5, $orientation);
		return self::isOkBox($boundingBox) && StructurePiece::findCollisionPiece($pieces, $boundingBox) === null ? new CastleSmallCorridor($genDepth, $boundingBox, $orientation) : null;
	}

	public function addChildren(FortressStartPiece $start, array &$pieces, RandomSource $random) : void{
		$this->generateChildForward($start, $pieces, $random, 1, 0, true);
	}

	public function postProcess(BlockManager $level, RandomSource $random, BoundingBox $boundingBox, int $chunkX, int $chunkZ) : bool{
		$bricks = FortressBlocks::netherBricks();
		$fence = FortressBlocks::netherBrickFence();
		$air = FortressBlocks::air();
		$this->generateBox($level, $boundingBox, 0, 0, 0, 4, 1, 4, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 2, 0, 4, 5, 4, $air, $air, false);
		$this->generateBox($level, $boundingBox, 0, 2, 0, 0, 5, 4, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 4, 2, 0, 4, 5, 4, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 3, 1, 0, 4, 1, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 0, 3, 3, 0, 4, 3, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 4, 3, 1, 4, 4, 1, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 4, 3, 3, 4, 4, 3, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 0, 6, 0, 4, 6, 4, $bricks, $bricks, false);

		for($x = 0; $x <= 4; ++$x){
			for($z = 0; $z <= 4; ++$z){
				$this->fillColumnDown($level, $bricks, $x, -1, $z, $boundingBox);
			}
		}
		return true;
	}
}
