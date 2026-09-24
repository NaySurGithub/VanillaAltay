<?php

declare(strict_types=1);

namespace dimension\generator\structure\fortress;

use dimension\generator\object\BlockManager;
use dimension\generator\random\RandomSource;
use dimension\generator\structure\BoundingBox;
use dimension\generator\structure\StructurePiece;

/**
 * Walled courtyard leading from the bridges into the castle, with a lava
 * well in its middle.
 */
final class CastleEntrance extends FortressPiece{

	public function __construct(int $genDepth, BoundingBox $boundingBox, int $orientation){
		parent::__construct($genDepth);
		$this->setOrientation($orientation);
		$this->boundingBox = $boundingBox;
	}

	public static function createPiece(array $pieces, RandomSource $random, int $x, int $y, int $z, int $orientation, int $genDepth) : ?FortressPiece{
		$boundingBox = BoundingBox::orient($x, $y, $z, -5, -3, 0, 13, 14, 13, $orientation);
		return self::isOkBox($boundingBox) && StructurePiece::findCollisionPiece($pieces, $boundingBox) === null ? new CastleEntrance($genDepth, $boundingBox, $orientation) : null;
	}

	public function addChildren(FortressStartPiece $start, array &$pieces, RandomSource $random) : void{
		$this->generateChildForward($start, $pieces, $random, 5, 3, true);
	}

	public function postProcess(BlockManager $level, RandomSource $random, BoundingBox $boundingBox, int $chunkX, int $chunkZ) : bool{
		$bricks = FortressBlocks::netherBricks();
		$fence = FortressBlocks::netherBrickFence();
		$air = FortressBlocks::air();
		$this->generateBox($level, $boundingBox, 0, 3, 0, 12, 4, 12, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 5, 0, 12, 13, 12, $air, $air, false);
		$this->generateBox($level, $boundingBox, 0, 5, 0, 1, 12, 12, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 11, 5, 0, 12, 12, 12, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 2, 5, 11, 4, 12, 12, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 8, 5, 11, 10, 12, 12, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 5, 9, 11, 7, 12, 12, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 2, 5, 0, 4, 12, 1, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 8, 5, 0, 10, 12, 1, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 5, 9, 0, 7, 12, 1, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 2, 11, 2, 10, 12, 10, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 5, 8, 0, 7, 8, 0, $fence, $fence, false);

		for($i = 1; $i <= 11; $i += 2){
			$this->generateBox($level, $boundingBox, $i, 10, 0, $i, 11, 0, $fence, $fence, false);
			$this->generateBox($level, $boundingBox, $i, 10, 12, $i, 11, 12, $fence, $fence, false);
			$this->generateBox($level, $boundingBox, 0, 10, $i, 0, 11, $i, $fence, $fence, false);
			$this->generateBox($level, $boundingBox, 12, 10, $i, 12, 11, $i, $fence, $fence, false);
			$this->placeBlock($level, $bricks, $i, 13, 0, $boundingBox);
			$this->placeBlock($level, $bricks, $i, 13, 12, $boundingBox);
			$this->placeBlock($level, $bricks, 0, 13, $i, $boundingBox);
			$this->placeBlock($level, $bricks, 12, 13, $i, $boundingBox);

			if($i !== 11){
				$this->placeBlock($level, $fence, $i + 1, 13, 0, $boundingBox);
				$this->placeBlock($level, $fence, $i + 1, 13, 12, $boundingBox);
				$this->placeBlock($level, $fence, 0, 13, $i + 1, $boundingBox);
				$this->placeBlock($level, $fence, 12, 13, $i + 1, $boundingBox);
			}
		}

		$this->placeBlock($level, $fence, 0, 13, 0, $boundingBox);
		$this->placeBlock($level, $fence, 0, 13, 12, $boundingBox);
		$this->placeBlock($level, $fence, 12, 13, 12, $boundingBox);
		$this->placeBlock($level, $fence, 12, 13, 0, $boundingBox);

		for($z = 3; $z <= 9; $z += 2){
			$this->generateBox($level, $boundingBox, 1, 7, $z, 1, 8, $z, $fence, $fence, false);
			$this->generateBox($level, $boundingBox, 11, 7, $z, 11, 8, $z, $fence, $fence, false);
		}

		$this->generateBox($level, $boundingBox, 4, 2, 0, 8, 2, 12, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 2, 4, 12, 2, 8, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 4, 0, 0, 8, 1, 3, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 4, 0, 9, 8, 1, 12, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 0, 4, 3, 1, 8, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 9, 0, 4, 12, 1, 8, $bricks, $bricks, false);

		for($x = 4; $x <= 8; ++$x){
			for($z = 0; $z <= 2; ++$z){
				$this->fillColumnDown($level, $bricks, $x, -1, $z, $boundingBox);
				$this->fillColumnDown($level, $bricks, $x, -1, 12 - $z, $boundingBox);
			}
		}

		for($x = 0; $x <= 2; ++$x){
			for($z = 4; $z <= 8; ++$z){
				$this->fillColumnDown($level, $bricks, $x, -1, $z, $boundingBox);
				$this->fillColumnDown($level, $bricks, 12 - $x, -1, $z, $boundingBox);
			}
		}

		$this->generateBox($level, $boundingBox, 5, 5, 5, 7, 5, 7, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 6, 1, 6, 6, 4, 6, $air, $air, false);
		$this->placeBlock($level, $bricks, 6, 0, 6, $boundingBox);
		$this->placeBlock($level, FortressBlocks::lava(), 6, 5, 6, $boundingBox);

		$x = $this->getWorldX(6, 6);
		$y = $this->getWorldY(5);
		$z = $this->getWorldZ(6, 6);
		if($boundingBox->isInside($x, $y, $z)){
			$this->pourLava($level, $x, $y - 1, $z);
		}
		return true;
	}

	/**
	 * Fills the air below the lava source with falling lava, as the source
	 * would once it flows.
	 */
	private function pourLava(BlockManager $level, int $x, int $y, int $z) : void{
		$falling = $this->orientedBlock(FortressBlocks::fallingLava());
		if($falling === null){
			return;
		}
		while($y > $level->getMinHeight() && $level->getBlockIdAt($x, $y, $z) === self::AIR){
			$level->setBlockStateAt($x, $y, $z, $falling);
			--$y;
		}
	}
}
