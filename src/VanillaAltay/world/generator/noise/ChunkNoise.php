<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\noise;

/**
 * The 2d noise fields of one chunk, sampled once and read per column.
 *
 * @phpstan-type NoiseGrid \SplFixedArray<\SplFixedArray<float>>
 */
final class ChunkNoise{

	/**
	 * @phpstan-param NoiseGrid $continentalness
	 * @phpstan-param NoiseGrid $erosion
	 * @phpstan-param NoiseGrid $peaksValleys
	 * @phpstan-param NoiseGrid $temperature
	 * @phpstan-param NoiseGrid $humidity
	 */
	public function __construct(
		private \SplFixedArray $continentalness,
		private \SplFixedArray $erosion,
		private \SplFixedArray $peaksValleys,
		private \SplFixedArray $temperature,
		private \SplFixedArray $humidity
	){}

	public function getContinentalness(int $x, int $z) : float{
		return $this->continentalness[$x][$z];
	}

	public function getErosion(int $x, int $z) : float{
		return $this->erosion[$x][$z];
	}

	public function getPeaksValleys(int $x, int $z) : float{
		return $this->peaksValleys[$x][$z];
	}

	public function getTemperature(int $x, int $z) : float{
		return $this->temperature[$x][$z];
	}

	public function getHumidity(int $x, int $z) : float{
		return $this->humidity[$x][$z];
	}

	public function getTerrainHeight(int $x, int $z) : int{
		return NoiseRouter::terrainHeightFrom($this->getContinentalness($x, $z), $this->getErosion($x, $z), $this->getPeaksValleys($x, $z));
	}
}
