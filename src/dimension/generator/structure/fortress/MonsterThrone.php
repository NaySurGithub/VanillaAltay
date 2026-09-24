<?php

declare(strict_types=1);

namespace dimension\generator\structure\fortress;

use dimension\generator\object\BlockManager;
use dimension\generator\random\RandomSource;
use dimension\generator\structure\BoundingBox;
use dimension\generator\structure\StructurePiece;

/**
 * Raised platform holding the blaze spawner.
 */
final class MonsterThrone extends FortressPiece{

	private bool $hasPlacedSpawner = false;

	public function __construct(int $genDepth, BoundingBox $boundingBox, int $orientation){
		parent::__construct($genDepth);
		$this->setOrientation($orientation);
		$this->boundingBox = $boundingBox;
	}

	public static function createPiece(array $pieces, RandomSource $random, int $x, int $y, int $z, int $orientation, int $genDepth) : ?FortressPiece{
		$boundingBox = BoundingBox::orient($x, $y, $z, -2, 0, 0, 7, 8, 9, $orientation);
		return self::isOkBox($boundingBox) && StructurePiece::findCollisionPiece($pieces, $boundingBox) === null ? new MonsterThrone($genDepth, $boundingBox, $orientation) : null;
	}

	/**
	 * World position of the spawner, as [x, y, z].
	 *
	 * @return array{int, int, int}
	 */
	public function getSpawnerPosition() : array{
		return [$this->getWorldX(3, 5), $this->getWorldY(5), $this->getWorldZ(3, 5)];
	}

	public function postProcess(BlockManager $level, RandomSource $random, BoundingBox $boundingBox, int $chunkX, int $chunkZ) : bool{
		$bricks = FortressBlocks::netherBricks();
		$fence = FortressBlocks::netherBrickFence();
		$air = FortressBlocks::air();
		$this->generateBox($level, $boundingBox, 0, 2, 0, 6, 7, 7, $air, $air, false);
		$this->generateBox($level, $boundingBox, 1, 0, 0, 5, 1, 7, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 1, 2, 1, 5, 2, 7, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 1, 3, 2, 5, 3, 7, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 1, 4, 3, 5, 4, 7, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 1, 2, 0, 1, 4, 2, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 5, 2, 0, 5, 4, 2, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 1, 5, 2, 1, 5, 3, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 5, 5, 2, 5, 5, 3, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 5, 3, 0, 5, 8, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 6, 5, 3, 6, 5, 8, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 1, 5, 8, 5, 5, 8, $bricks, $bricks, false);
		$this->placeBlock($level, $fence, 1, 6, 3, $boundingBox);
		$this->placeBlock($level, $fence, 5, 6, 3, $boundingBox);
		$this->placeBlock($level, $fence, 0, 6, 3, $boundingBox);
		$this->placeBlock($level, $fence, 6, 6, 3, $boundingBox);
		$this->generateBox($level, $boundingBox, 0, 6, 4, 0, 6, 7, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 6, 6, 4, 6, 6, 7, $fence, $fence, false);
		$this->placeBlock($level, $fence, 0, 6, 8, $boundingBox);
		$this->placeBlock($level, $fence, 6, 6, 8, $boundingBox);
		$this->generateBox($level, $boundingBox, 1, 6, 8, 5, 6, 8, $fence, $fence, false);
		$this->placeBlock($level, $fence, 1, 7, 8, $boundingBox);
		$this->generateBox($level, $boundingBox, 2, 7, 8, 4, 7, 8, $fence, $fence, false);
		$this->placeBlock($level, $fence, 5, 7, 8, $boundingBox);
		$this->placeBlock($level, $fence, 2, 8, 8, $boundingBox);
		$this->placeBlock($level, $fence, 3, 8, 8, $boundingBox);
		$this->placeBlock($level, $fence, 4, 8, 8, $boundingBox);

		if(!$this->hasPlacedSpawner){
			[$x, $y, $z] = $this->getSpawnerPosition();
			if($boundingBox->isInside($x, $y, $z)){
				$this->hasPlacedSpawner = true;
				$this->placeBlock($level, FortressBlocks::spawner(), 3, 5, 5, $boundingBox);
			}
		}

		for($x = 0; $x <= 6; ++$x){
			for($z = 0; $z <= 6; ++$z){
				$this->fillColumnDown($level, $bricks, $x, -1, $z, $boundingBox);
			}
		}
		return true;
	}
}
