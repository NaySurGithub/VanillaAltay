<?php

declare(strict_types=1);

namespace dimension\generator\structure\fortress;

use dimension\generator\object\BlockManager;
use dimension\generator\random\RandomSource;
use dimension\generator\structure\BoundingBox;
use dimension\generator\structure\StructurePiece;
use function max;
use function min;

/**
 * Castle corridor stepping down seven blocks.
 */
final class CastleCorridorStairs extends FortressPiece{

	public function __construct(int $genDepth, BoundingBox $boundingBox, int $orientation){
		parent::__construct($genDepth);
		$this->setOrientation($orientation);
		$this->boundingBox = $boundingBox;
	}

	public static function createPiece(array $pieces, RandomSource $random, int $x, int $y, int $z, int $orientation, int $genDepth) : ?FortressPiece{
		$boundingBox = BoundingBox::orient($x, $y, $z, -1, -7, 0, 5, 14, 10, $orientation);
		return self::isOkBox($boundingBox) && StructurePiece::findCollisionPiece($pieces, $boundingBox) === null ? new CastleCorridorStairs($genDepth, $boundingBox, $orientation) : null;
	}

	public function addChildren(FortressStartPiece $start, array &$pieces, RandomSource $random) : void{
		$this->generateChildForward($start, $pieces, $random, 1, 0, true);
	}

	public function postProcess(BlockManager $level, RandomSource $random, BoundingBox $boundingBox, int $chunkX, int $chunkZ) : bool{
		$bricks = FortressBlocks::netherBricks();
		$fence = FortressBlocks::netherBrickFence();
		$air = FortressBlocks::air();
		$stairsSouth = FortressBlocks::netherBrickStairs(2);
		for($i = 0; $i <= 9; ++$i){
			$maxY = max(1, 7 - $i);
			$minY = min(max($maxY + 5, 14 - $i), 13);

			$this->generateBox($level, $boundingBox, 0, 0, $i, 4, $maxY, $i, $bricks, $bricks, false);
			$this->generateBox($level, $boundingBox, 1, $maxY + 1, $i, 3, $minY - 1, $i, $air, $air, false);

			if($i <= 6){
				$this->placeBlock($level, $stairsSouth, 1, $maxY + 1, $i, $boundingBox);
				$this->placeBlock($level, $stairsSouth, 2, $maxY + 1, $i, $boundingBox);
				$this->placeBlock($level, $stairsSouth, 3, $maxY + 1, $i, $boundingBox);
			}

			$this->generateBox($level, $boundingBox, 0, $minY, $i, 4, $minY, $i, $bricks, $bricks, false);
			$this->generateBox($level, $boundingBox, 0, $maxY + 1, $i, 0, $minY - 1, $i, $bricks, $bricks, false);
			$this->generateBox($level, $boundingBox, 4, $maxY + 1, $i, 4, $minY - 1, $i, $bricks, $bricks, false);

			if(($i & 0x1) === 0){
				$this->generateBox($level, $boundingBox, 0, $maxY + 2, $i, 0, $maxY + 3, $i, $fence, $fence, false);
				$this->generateBox($level, $boundingBox, 4, $maxY + 2, $i, 4, $maxY + 3, $i, $fence, $fence, false);
			}

			for($x = 0; $x <= 4; ++$x){
				$this->fillColumnDown($level, $bricks, $x, -1, $i, $boundingBox);
			}
		}
		return true;
	}
}
