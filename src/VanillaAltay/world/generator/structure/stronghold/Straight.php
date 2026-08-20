<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\stronghold;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use VanillaAltay\world\generator\structure\mineshaft\BoundingBox;

final class Straight extends StrongholdPiece{

	private bool $leftChild;
	private bool $rightChild;

	public function __construct(int $genDepth, Random $random, BoundingBox $boundingBox, ?int $orientation){
		parent::__construct($genDepth);

		$this->setOrientation($orientation);
		$this->entryDoor = self::randomSmallDoor($random);
		$this->boundingBox = $boundingBox;
		$this->leftChild = $random->nextBoundedInt(2) === 0;
		$this->rightChild = $random->nextBoundedInt(2) === 0;
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

		if($this->leftChild){
			$this->generateSmallDoorChildLeft($generator, $random, 1, 2);
		}
		if($this->rightChild){
			$this->generateSmallDoorChildRight($generator, $random, 1, 2);
		}
	}

	public function postProcess(ChunkManager $world, Random $random, BoundingBox $clip) : bool{
		$this->generateBoxSelector($world, $clip, $random, 0, 0, 0, 4, 4, 6);
		$this->generateSmallDoor($world, $clip, $this->entryDoor, 1, 1, 0);
		$this->generateSmallDoor($world, $clip, self::DOOR_OPENING, 1, 1, 6);

		$torchEast = VanillaBlocks::TORCH()->setFacing(Facing::EAST);
		$torchWest = VanillaBlocks::TORCH()->setFacing(Facing::WEST);

		$this->maybeGenerateBlock($world, $clip, $random, 10, 1, 2, 1, $torchEast);
		$this->maybeGenerateBlock($world, $clip, $random, 10, 3, 2, 1, $torchWest);
		$this->maybeGenerateBlock($world, $clip, $random, 10, 1, 2, 5, $torchEast);
		$this->maybeGenerateBlock($world, $clip, $random, 10, 3, 2, 5, $torchWest);

		$air = VanillaBlocks::AIR();
		if($this->leftChild){
			$this->generateBox($world, $clip, 0, 1, 2, 0, 3, 4, $air, $air);
		}
		if($this->rightChild){
			$this->generateBox($world, $clip, 4, 1, 2, 4, 3, 4, $air, $air);
		}

		return true;
	}
}
