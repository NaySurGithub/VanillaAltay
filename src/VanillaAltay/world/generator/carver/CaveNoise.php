<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\carver;

/**
 * The cave fields of one chunk, sampled once and read per block.
 */
final class CaveNoise
{
	/**
	 * @param float[][][] $cheese
	 * @param float[][][] $tunnels
	 */
	public function __construct(
		private array $cheese,
		private array $tunnels,
		private int $floor,
		private int $seaLevel,
	) {}

	public function isCave(int $x, int $y, int $z) : bool
	{
		$index = $y - $this->floor;
		if ($index < 0 || !isset($this->cheese[$x][$z][$index])) {
			return false;
		}

		return CaveCarver::isCave($this->cheese[$x][$z][$index], $this->tunnels[$x][$z][$index], $y, $this->seaLevel);
	}

	/**
	 * Returns the two fields of one column, so a caller filling that column can read them without paying for a
	 * method call on every block.
	 *
	 * @return float[][]
	 * @phpstan-return array{array<int, float>, array<int, float>}
	 */
	public function getColumn(int $x, int $z) : array
	{
		return [$this->cheese[$x][$z], $this->tunnels[$x][$z]];
	}

	public function getFloor() : int
	{
		return $this->floor;
	}
}
