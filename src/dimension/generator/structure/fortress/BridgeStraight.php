<?php

declare(strict_types=1);

namespace dimension\generator\structure\fortress;

use dimension\generator\object\BlockManager;
use dimension\generator\random\RandomSource;
use dimension\generator\structure\BoundingBox;
use dimension\generator\structure\StructurePiece;

/**
 * Straight bridge segment, 19 blocks long, with fence railings and support
 * pillars at both ends.
 */
final class BridgeStraight extends FortressPiece{

	public function __construct(int $genDepth, BoundingBox $boundingBox, int $orientation){
		parent::__construct($genDepth);
		$this->setOrientation($orientation);
		$this->boundingBox = $boundingBox;
	}

	public static function createPiece(array $pieces, RandomSource $random, int $x, int $y, int $z, int $orientation, int $genDepth) : ?FortressPiece{
		$boundingBox = BoundingBox::orient($x, $y, $z, -1, -3, 0, 5, 10, 19, $orientation);
		return self::isOkBox($boundingBox) && StructurePiece::findCollisionPiece($pieces, $boundingBox) === null ? new BridgeStraight($genDepth, $boundingBox, $orientation) : null;
	}

	public function addChildren(FortressStartPiece $start, array &$pieces, RandomSource $random) : void{
		$this->generateChildForward($start, $pieces, $random, 1, 3, false);
	}

	public function postProcess(BlockManager $level, RandomSource $random, BoundingBox $boundingBox, int $chunkX, int $chunkZ) : bool{
		$bricks = FortressBlocks::netherBricks();
		$fence = FortressBlocks::netherBrickFence();
		$air = FortressBlocks::air();
		$this->generateBox($level, $boundingBox, 0, 3, 0, 4, 4, 18, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 1, 5, 0, 3, 7, 18, $air, $air, false);
		$this->generateBox($level, $boundingBox, 0, 5, 0, 0, 5, 18, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 4, 5, 0, 4, 5, 18, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 2, 0, 4, 2, 5, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 2, 13, 4, 2, 18, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 0, 0, 4, 1, 3, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 0, 15, 4, 1, 18, $bricks, $bricks, false);

		for($x = 0; $x <= 4; ++$x){
			for($z = 0; $z <= 2; ++$z){
				$this->fillColumnDown($level, $bricks, $x, -1, $z, $boundingBox);
				$this->fillColumnDown($level, $bricks, $x, -1, 18 - $z, $boundingBox);
			}
		}

		$this->generateBox($level, $boundingBox, 0, 1, 1, 0, 4, 1, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 0, 3, 4, 0, 4, 4, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 0, 3, 14, 0, 4, 14, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 0, 1, 17, 0, 4, 17, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 4, 1, 1, 4, 4, 1, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 4, 3, 4, 4, 4, 4, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 4, 3, 14, 4, 4, 14, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 4, 1, 17, 4, 4, 17, $fence, $fence, false);
		return true;
	}
}
