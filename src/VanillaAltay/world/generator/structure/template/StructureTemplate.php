<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\template;

use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\utils\AnyFacing;
use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\MultiAnyFacing;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use function ord;
use function strlen;

/**
 * A block-by-block template: its size, a palette of block states and one byte per block indexing into it.
 * A byte of zero is a hole, which is left untouched when the template is stamped.
 */
final class StructureTemplate{

	public const ROTATION_NONE = 0;
	public const ROTATION_90 = 1;
	public const ROTATION_180 = 2;
	public const ROTATION_270 = 3;

	/**
	 * @var int[]
	 * @phpstan-var array<int, int|null>
	 */
	private array $palette;

	/**
	 * Rotated variants of a state, kept because the same states repeat across a whole structure.
	 *
	 * @var int[][]
	 * @phpstan-var array<int, array<int, int>>
	 */
	private array $rotated = [];

	/**
	 * @param int[] $palette state ids, indexed the way the stored bytes expect after subtracting one
	 * @param JigsawBlock[] $jigsaws
	 * @phpstan-param array<int, int|null> $palette
	 */
	public function __construct(
		private string $name,
		private int $sizeX,
		private int $sizeY,
		private int $sizeZ,
		array $palette,
		private string $blocks,
		private array $jigsaws = []
	){
		$this->palette = $palette;
	}

	public function getName() : string{
		return $this->name;
	}

	/**
	 * @return JigsawBlock[]
	 */
	public function getJigsaws() : array{
		return $this->jigsaws;
	}

	public function getSizeX() : int{
		return $this->sizeX;
	}

	public function getSizeY() : int{
		return $this->sizeY;
	}

	public function getSizeZ() : int{
		return $this->sizeZ;
	}

	/**
	 * A quarter turn swaps the two horizontal spans, which is what a caller needs to know where a rotated
	 * template ends.
	 *
	 * @return int[]
	 * @phpstan-return array{int, int}
	 */
	public function getRotatedSize(int $rotation) : array{
		return $rotation === self::ROTATION_90 || $rotation === self::ROTATION_270
			? [$this->sizeZ, $this->sizeX]
			: [$this->sizeX, $this->sizeZ];
	}

	/**
	 * Returns the state id at the given local position, or null where the template has a hole.
	 */
	public function getStateIdAt(int $x, int $y, int $z) : ?int{
		$index = $x + ($this->sizeX * ($y + ($this->sizeY * $z)));
		if($index < 0 || $index >= strlen($this->blocks)){
			return null;
		}

		$entry = ord($this->blocks[$index]);

		return $entry === 0 ? null : ($this->palette[$entry - 1] ?? null);
	}

	/**
	 * Writes the template into the world, skipping holes and anything falling in a chunk that is not loaded.
	 * The origin stays the corner the template grew from, whatever the rotation.
	 */
	public function place(ChunkManager $world, int $originX, int $originY, int $originZ, int $rotation = self::ROTATION_NONE) : int{
		$placed = 0;

		for($x = 0; $x < $this->sizeX; ++$x){
			for($y = 0; $y < $this->sizeY; ++$y){
				for($z = 0; $z < $this->sizeZ; ++$z){
					$stateId = $this->getStateIdAt($x, $y, $z);
					if($stateId === null){
						continue;
					}

					[$offsetX, $offsetZ] = $this->rotateOffset($x, $z, $rotation);

					$worldX = $originX + $offsetX;
					$worldY = $originY + $y;
					$worldZ = $originZ + $offsetZ;

					if(!$world->isInWorld($worldX, $worldY, $worldZ)){
						continue;
					}

					$chunk = $world->getChunk($worldX >> Chunk::COORD_BIT_SIZE, $worldZ >> Chunk::COORD_BIT_SIZE);
					if($chunk === null){
						continue;
					}

					$chunk->setBlockStateId($worldX & Chunk::COORD_MASK, $worldY, $worldZ & Chunk::COORD_MASK, $this->rotateState($stateId, $rotation));
					$placed++;
				}
			}
		}

		return $placed;
	}

	/**
	 * @return int[]
	 * @phpstan-return array{int, int}
	 */
	public function rotateOffset(int $x, int $z, int $rotation) : array{
		return match($rotation){
			self::ROTATION_90 => [$this->sizeZ - 1 - $z, $x],
			self::ROTATION_180 => [$this->sizeX - 1 - $x, $this->sizeZ - 1 - $z],
			self::ROTATION_270 => [$z, $this->sizeX - 1 - $x],
			default => [$x, $z]
		};
	}

	/**
	 * Turning a structure also turns the blocks that face somewhere: stairs, doors, chests and the rest keep
	 * their orientation relative to the building rather than to the world.
	 */
	private function rotateState(int $stateId, int $rotation) : int{
		if($rotation === self::ROTATION_NONE){
			return $stateId;
		}

		if(isset($this->rotated[$stateId][$rotation])){
			return $this->rotated[$stateId][$rotation];
		}

		$block = RuntimeBlockStateRegistry::getInstance()->fromStateId($stateId);

		$result = match(true){
			$block instanceof AnyFacing, $block instanceof HorizontalFacing => self::turnFacing($block, $rotation),
			$block instanceof MultiAnyFacing => self::turnFaces($block, $rotation),
			default => $stateId
		};

		return $this->rotated[$stateId][$rotation] = $result;
	}

	private static function turnFacing(AnyFacing|HorizontalFacing $block, int $rotation) : int{
		$facing = $block->getFacing();
		if(Facing::axis($facing) === Axis::Y){
			return $block->getStateId();
		}

		return (clone $block)->setFacing(self::turn($facing, $rotation))->getStateId();
	}

	private static function turnFaces(MultiAnyFacing $block, int $rotation) : int{
		$faces = [];
		foreach($block->getFaces() as $face){
			$faces[] = Facing::axis($face) === Axis::Y ? $face : self::turn($face, $rotation);
		}

		return (clone $block)->setFaces($faces)->getStateId();
	}

	private static function turn(int $facing, int $rotation) : int{
		for($i = 0; $i < $rotation; ++$i){
			$facing = Facing::rotateY($facing, true);
		}

		return $facing;
	}
}
