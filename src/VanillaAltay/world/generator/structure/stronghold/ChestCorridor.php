<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\stronghold;

use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use VanillaAltay\world\generator\structure\mineshaft\BoundingBox;

final class ChestCorridor extends StrongholdPiece{

	public function __construct(int $genDepth, Random $random, BoundingBox $boundingBox, ?int $orientation){
		parent::__construct($genDepth);

		$this->setOrientation($orientation);
		$this->entryDoor = self::randomSmallDoor($random);
		$this->boundingBox = $boundingBox;
	}

	/**
	 * @param StrongholdPiece[] $pieces
	 */
	public static function createPiece(array $pieces, Random $random, int $x, int $y, int $z, ?int $orientation, int $genDepth) : ?self{
		$box = self::orientBox($x, $y, $z, -1, -1, 0, 5, 5, 7, $orientation);

		return self::isOkBox($box) && self::findCollisionPiece($pieces, $box) === null ? new self($genDepth, $random, $box, $orientation) : null;
	}

	public function addChildren(PieceGenerator $generator, Random $random) : void{
		$this->generateSmallDoorChildForward($generator, $random, 1, 1);
	}

	public function postProcess(ChunkManager $world, Random $random, BoundingBox $clip) : bool{
		$this->generateBoxSelector($world, $clip, $random, 0, 0, 0, 4, 4, 6);
		$this->generateSmallDoor($world, $clip, $this->entryDoor, 1, 1, 0);
		$this->generateSmallDoor($world, $clip, self::DOOR_OPENING, 1, 1, 6);

		$bricks = VanillaBlocks::STONE_BRICKS();
		$this->generateBox($world, $clip, 3, 1, 2, 3, 1, 4, $bricks, $bricks);

		$slab = VanillaBlocks::STONE_BRICK_SLAB();
		$this->placeBlock($world, $clip, $slab, 3, 1, 1);
		$this->placeBlock($world, $clip, $slab, 3, 1, 5);
		$this->placeBlock($world, $clip, $slab, 3, 2, 2);
		$this->placeBlock($world, $clip, $slab, 3, 2, 4);

		for($z = 2; $z <= 4; ++$z){
			$this->placeBlock($world, $clip, $slab, 2, 1, $z);
		}

		$this->placeBlock($world, $clip, VanillaBlocks::CHEST()->setFacing($this->chestFacing()), 3, 2, 3);

		return true;
	}
}
