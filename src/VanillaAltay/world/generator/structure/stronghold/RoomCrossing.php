<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\stronghold;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use VanillaAltay\world\generator\structure\mineshaft\BoundingBox;

final class RoomCrossing extends StrongholdPiece{

	private int $type;

	public function __construct(int $genDepth, Random $random, BoundingBox $boundingBox, ?int $orientation){
		parent::__construct($genDepth);

		$this->setOrientation($orientation);
		$this->entryDoor = self::randomSmallDoor($random);
		$this->boundingBox = $boundingBox;
		$this->type = $random->nextBoundedInt(5);
	}

	/**
	 * @param StrongholdPiece[] $pieces
	 */
	public static function createPiece(array $pieces, Random $random, int $x, int $y, int $z, ?int $orientation, int $genDepth) : ?self{
		$box = self::orientBox($x, $y, $z, -4, -1, 0, 11, 7, 11, $orientation);

		return self::isOkBox($box) && self::findCollisionPiece($pieces, $box) === null ? new self($genDepth, $random, $box, $orientation) : null;
	}

	public function addChildren(PieceGenerator $generator, Random $random) : void{
		$this->generateSmallDoorChildForward($generator, $random, 4, 1);
		$this->generateSmallDoorChildLeft($generator, $random, 1, 4);
		$this->generateSmallDoorChildRight($generator, $random, 1, 4);
	}

	public function postProcess(ChunkManager $world, Random $random, BoundingBox $clip) : bool{
		$this->generateBoxSelector($world, $clip, $random, 0, 0, 0, 10, 6, 10);
		$this->generateSmallDoor($world, $clip, $this->entryDoor, 4, 1, 0);

		$air = VanillaBlocks::AIR();
		$this->generateBox($world, $clip, 4, 1, 10, 6, 3, 10, $air, $air);
		$this->generateBox($world, $clip, 0, 1, 4, 0, 3, 6, $air, $air);
		$this->generateBox($world, $clip, 10, 1, 4, 10, 3, 6, $air, $air);

		match($this->type){
			0 => $this->buildPillar($world, $clip),
			1 => $this->buildFountain($world, $clip),
			2 => $this->buildLoft($world, $clip),
			default => null
		};

		return true;
	}

	private function buildPillar(ChunkManager $world, BoundingBox $clip) : void{
		$bricks = VanillaBlocks::STONE_BRICKS();
		$this->placeBlock($world, $clip, $bricks, 5, 1, 5);
		$this->placeBlock($world, $clip, $bricks, 5, 2, 5);
		$this->placeBlock($world, $clip, $bricks, 5, 3, 5);

		$this->placeBlock($world, $clip, VanillaBlocks::TORCH()->setFacing(Facing::WEST), 4, 3, 5);
		$this->placeBlock($world, $clip, VanillaBlocks::TORCH()->setFacing(Facing::EAST), 6, 3, 5);
		$this->placeBlock($world, $clip, VanillaBlocks::TORCH()->setFacing(Facing::SOUTH), 5, 3, 4);
		$this->placeBlock($world, $clip, VanillaBlocks::TORCH()->setFacing(Facing::NORTH), 5, 3, 6);

		$slab = VanillaBlocks::SMOOTH_STONE_SLAB();
		$this->placeBlock($world, $clip, $slab, 4, 1, 4);
		$this->placeBlock($world, $clip, $slab, 4, 1, 5);
		$this->placeBlock($world, $clip, $slab, 4, 1, 6);
		$this->placeBlock($world, $clip, $slab, 6, 1, 4);
		$this->placeBlock($world, $clip, $slab, 6, 1, 5);
		$this->placeBlock($world, $clip, $slab, 6, 1, 6);
		$this->placeBlock($world, $clip, $slab, 5, 1, 4);
		$this->placeBlock($world, $clip, $slab, 5, 1, 6);
	}

	private function buildFountain(ChunkManager $world, BoundingBox $clip) : void{
		$bricks = VanillaBlocks::STONE_BRICKS();
		for($i = 0; $i < 5; ++$i){
			$this->placeBlock($world, $clip, $bricks, 3, 1, 3 + $i);
			$this->placeBlock($world, $clip, $bricks, 7, 1, 3 + $i);
			$this->placeBlock($world, $clip, $bricks, 3 + $i, 1, 3);
			$this->placeBlock($world, $clip, $bricks, 3 + $i, 1, 7);
		}

		$this->placeBlock($world, $clip, $bricks, 5, 1, 5);
		$this->placeBlock($world, $clip, $bricks, 5, 2, 5);
		$this->placeBlock($world, $clip, $bricks, 5, 3, 5);

		$this->placeBlock($world, $clip, VanillaBlocks::WATER(), 5, 4, 5);

		$flowing = VanillaBlocks::WATER()->setDecay(1);
		$this->placeBlock($world, $clip, $flowing, 6, 4, 5);
		$this->placeBlock($world, $clip, $flowing, 4, 4, 5);
		$this->placeBlock($world, $clip, $flowing, 5, 4, 6);
		$this->placeBlock($world, $clip, $flowing, 5, 4, 4);
		$this->placeBlock($world, $clip, $flowing, 6, 1, 4);
		$this->placeBlock($world, $clip, $flowing, 6, 1, 6);
		$this->placeBlock($world, $clip, $flowing, 4, 1, 4);
		$this->placeBlock($world, $clip, $flowing, 4, 1, 6);

		$falling = VanillaBlocks::WATER()->setDecay(1)->setFalling(true);
		for($y = 1; $y < 4; ++$y){
			$this->placeBlock($world, $clip, $falling, 6, $y, 5);
			$this->placeBlock($world, $clip, $falling, 4, $y, 5);
			$this->placeBlock($world, $clip, $falling, 5, $y, 6);
			$this->placeBlock($world, $clip, $falling, 5, $y, 4);
		}
	}

	private function buildLoft(ChunkManager $world, BoundingBox $clip) : void{
		$cobble = VanillaBlocks::COBBLESTONE();
		for($z = 1; $z <= 9; ++$z){
			$this->placeBlock($world, $clip, $cobble, 1, 3, $z);
			$this->placeBlock($world, $clip, $cobble, 9, 3, $z);
		}
		for($x = 1; $x <= 9; ++$x){
			$this->placeBlock($world, $clip, $cobble, $x, 3, 1);
			$this->placeBlock($world, $clip, $cobble, $x, 3, 9);
		}

		$this->placeBlock($world, $clip, $cobble, 5, 1, 4);
		$this->placeBlock($world, $clip, $cobble, 5, 1, 6);
		$this->placeBlock($world, $clip, $cobble, 5, 3, 4);
		$this->placeBlock($world, $clip, $cobble, 5, 3, 6);
		$this->placeBlock($world, $clip, $cobble, 4, 1, 5);
		$this->placeBlock($world, $clip, $cobble, 6, 1, 5);
		$this->placeBlock($world, $clip, $cobble, 4, 3, 5);
		$this->placeBlock($world, $clip, $cobble, 6, 3, 5);

		for($y = 1; $y <= 3; ++$y){
			$this->placeBlock($world, $clip, $cobble, 4, $y, 4);
			$this->placeBlock($world, $clip, $cobble, 6, $y, 4);
			$this->placeBlock($world, $clip, $cobble, 4, $y, 6);
			$this->placeBlock($world, $clip, $cobble, 6, $y, 6);
		}

		$this->placeBlock($world, $clip, VanillaBlocks::TORCH()->setFacing(Facing::UP), 5, 3, 5);

		$planks = VanillaBlocks::OAK_PLANKS();
		for($z = 2; $z <= 8; ++$z){
			$this->placeBlock($world, $clip, $planks, 2, 3, $z);
			$this->placeBlock($world, $clip, $planks, 3, 3, $z);

			if($z <= 3 || $z >= 7){
				$this->placeBlock($world, $clip, $planks, 4, 3, $z);
				$this->placeBlock($world, $clip, $planks, 5, 3, $z);
				$this->placeBlock($world, $clip, $planks, 6, 3, $z);
			}

			$this->placeBlock($world, $clip, $planks, 7, 3, $z);
			$this->placeBlock($world, $clip, $planks, 8, 3, $z);
		}

		$ladder = VanillaBlocks::LADDER()->setFacing(Facing::WEST);
		$this->placeBlock($world, $clip, $ladder, 9, 1, 3);
		$this->placeBlock($world, $clip, $ladder, 9, 2, 3);
		$this->placeBlock($world, $clip, $ladder, 9, 3, 3);

		$this->placeBlock($world, $clip, VanillaBlocks::CHEST()->setFacing($this->chestFacing()), 3, 4, 8);
	}
}
