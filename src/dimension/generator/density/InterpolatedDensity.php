<?php

declare(strict_types=1);

namespace dimension\generator\density;

use function count;

/**
 * Samples another function at the corners of 4x8x4 block cells and
 * trilinearly interpolates inside each cell. The last filled cell and the
 * recently sampled corners are kept, so each corner is evaluated once.
 */
final class InterpolatedDensity implements DensityFunction{

	private const CELL_SIZE_XZ = 4;
	private const CELL_SIZE_Y = 8;
	private const MAX_CACHED_CORNERS = 4096;

	/** @var float[] */
	private array $values = [];
	/** @var array<string, float> */
	private array $corners = [];
	private ?int $lastX = null;
	private int $lastY = 0;
	private int $lastZ = 0;

	public function __construct(
		private DensityFunction $wrapped
	){}

	public function compute(int $x, int $y, int $z) : float{
		$cellX = ($x >> 2) << 2;
		$cellY = ($y >> 3) << 3;
		$cellZ = ($z >> 2) << 2;
		if($this->lastX !== $cellX || $this->lastY !== $cellY || $this->lastZ !== $cellZ){
			$this->fillCell($cellX, $cellY, $cellZ);
			$this->lastX = $cellX;
			$this->lastY = $cellY;
			$this->lastZ = $cellZ;
		}
		return $this->values[(($y & 7) << 4) | (($z & 3) << 2) | ($x & 3)];
	}

	public function minValue() : float{
		return $this->wrapped->minValue();
	}

	public function maxValue() : float{
		return $this->wrapped->maxValue();
	}

	private function fillCell(int $cellX, int $cellY, int $cellZ) : void{
		if(count($this->corners) > self::MAX_CACHED_CORNERS){
			$this->corners = [];
		}
		$nextX = $cellX + self::CELL_SIZE_XZ;
		$nextY = $cellY + self::CELL_SIZE_Y;
		$nextZ = $cellZ + self::CELL_SIZE_XZ;
		$d000 = $this->corner($cellX, $cellY, $cellZ);
		$d100 = $this->corner($nextX, $cellY, $cellZ);
		$d010 = $this->corner($cellX, $nextY, $cellZ);
		$d110 = $this->corner($nextX, $nextY, $cellZ);
		$d001 = $this->corner($cellX, $cellY, $nextZ);
		$d101 = $this->corner($nextX, $cellY, $nextZ);
		$d011 = $this->corner($cellX, $nextY, $nextZ);
		$d111 = $this->corner($nextX, $nextY, $nextZ);

		$c000 = $d000;
		$c100 = $d100 - $d000;
		$c010 = $d010 - $d000;
		$c001 = $d001 - $d000;
		$c110 = $d110 - $d100 - $d010 + $d000;
		$c101 = $d101 - $d100 - $d001 + $d000;
		$c011 = $d011 - $d010 - $d001 + $d000;
		$c111 = $d111 - $d110 - $d101 - $d011 + $d100 + $d010 + $d001 - $d000;

		$index = 0;
		$values = [];
		for($localY = 0; $localY < self::CELL_SIZE_Y; $localY++){
			$yAlpha = $localY * (1.0 / self::CELL_SIZE_Y);
			for($localZ = 0; $localZ < self::CELL_SIZE_XZ; $localZ++){
				$zAlpha = $localZ * (1.0 / self::CELL_SIZE_XZ);
				$yz = $yAlpha * $zAlpha;
				$zTerm = $zAlpha * ($c001 + $yAlpha * $c011);
				for($localX = 0; $localX < self::CELL_SIZE_XZ; $localX++){
					$xAlpha = $localX * (1.0 / self::CELL_SIZE_XZ);
					$values[$index++] = $c000
						+ $xAlpha * ($c100 + $yAlpha * $c110 + $zAlpha * $c101 + $yz * $c111)
						+ $yAlpha * $c010
						+ $zTerm;
				}
			}
		}
		$this->values = $values;
	}

	private function corner(int $x, int $y, int $z) : float{
		return $this->corners[$x . ":" . $y . ":" . $z] ??= $this->wrapped->compute($x, $y, $z);
	}
}
