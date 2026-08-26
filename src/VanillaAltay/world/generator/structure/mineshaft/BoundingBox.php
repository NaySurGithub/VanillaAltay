<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\structure\mineshaft;

use function max;
use function min;

final class BoundingBox
{
	public function __construct(
		public int $x0,
		public int $y0,
		public int $z0,
		public int $x1,
		public int $y1,
		public int $z1,
	) {}

	public function intersects(BoundingBox $other) : bool
	{
		return $this->x1 >= $other->x0 && $this->x0 <= $other->x1
			&& $this->z1 >= $other->z0 && $this->z0 <= $other->z1
			&& $this->y1 >= $other->y0 && $this->y0 <= $other->y1;
	}

	public function expand(BoundingBox $other) : void
	{
		$this->x0 = min($this->x0, $other->x0);
		$this->y0 = min($this->y0, $other->y0);
		$this->z0 = min($this->z0, $other->z0);
		$this->x1 = max($this->x1, $other->x1);
		$this->y1 = max($this->y1, $other->y1);
		$this->z1 = max($this->z1, $other->z1);
	}

	public function move(int $x, int $y, int $z) : void
	{
		$this->x0 += $x;
		$this->y0 += $y;
		$this->z0 += $z;
		$this->x1 += $x;
		$this->y1 += $y;
		$this->z1 += $z;
	}

	public function isInside(int $x, int $y, int $z) : bool
	{
		return $x >= $this->x0 && $x <= $this->x1
			&& $y >= $this->y0 && $y <= $this->y1
			&& $z >= $this->z0 && $z <= $this->z1;
	}

	public function getXSpan() : int
	{
		return $this->x1 - $this->x0 + 1;
	}

	public function getYSpan() : int
	{
		return $this->y1 - $this->y0 + 1;
	}

	public function getZSpan() : int
	{
		return $this->z1 - $this->z0 + 1;
	}
}
