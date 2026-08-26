<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\noise;

use function min;

/**
 * The 3d density field of one chunk, sampled once and read per block.
 */
final class DensityNoise
{
	/**
	 * @param float[][][] $values
	 */
	public function __construct(private array $values, private int $floor)
	{
	}

	/**
	 * @return float[]
	 * @phpstan-return array<int, float>
	 */
	public function getColumn(int $x, int $z) : array
	{
		return $this->values[$x][$z];
	}

	public function getFloor() : int
	{
		return $this->floor;
	}

	/**
	 * The field only gets to remove blocks near the surface, so cliffs and overhangs appear without turning the
	 * deep stone into swiss cheese.
	 */
	public function isSolid(int $x, int $y, int $z, int $terrainHeight) : bool
	{
		$depth = $terrainHeight - $y;
		if ($depth <= 0) {
			return true;
		}

		$threshold = min(1.0, $depth / 12);
		if ($threshold >= 1.0) {
			return true;
		}

		$index = $y - $this->floor;
		if ($index < 0 || !isset($this->values[$x][$z][$index])) {
			return true;
		}

		return $this->values[$x][$z][$index] < 0.5 + (0.5 * $threshold);
	}
}
