<?php

declare(strict_types=1);

namespace dimension\generator\structure\fortress;

use dimension\generator\object\BlockManager;
use dimension\generator\random\RandomSource;
use dimension\generator\structure\BoundingBox;
use dimension\generator\structure\StructurePiece;

/**
 * Castle corridor turning right, holding a chest one time in three.
 */
final class CastleSmallCorridorRightTurn extends FortressPiece{

	private bool $isNeedingChest;
	private bool $hasChest;

	public function __construct(int $genDepth, RandomSource $random, BoundingBox $boundingBox, int $orientation){
		parent::__construct($genDepth);
		$this->setOrientation($orientation);
		$this->boundingBox = $boundingBox;
		$this->isNeedingChest = $random->nextBoundedInt(3) === 0;
		$this->hasChest = $this->isNeedingChest;
	}

	public static function createPiece(array $pieces, RandomSource $random, int $x, int $y, int $z, int $orientation, int $genDepth) : ?FortressPiece{
		$boundingBox = BoundingBox::orient($x, $y, $z, -1, 0, 0, 5, 7, 5, $orientation);
		return self::isOkBox($boundingBox) && StructurePiece::findCollisionPiece($pieces, $boundingBox) === null ? new CastleSmallCorridorRightTurn($genDepth, $random, $boundingBox, $orientation) : null;
	}

	/**
	 * World position of the chest as [x, y, z], null when the piece has none.
	 *
	 * @return array{int, int, int}|null
	 */
	public function getChestPosition() : ?array{
		return $this->hasChest ? [$this->getWorldX(1, 3), $this->getWorldY(2), $this->getWorldZ(1, 3)] : null;
	}

	public function addChildren(FortressStartPiece $start, array &$pieces, RandomSource $random) : void{
		$this->generateChildRight($start, $pieces, $random, 0, 1, true);
	}

	public function postProcess(BlockManager $level, RandomSource $random, BoundingBox $boundingBox, int $chunkX, int $chunkZ) : bool{
		$bricks = FortressBlocks::netherBricks();
		$fence = FortressBlocks::netherBrickFence();
		$air = FortressBlocks::air();
		$this->generateBox($level, $boundingBox, 0, 0, 0, 4, 1, 4, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 2, 0, 4, 5, 4, $air, $air, false);
		$this->generateBox($level, $boundingBox, 0, 2, 0, 0, 5, 4, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 3, 1, 0, 4, 1, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 0, 3, 3, 0, 4, 3, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 4, 2, 0, 4, 5, 0, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 1, 2, 4, 4, 5, 4, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 1, 3, 4, 1, 4, 4, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 3, 3, 4, 3, 4, 4, $fence, $fence, false);

		if($this->isNeedingChest && $boundingBox->isInside($this->getWorldX(1, 3), $this->getWorldY(2), $this->getWorldZ(1, 3))){
			$this->isNeedingChest = false;
			$this->placeBlock($level, FortressBlocks::chest("east"), 1, 2, 3, $boundingBox);
		}

		$this->generateBox($level, $boundingBox, 0, 6, 0, 4, 6, 4, $bricks, $bricks, false);

		for($x = 0; $x <= 4; ++$x){
			for($z = 0; $z <= 4; ++$z){
				$this->fillColumnDown($level, $bricks, $x, -1, $z, $boundingBox);
			}
		}
		return true;
	}
}
