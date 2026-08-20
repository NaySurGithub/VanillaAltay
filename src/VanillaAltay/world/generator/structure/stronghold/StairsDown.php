<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\stronghold;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use VanillaAltay\world\generator\structure\mineshaft\BoundingBox;

class StairsDown extends StrongholdPiece{

	private const HORIZONTAL = [Facing::NORTH, Facing::EAST, Facing::SOUTH, Facing::WEST];

	protected bool $isSource = false;

	public function __construct(int $genDepth, Random $random, BoundingBox $boundingBox, ?int $orientation){
		parent::__construct($genDepth);

		$this->setOrientation($orientation);
		$this->entryDoor = self::randomSmallDoor($random);
		$this->boundingBox = $boundingBox;
	}

	public static function createSource(Random $random, int $x, int $z) : static{
		$piece = new static(0, $random, new BoundingBox($x, 64, $z, $x + 4, 74, $z + 4), null);
		$piece->isSource = true;
		$piece->setOrientation(self::HORIZONTAL[$random->nextBoundedInt(4)]);
		$piece->entryDoor = self::DOOR_OPENING;

		return $piece;
	}

	/**
	 * @param StrongholdPiece[] $pieces
	 */
	public static function createPiece(array $pieces, Random $random, int $x, int $y, int $z, ?int $orientation, int $genDepth) : ?self{
		$box = self::orientBox($x, $y, $z, -1, -7, 0, 5, 11, 5, $orientation);

		return self::isOkBox($box) && self::findCollisionPiece($pieces, $box) === null ? new self($genDepth, $random, $box, $orientation) : null;
	}

	public function addChildren(PieceGenerator $generator, Random $random) : void{
		if($this->isSource){
			$generator->impose(FiveCrossing::class);
		}

		$this->generateSmallDoorChildForward($generator, $random, 1, 1);
	}

	public function postProcess(ChunkManager $world, Random $random, BoundingBox $clip) : bool{
		$this->generateBoxSelector($world, $clip, $random, 0, 0, 0, 4, 10, 4);
		$this->generateSmallDoor($world, $clip, $this->entryDoor, 1, 7, 0);
		$this->generateSmallDoor($world, $clip, self::DOOR_OPENING, 1, 1, 4);

		$bricks = VanillaBlocks::STONE_BRICKS();
		$slab = VanillaBlocks::STONE_BRICK_SLAB();

		$this->placeBlock($world, $clip, $bricks, 2, 6, 1);
		$this->placeBlock($world, $clip, $bricks, 1, 5, 1);
		$this->placeBlock($world, $clip, $slab, 1, 6, 1);
		$this->placeBlock($world, $clip, $bricks, 1, 5, 2);
		$this->placeBlock($world, $clip, $bricks, 1, 4, 3);
		$this->placeBlock($world, $clip, $slab, 1, 5, 3);
		$this->placeBlock($world, $clip, $bricks, 2, 4, 3);
		$this->placeBlock($world, $clip, $bricks, 3, 3, 3);
		$this->placeBlock($world, $clip, $slab, 3, 4, 3);
		$this->placeBlock($world, $clip, $bricks, 3, 3, 2);
		$this->placeBlock($world, $clip, $bricks, 3, 2, 1);
		$this->placeBlock($world, $clip, $slab, 3, 3, 1);
		$this->placeBlock($world, $clip, $bricks, 2, 2, 1);
		$this->placeBlock($world, $clip, $bricks, 1, 1, 1);
		$this->placeBlock($world, $clip, $slab, 1, 2, 1);
		$this->placeBlock($world, $clip, $bricks, 1, 1, 2);
		$this->placeBlock($world, $clip, $slab, 1, 1, 3);

		return true;
	}
}
