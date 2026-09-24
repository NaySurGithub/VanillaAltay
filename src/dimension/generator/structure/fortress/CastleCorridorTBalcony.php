<?php

declare(strict_types=1);

namespace dimension\generator\structure\fortress;

use dimension\generator\object\BlockManager;
use dimension\generator\random\RandomSource;
use dimension\generator\structure\BoundingBox;
use dimension\generator\structure\StructurePiece;
use pocketmine\math\Facing;

/**
 * T-shaped castle corridor with a balcony looking outwards.
 */
final class CastleCorridorTBalcony extends FortressPiece{

	public function __construct(int $genDepth, BoundingBox $boundingBox, int $orientation){
		parent::__construct($genDepth);
		$this->setOrientation($orientation);
		$this->boundingBox = $boundingBox;
	}

	public static function createPiece(array $pieces, RandomSource $random, int $x, int $y, int $z, int $orientation, int $genDepth) : ?FortressPiece{
		$boundingBox = BoundingBox::orient($x, $y, $z, -3, 0, 0, 9, 7, 9, $orientation);
		return self::isOkBox($boundingBox) && StructurePiece::findCollisionPiece($pieces, $boundingBox) === null ? new CastleCorridorTBalcony($genDepth, $boundingBox, $orientation) : null;
	}

	public function addChildren(FortressStartPiece $start, array &$pieces, RandomSource $random) : void{
		$horizontalOffset = 1;
		$orientation = $this->getOrientation();
		if($orientation === Facing::WEST || $orientation === Facing::NORTH){
			$horizontalOffset = 5;
		}
		$this->generateChildLeft($start, $pieces, $random, 0, $horizontalOffset, $random->nextBoundedInt(8) > 0);
		$this->generateChildRight($start, $pieces, $random, 0, $horizontalOffset, $random->nextBoundedInt(8) > 0);
	}

	public function postProcess(BlockManager $level, RandomSource $random, BoundingBox $boundingBox, int $chunkX, int $chunkZ) : bool{
		$bricks = FortressBlocks::netherBricks();
		$fence = FortressBlocks::netherBrickFence();
		$air = FortressBlocks::air();
		$this->generateBox($level, $boundingBox, 0, 0, 0, 8, 1, 8, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 2, 0, 8, 5, 8, $air, $air, false);
		$this->generateBox($level, $boundingBox, 0, 6, 0, 8, 6, 5, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 0, 2, 0, 2, 5, 0, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 6, 2, 0, 8, 5, 0, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 1, 3, 0, 1, 4, 0, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 7, 3, 0, 7, 4, 0, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 0, 2, 4, 8, 2, 8, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 1, 1, 4, 2, 2, 4, $air, $air, false);
		$this->generateBox($level, $boundingBox, 6, 1, 4, 7, 2, 4, $air, $air, false);
		$this->generateBox($level, $boundingBox, 1, 3, 8, 7, 3, 8, $fence, $fence, false);
		$this->placeBlock($level, $fence, 0, 3, 8, $boundingBox);
		$this->placeBlock($level, $fence, 8, 3, 8, $boundingBox);
		$this->generateBox($level, $boundingBox, 0, 3, 6, 0, 3, 7, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 8, 3, 6, 8, 3, 7, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 0, 3, 4, 0, 5, 5, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 8, 3, 4, 8, 5, 5, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 1, 3, 5, 2, 5, 5, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 6, 3, 5, 7, 5, 5, $bricks, $bricks, false);
		$this->generateBox($level, $boundingBox, 1, 4, 5, 1, 5, 5, $fence, $fence, false);
		$this->generateBox($level, $boundingBox, 7, 4, 5, 7, 5, 5, $fence, $fence, false);

		for($x = 0; $x <= 8; ++$x){
			for($z = 0; $z <= 5; ++$z){
				$this->fillColumnDown($level, $bricks, $x, -1, $z, $boundingBox);
			}
		}
		return true;
	}
}
