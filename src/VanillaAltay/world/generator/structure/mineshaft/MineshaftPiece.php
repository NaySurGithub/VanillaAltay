<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\mineshaft;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Rail;
use pocketmine\block\Torch;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use function abs;
use function max;
use function min;

abstract class MineshaftPiece{

	public const MAX_DEPTH = 8;
	public const MAX_SPREAD = 80;

	protected const RAIL_NORTH_SOUTH = 0;
	protected const RAIL_EAST_WEST = 1;

	public BoundingBox $boundingBox;

	protected ?int $orientation = null;

	public function __construct(protected int $genDepth, protected bool $mesa){}

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

	/**
	 * @param MineshaftPiece[] $pieces
	 */
	public function addChildren(MineshaftPiece $start, array &$pieces, Random $random) : void{
		//NOOP
	}

	abstract public function postProcess(ChunkManager $world, Random $random, BoundingBox $clip) : bool;

	/**
	 * @param MineshaftPiece[] $pieces
	 */
	public static function findCollisionPiece(array $pieces, BoundingBox $box) : ?MineshaftPiece{
		foreach($pieces as $piece){
			if($piece->boundingBox->intersects($box)){
				return $piece;
			}
		}

		return null;
	}

	/**
	 * @param MineshaftPiece[] $pieces
	 */
	protected static function createRandomShaftPiece(array $pieces, Random $random, int $x, int $y, int $z, ?int $orientation, int $genDepth, bool $mesa) : ?MineshaftPiece{
		$chance = $random->nextBoundedInt(100);

		if($chance >= 80){
			$box = MineshaftCrossing::findCrossing($pieces, $random, $x, $y, $z, $orientation);
			if($box !== null){
				return new MineshaftCrossing($genDepth, $box, $orientation, $mesa);
			}
		}elseif($chance >= 70){
			$box = MineshaftStairs::findStairs($pieces, $x, $y, $z, $orientation);
			if($box !== null){
				return new MineshaftStairs($genDepth, $box, $orientation, $mesa);
			}
		}else{
			$box = MineshaftCorridor::findCorridorSize($pieces, $random, $x, $y, $z, $orientation);
			if($box !== null){
				return new MineshaftCorridor($genDepth, $random, $box, $orientation, $mesa);
			}
		}

		return null;
	}

	/**
	 * @param MineshaftPiece[] $pieces
	 */
	protected static function generateAndAddPiece(MineshaftPiece $start, array &$pieces, Random $random, int $x, int $y, int $z, ?int $orientation, int $genDepth) : ?MineshaftPiece{
		if($genDepth > self::MAX_DEPTH || abs($x - $start->boundingBox->x0) > self::MAX_SPREAD || abs($z - $start->boundingBox->z0) > self::MAX_SPREAD){
			return null;
		}

		$piece = self::createRandomShaftPiece($pieces, $random, $x, $y, $z, $orientation, $genDepth + 1, $start->mesa);
		if($piece !== null){
			$pieces[] = $piece;
			$piece->addChildren($start, $pieces, $random);
		}

		return $piece;
	}

	protected function getPlanksBlock() : Block{
		return $this->mesa ? VanillaBlocks::DARK_OAK_PLANKS() : VanillaBlocks::OAK_PLANKS();
	}

	protected function getFenceBlock() : Block{
		return $this->mesa ? VanillaBlocks::DARK_OAK_FENCE() : VanillaBlocks::OAK_FENCE();
	}

	protected function getWoodBlock() : Block{
		return $this->mesa ? VanillaBlocks::DARK_OAK_LOG() : VanillaBlocks::OAK_LOG();
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

	protected function setAt(ChunkManager $world, BoundingBox $clip, int $worldX, int $worldY, int $worldZ, Block $block) : void{
		if($this->canWrite($world, $clip, $worldX, $worldY, $worldZ)){
			$world->setBlockAt($worldX, $worldY, $worldZ, $block);
		}
	}

	protected function getAt(ChunkManager $world, BoundingBox $clip, int $worldX, int $worldY, int $worldZ) : Block{
		if(!$this->canWrite($world, $clip, $worldX, $worldY, $worldZ)){
			return VanillaBlocks::AIR();
		}

		return $world->getBlockAt($worldX, $worldY, $worldZ);
	}

	protected function placeBlock(ChunkManager $world, BoundingBox $clip, Block $block, int $x, int $y, int $z) : void{
		$this->setAt($world, $clip, $this->getWorldX($x, $z), $this->getWorldY($y), $this->getWorldZ($x, $z), $this->rotate($block));
	}

	protected function getBlock(ChunkManager $world, BoundingBox $clip, int $x, int $y, int $z) : Block{
		return $this->getAt($world, $clip, $this->getWorldX($x, $z), $this->getWorldY($y), $this->getWorldZ($x, $z));
	}

	/**
	 * Torches and rails are laid out as if the piece faced north, so they have to follow the piece's orientation.
	 */
	protected function rotate(Block $block) : Block{
		if($this->orientation === null || $this->orientation === Facing::NORTH){
			return $block;
		}

		if($block instanceof Torch){
			$facing = $block->getFacing();
			if(Facing::axis($facing) !== Axis::Y){
				return (clone $block)->setFacing($this->rotateFacing($facing));
			}

			return $block;
		}

		if($block instanceof Rail && ($this->orientation === Facing::WEST || $this->orientation === Facing::EAST)){
			$shape = $block->getShape();
			if($shape === self::RAIL_NORTH_SOUTH){
				return (clone $block)->setShape(self::RAIL_EAST_WEST);
			}
			if($shape === self::RAIL_EAST_WEST){
				return (clone $block)->setShape(self::RAIL_NORTH_SOUTH);
			}
		}

		return $block;
	}

	private function rotateFacing(int $facing) : int{
		return match($this->orientation){
			Facing::SOUTH => Facing::opposite($facing),
			Facing::EAST => Facing::rotateY($facing, true),
			Facing::WEST => Facing::rotateY($facing, false),
			default => $facing
		};
	}

	protected function isLiquid(Block $block) : bool{
		$id = $block->getTypeId();

		return $id === BlockTypeIds::WATER || $id === BlockTypeIds::LAVA;
	}

	protected function edgesLiquid(ChunkManager $world, BoundingBox $clip) : bool{
		$x0 = max($this->boundingBox->x0 - 1, $clip->x0);
		$y0 = max($this->boundingBox->y0 - 1, $clip->y0);
		$z0 = max($this->boundingBox->z0 - 1, $clip->z0);
		$x1 = min($this->boundingBox->x1 + 1, $clip->x1);
		$y1 = min($this->boundingBox->y1 + 1, $clip->y1);
		$z1 = min($this->boundingBox->z1 + 1, $clip->z1);

		for($x = $x0; $x <= $x1; ++$x){
			for($z = $z0; $z <= $z1; ++$z){
				if($this->isLiquid($this->getAt($world, $clip, $x, $y0, $z)) || $this->isLiquid($this->getAt($world, $clip, $x, $y1, $z))){
					return true;
				}
			}
		}

		for($x = $x0; $x <= $x1; ++$x){
			for($y = $y0; $y <= $y1; ++$y){
				if($this->isLiquid($this->getAt($world, $clip, $x, $y, $z0)) || $this->isLiquid($this->getAt($world, $clip, $x, $y, $z1))){
					return true;
				}
			}
		}

		for($z = $z0; $z <= $z1; ++$z){
			for($y = $y0; $y <= $y1; ++$y){
				if($this->isLiquid($this->getAt($world, $clip, $x0, $y, $z)) || $this->isLiquid($this->getAt($world, $clip, $x1, $y, $z))){
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Stands in for the vanilla heightmap test: a spot only counts as interior when it is roofed over, which keeps
	 * the shaft from opening holes into the sky.
	 */
	protected function isInterior(ChunkManager $world, BoundingBox $clip, int $x, int $y, int $z) : bool{
		$worldX = $this->getWorldX($x, $z);
		$worldY = $this->getWorldY($y + 1);
		$worldZ = $this->getWorldZ($x, $z);

		if(!$this->canWrite($world, $clip, $worldX, $worldY, $worldZ)){
			return false;
		}

		for($i = 1; $i <= 5; ++$i){
			if(!$world->isInWorld($worldX, $worldY + $i, $worldZ)){
				break;
			}
			if($world->getBlockAt($worldX, $worldY + $i, $worldZ)->getTypeId() !== BlockTypeIds::AIR){
				return true;
			}
		}

		return false;
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

	protected function generateMaybeBox(ChunkManager $world, BoundingBox $clip, Random $random, int $probability, int $x1, int $y1, int $z1, int $x2, int $y2, int $z2, Block $outside, Block $inside, bool $checkInterior) : void{
		for($y = $y1; $y <= $y2; ++$y){
			for($x = $x1; $x <= $x2; ++$x){
				for($z = $z1; $z <= $z2; ++$z){
					if($random->nextBoundedInt(100) > $probability){
						continue;
					}
					if($checkInterior && !$this->isInterior($world, $clip, $x, $y, $z)){
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

	protected function generateUpperHalfSphere(ChunkManager $world, BoundingBox $clip, int $x1, int $y1, int $z1, int $x2, int $y2, int $z2, Block $block) : void{
		$xLen = (float) ($x2 - $x1 + 1);
		$yLen = (float) ($y2 - $y1 + 1);
		$zLen = (float) ($z2 - $z1 + 1);
		$xHalf = $x1 + $xLen / 2;
		$zHalf = $z1 + $zLen / 2;

		for($y = $y1; $y <= $y2; ++$y){
			$dy = ($y - $y1) / $yLen;
			for($x = $x1; $x <= $x2; ++$x){
				$dx = ($x - $xHalf) / ($xLen * 0.5);
				for($z = $z1; $z <= $z2; ++$z){
					$dz = ($z - $zHalf) / ($zLen * 0.5);
					if($dx * $dx + $dy * $dy + $dz * $dz <= 1.05){
						$this->placeBlock($world, $clip, $block, $x, $y, $z);
					}
				}
			}
		}
	}

	protected function isSupportingBox(ChunkManager $world, BoundingBox $clip, int $x0, int $x1, int $y, int $z) : bool{
		for($x = $x0; $x <= $x1; ++$x){
			if($this->getBlock($world, $clip, $x, $y + 1, $z)->getTypeId() === BlockTypeIds::AIR){
				return false;
			}
		}

		return true;
	}

	protected function setPlanksBlock(ChunkManager $world, BoundingBox $clip, Block $planks, int $x, int $y, int $z) : void{
		if(!$this->isInterior($world, $clip, $x, $y, $z)){
			return;
		}

		$worldX = $this->getWorldX($x, $z);
		$worldY = $this->getWorldY($y);
		$worldZ = $this->getWorldZ($x, $z);

		if($this->canWrite($world, $clip, $worldX, $worldY, $worldZ) && !$world->getBlockAt($worldX, $worldY, $worldZ)->isSolid()){
			$world->setBlockAt($worldX, $worldY, $worldZ, $planks);
		}
	}
}
