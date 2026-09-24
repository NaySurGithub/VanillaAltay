<?php

declare(strict_types=1);

namespace dimension\generator\structure;

use pocketmine\math\Facing;
use function max;
use function min;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * Inclusive box of block coordinates.
 */
final class BoundingBox{

	public function __construct(
		public int $x0,
		public int $y0,
		public int $z0,
		public int $x1,
		public int $y1,
		public int $z1
	){}

	/**
	 * Box spanning the full build height over the given columns.
	 */
	public static function columns(int $x0, int $z0, int $x1, int $z1) : BoundingBox{
		return new BoundingBox($x0, -63, $z0, $x1, 512, $z1);
	}

	/**
	 * Box of a single chunk over the full build height.
	 */
	public static function chunk(int $chunkX, int $chunkZ) : BoundingBox{
		$x = $chunkX << 4;
		$z = $chunkZ << 4;
		return self::columns($x, $z, $x + 15, $z + 15);
	}

	/**
	 * Empty box that grows to the first box it is expanded with.
	 */
	public static function unknown() : BoundingBox{
		return new BoundingBox(PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MIN, PHP_INT_MIN, PHP_INT_MIN);
	}

	/**
	 * Box of a piece of the given size placed at a point, oriented towards
	 * one of the horizontal Facing values.
	 */
	public static function orient(int $x, int $y, int $z, int $xOffset, int $yOffset, int $zOffset, int $xLength, int $yLength, int $zLength, int $orientation) : BoundingBox{
		return match($orientation){
			Facing::NORTH => new BoundingBox($x + $xOffset, $y + $yOffset, $z - $zLength + 1 + $zOffset, $x + $xLength - 1 + $xOffset, $y + $yLength - 1 + $yOffset, $z + $zOffset),
			Facing::WEST => new BoundingBox($x - $zLength + 1 + $zOffset, $y + $yOffset, $z + $xOffset, $x + $zOffset, $y + $yLength - 1 + $yOffset, $z + $xLength - 1 + $xOffset),
			Facing::EAST => new BoundingBox($x + $zOffset, $y + $yOffset, $z + $xOffset, $x + $zLength - 1 + $zOffset, $y + $yLength - 1 + $yOffset, $z + $xLength - 1 + $xOffset),
			default => new BoundingBox($x + $xOffset, $y + $yOffset, $z + $zOffset, $x + $xLength - 1 + $xOffset, $y + $yLength - 1 + $yOffset, $z + $zLength - 1 + $zOffset)
		};
	}

	public function copy() : BoundingBox{
		return new BoundingBox($this->x0, $this->y0, $this->z0, $this->x1, $this->y1, $this->z1);
	}

	public function intersects(BoundingBox $other) : bool{
		return $this->x1 >= $other->x0 && $this->x0 <= $other->x1
			&& $this->z1 >= $other->z0 && $this->z0 <= $other->z1
			&& $this->y1 >= $other->y0 && $this->y0 <= $other->y1;
	}

	public function intersectsColumns(int $x0, int $z0, int $x1, int $z1) : bool{
		return $this->x1 >= $x0 && $this->x0 <= $x1 && $this->z1 >= $z0 && $this->z0 <= $z1;
	}

	public function expand(BoundingBox $other) : void{
		$this->x0 = min($this->x0, $other->x0);
		$this->y0 = min($this->y0, $other->y0);
		$this->z0 = min($this->z0, $other->z0);
		$this->x1 = max($this->x1, $other->x1);
		$this->y1 = max($this->y1, $other->y1);
		$this->z1 = max($this->z1, $other->z1);
	}

	public function move(int $x, int $y, int $z) : void{
		$this->x0 += $x;
		$this->y0 += $y;
		$this->z0 += $z;
		$this->x1 += $x;
		$this->y1 += $y;
		$this->z1 += $z;
	}

	public function isInside(int $x, int $y, int $z) : bool{
		return $x >= $this->x0 && $x <= $this->x1 && $z >= $this->z0 && $z <= $this->z1 && $y >= $this->y0 && $y <= $this->y1;
	}

	public function getXSpan() : int{
		return $this->x1 - $this->x0 + 1;
	}

	public function getYSpan() : int{
		return $this->y1 - $this->y0 + 1;
	}

	public function getZSpan() : int{
		return $this->z1 - $this->z0 + 1;
	}
}
