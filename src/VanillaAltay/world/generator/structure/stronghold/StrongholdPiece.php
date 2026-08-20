<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\stronghold;

use pocketmine\block\Block;
use pocketmine\block\Button;
use pocketmine\block\Chest;
use pocketmine\block\Door;
use pocketmine\block\EndPortalFrame;
use pocketmine\block\Ladder;
use pocketmine\block\Stair;
use pocketmine\block\Torch;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use VanillaAltay\world\generator\structure\mineshaft\BoundingBox;

abstract class StrongholdPiece{

	public const DOOR_OPENING = 0;
	public const DOOR_WOOD = 1;
	public const DOOR_GRATES = 2;
	public const DOOR_IRON = 3;

	public const MAX_DEPTH = 50;
	public const MAX_SPREAD = 112;

	public BoundingBox $boundingBox;

	protected ?int $orientation = null;
	protected int $entryDoor = self::DOOR_OPENING;

	public function __construct(protected int $genDepth){}

	public function getGenDepth() : int{
		return $this->genDepth;
	}

	public function getOrientation() : ?int{
		return $this->orientation;
	}

	public function setOrientation(?int $orientation) : void{
		$this->orientation = $orientation;
	}

	public function move(int $x, int $y, int $z) : void{
		$this->boundingBox->move($x, $y, $z);
	}

	public function addChildren(PieceGenerator $generator, Random $random) : void{
		//NOOP
	}

	abstract public function postProcess(ChunkManager $world, Random $random, BoundingBox $clip) : bool;

	protected static function isOkBox(BoundingBox $box) : bool{
		return $box->y0 > 10;
	}

	public static function orientBox(int $x, int $y, int $z, int $xOffset, int $yOffset, int $zOffset, int $xLength, int $yLength, int $zLength, ?int $orientation) : BoundingBox{
		return match($orientation){
			Facing::NORTH => new BoundingBox($x + $xOffset, $y + $yOffset, $z - $zLength + 1 + $zOffset, $x + $xLength - 1 + $xOffset, $y + $yLength - 1 + $yOffset, $z + $zOffset),
			Facing::WEST => new BoundingBox($x - $zLength + 1 + $zOffset, $y + $yOffset, $z + $xOffset, $x + $zOffset, $y + $yLength - 1 + $yOffset, $z + $xLength - 1 + $xOffset),
			Facing::EAST => new BoundingBox($x + $zOffset, $y + $yOffset, $z + $xOffset, $x + $zLength - 1 + $zOffset, $y + $yLength - 1 + $yOffset, $z + $xLength - 1 + $xOffset),
			default => new BoundingBox($x + $xOffset, $y + $yOffset, $z + $zOffset, $x + $xLength - 1 + $xOffset, $y + $yLength - 1 + $yOffset, $z + $zLength - 1 + $zOffset)
		};
	}

	/**
	 * @param StrongholdPiece[] $pieces
	 */
	public static function findCollisionPiece(array $pieces, BoundingBox $box) : ?StrongholdPiece{
		foreach($pieces as $piece){
			if($piece->boundingBox->intersects($box)){
				return $piece;
			}
		}

		return null;
	}

	protected static function randomSmallDoor(Random $random) : int{
		return match($random->nextBoundedInt(5)){
			2 => self::DOOR_WOOD,
			3 => self::DOOR_GRATES,
			4 => self::DOOR_IRON,
			default => self::DOOR_OPENING
		};
	}

	protected function generateSmallDoor(ChunkManager $world, BoundingBox $clip, int $type, int $x, int $y, int $z) : void{
		$air = VanillaBlocks::AIR();
		$bricks = VanillaBlocks::STONE_BRICKS();

		switch($type){
			case self::DOOR_WOOD:
			case self::DOOR_IRON:
				$this->placeBlock($world, $clip, $bricks, $x, $y, $z);
				$this->placeBlock($world, $clip, $bricks, $x, $y + 1, $z);
				$this->placeBlock($world, $clip, $bricks, $x, $y + 2, $z);
				$this->placeBlock($world, $clip, $bricks, $x + 1, $y + 2, $z);
				$this->placeBlock($world, $clip, $bricks, $x + 2, $y + 2, $z);
				$this->placeBlock($world, $clip, $bricks, $x + 2, $y + 1, $z);
				$this->placeBlock($world, $clip, $bricks, $x + 2, $y, $z);

				$door = $type === self::DOOR_WOOD ? VanillaBlocks::OAK_DOOR() : VanillaBlocks::IRON_DOOR();
				$this->placeBlock($world, $clip, (clone $door)->setFacing(Facing::EAST), $x + 1, $y, $z);
				$this->placeBlock($world, $clip, (clone $door)->setFacing(Facing::EAST)->setTop(true), $x + 1, $y + 1, $z);

				if($type === self::DOOR_IRON){
					$this->placeBlock($world, $clip, VanillaBlocks::STONE_BUTTON()->setFacing(Facing::NORTH), $x + 2, $y + 1, $z + 1);
					$this->placeBlock($world, $clip, VanillaBlocks::STONE_BUTTON()->setFacing(Facing::SOUTH), $x + 2, $y + 1, $z - 1);
				}
				break;
			case self::DOOR_GRATES:
				$bars = VanillaBlocks::IRON_BARS();
				$this->placeBlock($world, $clip, $air, $x + 1, $y, $z);
				$this->placeBlock($world, $clip, $air, $x + 1, $y + 1, $z);
				$this->placeBlock($world, $clip, $bars, $x, $y, $z);
				$this->placeBlock($world, $clip, $bars, $x, $y + 1, $z);
				$this->placeBlock($world, $clip, $bars, $x, $y + 2, $z);
				$this->placeBlock($world, $clip, $bars, $x + 1, $y + 2, $z);
				$this->placeBlock($world, $clip, $bars, $x + 2, $y + 2, $z);
				$this->placeBlock($world, $clip, $bars, $x + 2, $y + 1, $z);
				$this->placeBlock($world, $clip, $bars, $x + 2, $y, $z);
				break;
			default:
				$this->generateBox($world, $clip, $x, $y, $z, $x + 2, $y + 2, $z, $air, $air);
		}
	}

	protected function generateSmallDoorChildForward(PieceGenerator $generator, Random $random, int $x, int $y) : ?StrongholdPiece{
		return match($this->orientation){
			Facing::NORTH => $generator->generateAndAddPiece($random, $this->boundingBox->x0 + $x, $this->boundingBox->y0 + $y, $this->boundingBox->z0 - 1, Facing::NORTH, $this->genDepth),
			Facing::SOUTH => $generator->generateAndAddPiece($random, $this->boundingBox->x0 + $x, $this->boundingBox->y0 + $y, $this->boundingBox->z1 + 1, Facing::SOUTH, $this->genDepth),
			Facing::WEST => $generator->generateAndAddPiece($random, $this->boundingBox->x0 - 1, $this->boundingBox->y0 + $y, $this->boundingBox->z0 + $x, Facing::WEST, $this->genDepth),
			Facing::EAST => $generator->generateAndAddPiece($random, $this->boundingBox->x1 + 1, $this->boundingBox->y0 + $y, $this->boundingBox->z0 + $x, Facing::EAST, $this->genDepth),
			default => null
		};
	}

	protected function generateSmallDoorChildLeft(PieceGenerator $generator, Random $random, int $y, int $z) : ?StrongholdPiece{
		return match($this->orientation){
			Facing::NORTH, Facing::SOUTH => $generator->generateAndAddPiece($random, $this->boundingBox->x0 - 1, $this->boundingBox->y0 + $y, $this->boundingBox->z0 + $z, Facing::WEST, $this->genDepth),
			Facing::WEST, Facing::EAST => $generator->generateAndAddPiece($random, $this->boundingBox->x0 + $z, $this->boundingBox->y0 + $y, $this->boundingBox->z0 - 1, Facing::NORTH, $this->genDepth),
			default => null
		};
	}

	protected function generateSmallDoorChildRight(PieceGenerator $generator, Random $random, int $y, int $z) : ?StrongholdPiece{
		return match($this->orientation){
			Facing::NORTH, Facing::SOUTH => $generator->generateAndAddPiece($random, $this->boundingBox->x1 + 1, $this->boundingBox->y0 + $y, $this->boundingBox->z0 + $z, Facing::EAST, $this->genDepth),
			Facing::WEST, Facing::EAST => $generator->generateAndAddPiece($random, $this->boundingBox->x0 + $z, $this->boundingBox->y0 + $y, $this->boundingBox->z1 + 1, Facing::SOUTH, $this->genDepth),
			default => null
		};
	}

	protected function getWorldX(int $x, int $z) : int{
		return match($this->orientation){
			null => $x,
			Facing::NORTH, Facing::SOUTH => $this->boundingBox->x0 + $x,
			Facing::WEST => $this->boundingBox->x1 - $z,
			Facing::EAST => $this->boundingBox->x0 + $z,
			default => $x
		};
	}

	protected function getWorldY(int $y) : int{
		return $this->orientation === null ? $y : $y + $this->boundingBox->y0;
	}

	protected function getWorldZ(int $x, int $z) : int{
		return match($this->orientation){
			null => $z,
			Facing::NORTH => $this->boundingBox->z1 - $z,
			Facing::SOUTH => $this->boundingBox->z0 + $z,
			Facing::WEST, Facing::EAST => $this->boundingBox->z0 + $x,
			default => $z
		};
	}

	protected function canWrite(ChunkManager $world, BoundingBox $clip, int $worldX, int $worldY, int $worldZ) : bool{
		return $clip->isInside($worldX, $worldY, $worldZ)
			&& $world->isInWorld($worldX, $worldY, $worldZ)
			&& $world->getChunk($worldX >> 4, $worldZ >> 4) !== null;
	}

	protected function placeBlock(ChunkManager $world, BoundingBox $clip, Block $block, int $x, int $y, int $z) : void{
		$worldX = $this->getWorldX($x, $z);
		$worldY = $this->getWorldY($y);
		$worldZ = $this->getWorldZ($x, $z);

		if($this->canWrite($world, $clip, $worldX, $worldY, $worldZ)){
			$world->setBlockAt($worldX, $worldY, $worldZ, $this->rotate($block));
		}
	}

	/**
	 * Pieces are laid out as if they faced north, so anything with a facing has to follow the piece's orientation.
	 */
	protected function rotate(Block $block) : Block{
		if($this->orientation === null || $this->orientation === Facing::NORTH){
			return $block;
		}

		if($block instanceof Torch || $block instanceof Stair || $block instanceof Door || $block instanceof Chest || $block instanceof EndPortalFrame || $block instanceof Ladder || $block instanceof Button){
			$facing = $block->getFacing();
			if(Facing::axis($facing) !== Axis::Y){
				return (clone $block)->setFacing($this->rotateFacing($facing));
			}
		}

		return $block;
	}

	protected function rotateFacing(int $facing) : int{
		return match($this->orientation){
			Facing::SOUTH => Facing::opposite($facing),
			Facing::EAST => Facing::rotateY($facing, true),
			Facing::WEST => Facing::rotateY($facing, false),
			default => $facing
		};
	}

	/**
	 * Local facing: rotate() turns it into the direction opposite the piece's orientation.
	 */
	protected function chestFacing() : int{
		return Facing::SOUTH;
	}

	protected function generateBox(ChunkManager $world, BoundingBox $clip, int $x1, int $y1, int $z1, int $x2, int $y2, int $z2, Block $outside, Block $inside) : void{
		for($y = $y1; $y <= $y2; ++$y){
			for($x = $x1; $x <= $x2; ++$x){
				for($z = $z1; $z <= $z2; ++$z){
					$edge = $y === $y1 || $y === $y2 || $x === $x1 || $x === $x2 || $z === $z1 || $z === $z2;
					$this->placeBlock($world, $clip, $edge ? $outside : $inside, $x, $y, $z);
				}
			}
		}
	}

	protected function generateBoxSelector(ChunkManager $world, BoundingBox $clip, Random $random, int $x1, int $y1, int $z1, int $x2, int $y2, int $z2) : void{
		for($y = $y1; $y <= $y2; ++$y){
			for($x = $x1; $x <= $x2; ++$x){
				for($z = $z1; $z <= $z2; ++$z){
					$edge = $y === $y1 || $y === $y2 || $x === $x1 || $x === $x2 || $z === $z1 || $z === $z2;
					$this->placeBlock($world, $clip, self::selectSmoothStone($random, $edge), $x, $y, $z);
				}
			}
		}
	}

	protected function generateMaybeBox(ChunkManager $world, BoundingBox $clip, Random $random, int $probability, int $x1, int $y1, int $z1, int $x2, int $y2, int $z2, Block $outside, Block $inside) : void{
		for($y = $y1; $y <= $y2; ++$y){
			for($x = $x1; $x <= $x2; ++$x){
				for($z = $z1; $z <= $z2; ++$z){
					if($random->nextBoundedInt(100) > $probability){
						continue;
					}

					$edge = $y === $y1 || $y === $y2 || $x === $x1 || $x === $x2 || $z === $z1 || $z === $z2;
					$this->placeBlock($world, $clip, $edge ? $outside : $inside, $x, $y, $z);
				}
			}
		}
	}

	protected function maybeGenerateBlock(ChunkManager $world, BoundingBox $clip, Random $random, int $probability, int $x, int $y, int $z, Block $block) : void{
		if($random->nextBoundedInt(100) < $probability){
			$this->placeBlock($world, $clip, $block, $x, $y, $z);
		}
	}

	protected static function selectSmoothStone(Random $random, bool $edge) : Block{
		if(!$edge){
			return VanillaBlocks::AIR();
		}

		$chance = $random->nextBoundedInt(100);
		if($chance < 20){
			return VanillaBlocks::CRACKED_STONE_BRICKS();
		}
		if($chance < 50){
			return VanillaBlocks::MOSSY_STONE_BRICKS();
		}
		if($chance < 55){
			return VanillaBlocks::INFESTED_STONE_BRICK();
		}

		return VanillaBlocks::STONE_BRICKS();
	}
}
