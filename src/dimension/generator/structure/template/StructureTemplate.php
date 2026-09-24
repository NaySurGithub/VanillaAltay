<?php

declare(strict_types=1);

namespace dimension\generator\structure\template;

use pocketmine\data\bedrock\block\BlockStateData;
use function chr;
use function count;
use function ord;
use function str_repeat;
use function strlen;

/**
 * A block template: a box of palette indices (0 is structure void, which
 * leaves the world untouched) plus the jigsaw blocks it contains. Indices
 * run X first, then Y, then Z.
 */
final class StructureTemplate{

	public const AIR = "minecraft:air";

	/** @var array<int, StructureTemplate> */
	private array $rotated = [];

	/**
	 * @param list<BlockStateData|null> $palette null entries are structure void
	 * @param list<JigsawConnector>     $jigsaws
	 */
	public function __construct(
		private int $sizeX,
		private int $sizeY,
		private int $sizeZ,
		private array $palette,
		private string $blocks,
		private array $jigsaws
	){}

	public function getSizeX() : int{
		return $this->sizeX;
	}

	public function getSizeY() : int{
		return $this->sizeY;
	}

	public function getSizeZ() : int{
		return $this->sizeZ;
	}

	public function getVolume() : int{
		return strlen($this->blocks);
	}

	/**
	 * @return list<JigsawConnector>
	 */
	public function getJigsaws() : array{
		return $this->jigsaws;
	}

	public function index(int $x, int $y, int $z) : int{
		return $x + ($y * $this->sizeX) + ($z * $this->sizeX * $this->sizeY);
	}

	/**
	 * State stored at a flat index, null for structure void.
	 */
	public function stateAt(int $index) : ?BlockStateData{
		$paletteIndex = ord($this->blocks[$index]) - 1;
		if($paletteIndex < 0){
			return null;
		}
		return $this->palette[$paletteIndex] ?? null;
	}

	public function xOf(int $index) : int{
		return $index % $this->sizeX;
	}

	public function yOf(int $index) : int{
		return (int) ($index / $this->sizeX) % $this->sizeY;
	}

	public function zOf(int $index) : int{
		return (int) ($index / ($this->sizeX * $this->sizeY));
	}

	/**
	 * Copy of this template with one block replaced.
	 */
	public function withBlock(int $x, int $y, int $z, BlockStateData $state) : StructureTemplate{
		$palette = $this->palette;
		$paletteIndex = -1;
		foreach($palette as $i => $entry){
			if($entry !== null && $entry->equals($state)){
				$paletteIndex = $i;
				break;
			}
		}
		if($paletteIndex === -1){
			$paletteIndex = count($palette);
			$palette[] = $state;
		}
		$blocks = $this->blocks;
		$blocks[$this->index($x, $y, $z)] = chr($paletteIndex + 1);
		return new StructureTemplate($this->sizeX, $this->sizeY, $this->sizeZ, $palette, $blocks, $this->jigsaws);
	}

	/**
	 * Turns the block grid by $geometryRotation and the block states by
	 * $stateRotation.
	 */
	public function rotate(int $geometryRotation, int $stateRotation) : StructureTemplate{
		if($geometryRotation === Rotation::NONE && $stateRotation === Rotation::NONE){
			return $this;
		}
		$key = ($geometryRotation << 2) | $stateRotation;
		return $this->rotated[$key] ??= $this->buildRotated($geometryRotation, $stateRotation);
	}

	/**
	 * Turns the grid and turns the states the opposite way.
	 */
	public function rotateGrid(int $rotation) : StructureTemplate{
		return $this->rotate($rotation, Rotation::inverse($rotation));
	}

	private function buildRotated(int $geometryRotation, int $stateRotation) : StructureTemplate{
		$swap = $geometryRotation === Rotation::ROTATE_90 || $geometryRotation === Rotation::ROTATE_270;
		$newSizeX = $swap ? $this->sizeZ : $this->sizeX;
		$newSizeZ = $swap ? $this->sizeX : $this->sizeZ;

		$palette = [];
		foreach($this->palette as $state){
			$palette[] = $state === null ? null : BlockStateTransform::rotate($state, $stateRotation);
		}

		$length = strlen($this->blocks);
		$blocks = str_repeat("\0", $length);
		for($index = 0; $index < $length; ++$index){
			$byte = $this->blocks[$index];
			if($byte === "\0"){
				continue;
			}
			$x = $index % $this->sizeX;
			$y = (int) ($index / $this->sizeX) % $this->sizeY;
			$z = (int) ($index / ($this->sizeX * $this->sizeY));
			$rx = self::rotateX($this->sizeX, $this->sizeZ, $x, $z, $geometryRotation);
			$rz = self::rotateZ($this->sizeX, $this->sizeZ, $x, $z, $geometryRotation);
			$blocks[$rx + ($y * $newSizeX) + ($rz * $newSizeX * $this->sizeY)] = $byte;
		}

		$jigsaws = [];
		foreach($this->jigsaws as $jigsaw){
			$rx = self::rotateX($this->sizeX, $this->sizeZ, $jigsaw->x, $jigsaw->z, $geometryRotation);
			$rz = self::rotateZ($this->sizeX, $this->sizeZ, $jigsaw->x, $jigsaw->z, $geometryRotation);
			$finalState = $jigsaw->finalState === null ? null : BlockStateTransform::rotate($jigsaw->finalState, $stateRotation);
			$jigsaws[] = $jigsaw->withPosition($rx, $jigsaw->y, $rz)->withFinalState($finalState);
		}

		return new StructureTemplate($newSizeX, $this->sizeY, $newSizeZ, $palette, $blocks, $jigsaws);
	}

	private static function rotateX(int $sizeX, int $sizeZ, int $x, int $z, int $rotation) : int{
		return match($rotation){
			Rotation::ROTATE_90 => $z,
			Rotation::ROTATE_180 => $sizeX - 1 - $x,
			Rotation::ROTATE_270 => $sizeZ - 1 - $z,
			default => $x
		};
	}

	private static function rotateZ(int $sizeX, int $sizeZ, int $x, int $z, int $rotation) : int{
		return match($rotation){
			Rotation::ROTATE_90 => $sizeX - 1 - $x,
			Rotation::ROTATE_180 => $sizeZ - 1 - $z,
			Rotation::ROTATE_270 => $x,
			default => $z
		};
	}
}
