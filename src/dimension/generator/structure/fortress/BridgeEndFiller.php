<?php

declare(strict_types=1);

namespace dimension\generator\structure\fortress;

use dimension\generator\object\BlockManager;
use dimension\generator\random\RandomSource;
use dimension\generator\random\Xoroshiro128;
use dimension\generator\structure\BoundingBox;
use dimension\generator\structure\StructurePiece;

/**
 * Crumbling end of a bridge. Its jagged outline comes from a seed drawn
 * when the piece is laid out, so the piece looks the same in every chunk
 * it crosses.
 */
final class BridgeEndFiller extends FortressPiece{

	private int $selfSeed;

	public function __construct(int $genDepth, RandomSource $random, BoundingBox $boundingBox, int $orientation){
		parent::__construct($genDepth);
		$this->setOrientation($orientation);
		$this->boundingBox = $boundingBox;
		$this->selfSeed = $random->nextInt();
	}

	public static function createPiece(array $pieces, RandomSource $random, int $x, int $y, int $z, int $orientation, int $genDepth) : ?FortressPiece{
		$boundingBox = BoundingBox::orient($x, $y, $z, -1, -3, 0, 5, 10, 8, $orientation);
		return self::isOkBox($boundingBox) && StructurePiece::findCollisionPiece($pieces, $boundingBox) === null ? new BridgeEndFiller($genDepth, $random, $boundingBox, $orientation) : null;
	}

	public function postProcess(BlockManager $level, RandomSource $random, BoundingBox $boundingBox, int $chunkX, int $chunkZ) : bool{
		$rand = new Xoroshiro128($this->selfSeed);
		$bricks = FortressBlocks::netherBricks();

		for($x = 0; $x <= 4; ++$x){
			for($y = 3; $y <= 4; ++$y){
				$this->generateBox($level, $boundingBox, $x, $y, 0, $x, $y, $rand->nextBoundedInt(8), $bricks, $bricks, false);
			}
		}

		$this->generateBox($level, $boundingBox, 0, 5, 0, 0, 5, $rand->nextBoundedInt(8), $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 4, 5, 0, 4, 5, $rand->nextBoundedInt(8), $bricks, $bricks, false);

		for($x = 0; $x <= 4; ++$x){
			$this->generateBox($level, $boundingBox, $x, 2, 0, $x, 2, $rand->nextBoundedInt(5), $bricks, $bricks, false);
		}

		for($x = 0; $x <= 4; ++$x){
			for($y = 0; $y <= 1; ++$y){
				$this->generateBox($level, $boundingBox, $x, $y, 0, $x, $y, $rand->nextBoundedInt(3), $bricks, $bricks, false);
			}
		}
		return true;
	}
}
