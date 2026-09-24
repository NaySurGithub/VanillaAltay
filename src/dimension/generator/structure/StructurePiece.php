<?php

declare(strict_types=1);

namespace dimension\generator\structure;

use dimension\generator\object\BlockManager;
use dimension\generator\random\RandomSource;
use dimension\generator\structure\template\BlockStateTransform;
use dimension\generator\structure\template\StateBlocks;
use pocketmine\block\Block;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\math\Facing;
use function array_key_exists;

/**
 * A box-shaped part of a structure built from code. Blocks are given in
 * piece coordinates and mapped to the world through the piece orientation:
 * a piece facing south is the north-facing layout mirrored along Z, a piece
 * facing east is turned a quarter clockwise, and a piece facing west is
 * both mirrored and turned. Block states follow the same mapping.
 *
 * Every write is clipped to the box passed to postProcess(), which is the
 * chunk being populated.
 */
abstract class StructurePiece{

	public const AIR = "minecraft:air";

	/** @var array<string, Block|null> */
	private static array $blocks = [];

	protected BoundingBox $boundingBox;
	private ?int $orientation = null;

	public function __construct(
		protected int $genDepth
	){}

	/**
	 * @param list<StructurePiece> $pieces
	 */
	public static function findCollisionPiece(array $pieces, BoundingBox $boundingBox) : ?StructurePiece{
		foreach($pieces as $piece){
			if($piece->getBoundingBox()->intersects($boundingBox)){
				return $piece;
			}
		}
		return null;
	}

	/**
	 * Writes the part of the piece inside $boundingBox. Returns false when
	 * the piece must be dropped from its structure.
	 */
	abstract public function postProcess(BlockManager $level, RandomSource $random, BoundingBox $boundingBox, int $chunkX, int $chunkZ) : bool;

	public function getBoundingBox() : BoundingBox{
		return $this->boundingBox;
	}

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

	protected function getWorldX(int $x, int $z) : int{
		return match($this->orientation){
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
			Facing::NORTH => $this->boundingBox->z1 - $z,
			Facing::SOUTH => $this->boundingBox->z0 + $z,
			Facing::WEST, Facing::EAST => $this->boundingBox->z0 + $x,
			default => $z
		};
	}

	/**
	 * Block of $state as seen in the world for this piece orientation.
	 */
	protected function orientedBlock(BlockStateData $state) : ?Block{
		$orientation = $this->orientation ?? Facing::NORTH;
		$key = $orientation . StateBlocks::key($state);
		if(!array_key_exists($key, self::$blocks)){
			$oriented = match($orientation){
				Facing::SOUTH => BlockStateTransform::mirrorZ($state),
				Facing::WEST => BlockStateTransform::clockwise90(BlockStateTransform::mirrorZ($state)),
				Facing::EAST => BlockStateTransform::clockwise90($state),
				default => $state
			};
			self::$blocks[$key] = StateBlocks::toBlock($oriented);
		}
		return self::$blocks[$key];
	}

	protected function placeBlock(BlockManager $level, BlockStateData $state, int $x, int $y, int $z, BoundingBox $boundingBox) : void{
		$worldX = $this->getWorldX($x, $z);
		$worldY = $this->getWorldY($y);
		$worldZ = $this->getWorldZ($x, $z);
		if(!$boundingBox->isInside($worldX, $worldY, $worldZ)){
			return;
		}
		$block = $this->orientedBlock($state);
		if($block !== null){
			$level->setBlockStateAt($worldX, $worldY, $worldZ, $block);
		}
	}

	protected function getBlockId(BlockManager $level, int $x, int $y, int $z, BoundingBox $boundingBox) : string{
		$worldX = $this->getWorldX($x, $z);
		$worldY = $this->getWorldY($y);
		$worldZ = $this->getWorldZ($x, $z);
		if(!$boundingBox->isInside($worldX, $worldY, $worldZ)){
			return self::AIR;
		}
		return $level->getBlockIdAt($worldX, $worldY, $worldZ);
	}

	protected function generateBox(BlockManager $level, BoundingBox $boundingBox, int $x1, int $y1, int $z1, int $x2, int $y2, int $z2, BlockStateData $outsideBlock, BlockStateData $insideBlock, bool $skipAir) : void{
		for($y = $y1; $y <= $y2; ++$y){
			for($x = $x1; $x <= $x2; ++$x){
				for($z = $z1; $z <= $z2; ++$z){
					if($skipAir){
						$id = $this->getBlockId($level, $x, $y, $z, $boundingBox);
						if($id === self::AIR || $id === "minecraft:water"){
							continue;
						}
					}
					if($y !== $y1 && $y !== $y2 && $x !== $x1 && $x !== $x2 && $z !== $z1 && $z !== $z2){
						$this->placeBlock($level, $insideBlock, $x, $y, $z, $boundingBox);
					}else{
						$this->placeBlock($level, $outsideBlock, $x, $y, $z, $boundingBox);
					}
				}
			}
		}
	}

	/**
	 * Fills the column downwards from the given point with $state while it
	 * meets air or liquid.
	 */
	protected function fillColumnDown(BlockManager $level, BlockStateData $state, int $x, int $y, int $z, BoundingBox $boundingBox) : void{
		$worldX = $this->getWorldX($x, $z);
		$worldY = $this->getWorldY($y);
		$worldZ = $this->getWorldZ($x, $z);
		if(!$boundingBox->isInside($worldX, $worldY, $worldZ)){
			return;
		}
		$block = $this->orientedBlock($state);
		if($block === null){
			return;
		}
		$minY = $level->getMinHeight();
		while($worldY > 1 && $worldY >= $minY && self::isAirOrLiquid($level->getBlockIdAt($worldX, $worldY, $worldZ))){
			$level->setBlockStateAt($worldX, $worldY, $worldZ, $block);
			--$worldY;
		}
	}

	private static function isAirOrLiquid(string $id) : bool{
		return $id === self::AIR
			|| $id === "minecraft:water"
			|| $id === "minecraft:flowing_water"
			|| $id === "minecraft:lava"
			|| $id === "minecraft:flowing_lava";
	}
}
