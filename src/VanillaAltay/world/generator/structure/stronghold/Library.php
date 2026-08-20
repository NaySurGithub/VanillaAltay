<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\stronghold;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use VanillaAltay\world\generator\structure\mineshaft\BoundingBox;

final class Library extends StrongholdPiece{

	private bool $isTall;

	public function __construct(int $genDepth, Random $random, BoundingBox $boundingBox, ?int $orientation){
		parent::__construct($genDepth);

		$this->setOrientation($orientation);
		$this->entryDoor = self::randomSmallDoor($random);
		$this->boundingBox = $boundingBox;
		$this->isTall = $boundingBox->getYSpan() > 6;
	}

	/**
	 * @param StrongholdPiece[] $pieces
	 */
	public static function createPiece(array $pieces, Random $random, int $x, int $y, int $z, ?int $orientation, int $genDepth) : ?self{
		$box = self::orientBox($x, $y, $z, -4, -1, 0, 14, 11, 15, $orientation);
		if(!self::isOkBox($box) || self::findCollisionPiece($pieces, $box) !== null){
			$box = self::orientBox($x, $y, $z, -4, -1, 0, 14, 6, 15, $orientation);
			if(!self::isOkBox($box) || self::findCollisionPiece($pieces, $box) !== null){
				return null;
			}
		}

		return new self($genDepth, $random, $box, $orientation);
	}

	public function postProcess(ChunkManager $world, Random $random, BoundingBox $clip) : bool{
		$height = $this->isTall ? 11 : 6;

		$this->generateBoxSelector($world, $clip, $random, 0, 0, 0, 13, $height - 1, 14);
		$this->generateSmallDoor($world, $clip, $this->entryDoor, 4, 1, 0);

		$cobweb = VanillaBlocks::COBWEB();
		$this->generateMaybeBox($world, $clip, $random, 7, 2, 1, 1, 11, 4, 13, $cobweb, $cobweb);

		$planks = VanillaBlocks::OAK_PLANKS();
		$shelf = VanillaBlocks::BOOKSHELF();
		$torchEast = VanillaBlocks::TORCH()->setFacing(Facing::EAST);
		$torchWest = VanillaBlocks::TORCH()->setFacing(Facing::WEST);

		for($z = 1; $z <= 13; ++$z){
			if(($z - 1) % 4 === 0){
				$this->generateBox($world, $clip, 1, 1, $z, 1, 4, $z, $planks, $planks);
				$this->generateBox($world, $clip, 12, 1, $z, 12, 4, $z, $planks, $planks);
				$this->placeBlock($world, $clip, $torchEast, 2, 3, $z);
				$this->placeBlock($world, $clip, $torchWest, 11, 3, $z);
				if($this->isTall){
					$this->generateBox($world, $clip, 1, 6, $z, 1, 9, $z, $planks, $planks);
					$this->generateBox($world, $clip, 12, 6, $z, 12, 9, $z, $planks, $planks);
				}
			}else{
				$this->generateBox($world, $clip, 1, 1, $z, 1, 4, $z, $shelf, $shelf);
				$this->generateBox($world, $clip, 12, 1, $z, 12, 4, $z, $shelf, $shelf);
				if($this->isTall){
					$this->generateBox($world, $clip, 1, 6, $z, 1, 9, $z, $shelf, $shelf);
					$this->generateBox($world, $clip, 12, 6, $z, 12, 9, $z, $shelf, $shelf);
				}
			}
		}

		for($z = 3; $z < 12; $z += 2){
			$this->generateBox($world, $clip, 3, 1, $z, 4, 3, $z, $shelf, $shelf);
			$this->generateBox($world, $clip, 6, 1, $z, 7, 3, $z, $shelf, $shelf);
			$this->generateBox($world, $clip, 9, 1, $z, 10, 3, $z, $shelf, $shelf);
		}

		if($this->isTall){
			$this->buildUpperFloor($world, $clip);
		}

		$chest = VanillaBlocks::CHEST()->setFacing($this->chestFacing());
		$this->placeBlock($world, $clip, $chest, 3, 3, 5);

		if($this->isTall){
			$this->placeBlock($world, $clip, VanillaBlocks::AIR(), 12, 9, 1);
			$this->placeBlock($world, $clip, $chest, 12, 8, 1);
		}

		return true;
	}

	private function buildUpperFloor(ChunkManager $world, BoundingBox $clip) : void{
		$planks = VanillaBlocks::OAK_PLANKS();
		$fence = VanillaBlocks::OAK_FENCE();

		$this->generateBox($world, $clip, 1, 5, 1, 3, 5, 13, $planks, $planks);
		$this->generateBox($world, $clip, 10, 5, 1, 12, 5, 13, $planks, $planks);
		$this->generateBox($world, $clip, 4, 5, 1, 9, 5, 2, $planks, $planks);
		$this->generateBox($world, $clip, 4, 5, 12, 9, 5, 13, $planks, $planks);
		$this->placeBlock($world, $clip, $planks, 9, 5, 11);
		$this->placeBlock($world, $clip, $planks, 8, 5, 11);
		$this->placeBlock($world, $clip, $planks, 9, 5, 10);

		$this->generateBox($world, $clip, 3, 6, 3, 3, 6, 11, $fence, $fence);
		$this->generateBox($world, $clip, 10, 6, 3, 10, 6, 9, $fence, $fence);
		$this->generateBox($world, $clip, 4, 6, 2, 9, 6, 2, $fence, $fence);
		$this->generateBox($world, $clip, 4, 6, 12, 7, 6, 12, $fence, $fence);
		$this->placeBlock($world, $clip, $fence, 3, 6, 2);
		$this->placeBlock($world, $clip, $fence, 3, 6, 12);
		$this->placeBlock($world, $clip, $fence, 10, 6, 2);

		for($i = 0; $i <= 2; ++$i){
			$this->placeBlock($world, $clip, $fence, 8 + $i, 6, 12 - $i);
			if($i !== 2){
				$this->placeBlock($world, $clip, $fence, 8 + $i, 6, 11 - $i);
			}
		}

		$ladder = VanillaBlocks::LADDER()->setFacing(Facing::SOUTH);
		for($y = 1; $y <= 7; ++$y){
			$this->placeBlock($world, $clip, $ladder, 10, $y, 13);
		}

		$this->placeBlock($world, $clip, $fence, 6, 9, 7);
		$this->placeBlock($world, $clip, $fence, 7, 9, 7);
		$this->placeBlock($world, $clip, $fence, 6, 8, 7);
		$this->placeBlock($world, $clip, $fence, 7, 8, 7);
		$this->placeBlock($world, $clip, $fence, 6, 7, 7);
		$this->placeBlock($world, $clip, $fence, 7, 7, 7);
		$this->placeBlock($world, $clip, $fence, 5, 7, 7);
		$this->placeBlock($world, $clip, $fence, 8, 7, 7);
		$this->placeBlock($world, $clip, $fence, 6, 7, 6);
		$this->placeBlock($world, $clip, $fence, 6, 7, 8);
		$this->placeBlock($world, $clip, $fence, 7, 7, 6);
		$this->placeBlock($world, $clip, $fence, 7, 7, 8);

		$torch = VanillaBlocks::TORCH()->setFacing(Facing::UP);
		$this->placeBlock($world, $clip, $torch, 5, 8, 7);
		$this->placeBlock($world, $clip, $torch, 8, 8, 7);
		$this->placeBlock($world, $clip, $torch, 6, 8, 6);
		$this->placeBlock($world, $clip, $torch, 6, 8, 8);
		$this->placeBlock($world, $clip, $torch, 7, 8, 6);
		$this->placeBlock($world, $clip, $torch, 7, 8, 8);
	}
}
