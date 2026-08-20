<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\noise;

use pocketmine\utils\Random;
use pocketmine\world\generator\noise\Simplex;
use function ceil;
use function max;
use function min;

/**
 * Holds every noise field the overworld generator samples, and turns them into the terrain shape.
 *
 * This follows the structure vanilla has used since 1.18: instead of a single heightmap noise, several
 * independent low frequency fields describe the landscape and the terrain height is derived from them.
 * It is not a bit-for-bit port of Mojang's density functions, but the roles of the fields are the same.
 */
final class NoiseRouter{

	public const BASE_HEIGHT = 66;

	private const SAMPLING_RATE = 4;
	private const PERSISTENCE = 0.5;

	private Simplex $continentalness;
	private Simplex $erosion;
	private Simplex $peaksValleys;
	private Simplex $temperature;
	private Simplex $humidity;
	private Simplex $density;
	private Simplex $surface;

	public function __construct(Random $random){
		//few octaves on purpose: the extra ones add detail at a fraction of the scale, and that small wobble is
		//what chops a map into biome slivers by crossing the band edges back and forth
		$this->continentalness = new Simplex($random, 3, 0.5, 1 / 512);
		$this->erosion = new Simplex($random, 3, 0.5, 1 / 384);
		$this->peaksValleys = new Simplex($random, 4, 0.5, 1 / 192);
		$this->temperature = new Simplex($random, 2, 0.5, 1 / 1024);
		$this->humidity = new Simplex($random, 2, 0.5, 1 / 896);
		$this->density = new Simplex($random, 4, 0.5, 1 / 96);
		$this->surface = new Simplex($random, 2, 0.5, 1 / 24);
	}

	public function getContinentalness(int $x, int $z) : float{
		return $this->continentalness->noise3D($x, 0, $z, true);
	}

	public function getErosion(int $x, int $z) : float{
		return $this->erosion->noise3D($x, 0, $z, true);
	}

	public function getPeaksValleys(int $x, int $z) : float{
		return $this->peaksValleys->noise3D($x, 0, $z, true);
	}

	public function getTemperature(int $x, int $z) : float{
		return $this->temperature->noise3D($x, 0, $z, true);
	}

	public function getHumidity(int $x, int $z) : float{
		return $this->humidity->noise3D($x, 0, $z, true);
	}

	public function getDensity(int $x, int $y, int $z) : float{
		return $this->density->noise3D($x, $y, $z, true);
	}

	public function sampleDensity(int $chunkX, int $chunkZ, int $floor, int $ceiling) : DensityNoise{
		$ySize = (int) ceil(max(1, $ceiling - $floor + 1) / self::SAMPLING_RATE) * self::SAMPLING_RATE;

		return new DensityNoise(
			$this->density->getFastNoise3D(16, $ySize, 16, self::SAMPLING_RATE, self::SAMPLING_RATE, self::SAMPLING_RATE, $chunkX * 16, $floor, $chunkZ * 16),
			$floor
		);
	}

	public function getSurfaceNoise(int $x, int $z) : float{
		return $this->surface->noise2D($x, $z, true);
	}

	/**
	 * Samples every 2d field over a whole chunk at once.
	 *
	 * Sampling a coarse grid and interpolating between the samples costs a fraction of what evaluating all
	 * 256 columns does, and the fields are slow enough that the difference is not visible.
	 */
	public function sampleChunk(int $chunkX, int $chunkZ) : ChunkNoise{
		$baseX = $chunkX * 16;
		$baseZ = $chunkZ * 16;

		return new ChunkNoise(
			self::sample($this->continentalness, 3, $baseX, $baseZ),
			self::sample($this->erosion, 3, $baseX, $baseZ),
			self::sample($this->peaksValleys, 4, $baseX, $baseZ),
			self::sample($this->temperature, 2, $baseX, $baseZ),
			self::sample($this->humidity, 2, $baseX, $baseZ)
		);
	}

	/**
	 * The sampled noise comes out unnormalized, unlike noise2D(), so it has to be divided by the sum of the
	 * octave amplitudes to land back in the same range the rest of the generator expects.
	 *
	 * @phpstan-return \SplFixedArray<\SplFixedArray<float>>
	 */
	private static function sample(Simplex $noise, int $octaves, int $baseX, int $baseZ) : \SplFixedArray{
		$grid = $noise->getFastNoise2D(16, 16, self::SAMPLING_RATE, $baseX, 0, $baseZ);

		$max = 0.0;
		$amplitude = 1.0;
		for($i = 0; $i < $octaves; ++$i){
			$max += $amplitude;
			$amplitude *= self::PERSISTENCE;
		}

		for($x = 0; $x < 16; ++$x){
			for($z = 0; $z < 16; ++$z){
				$grid[$x][$z] /= $max;
			}
		}

		return $grid;
	}

	/**
	 * Returns the height the terrain reaches at the given column, before the 3d density field carves it.
	 */
	public function getTerrainHeight(int $x, int $z) : int{
		return self::terrainHeightFrom(
			$this->getContinentalness($x, $z),
			$this->getErosion($x, $z),
			$this->getPeaksValleys($x, $z)
		);
	}

	public static function terrainHeightFrom(float $continentalness, float $erosion, float $peaksValleys) : int{
		//below zero continentalness digs an ocean basin, above it barely lifts the land: what makes a mountain is
		//the relief, not the continent, otherwise the whole map drifts far above sea level
		$height = self::BASE_HEIGHT + ($continentalness < 0 ? $continentalness * 46 : $continentalness * 10);

		//eroded areas flatten into plains, uneroded ones keep their relief
		$height += $peaksValleys * (1 - max(0.0, $erosion)) * 14;

		if($peaksValleys > 0.55 && $erosion < 0){
			$height += ($peaksValleys - 0.55) * 130;
		}

		return (int) max(1, min(300, $height));
	}

	/**
	 * Returns how much the 3d field is allowed to eat into the terrain at the given height. Near the surface it
	 * is almost free, deeper down it is clamped so the ground stays solid.
	 */
	public function getDensityThreshold(int $y, int $terrainHeight) : float{
		$depth = $terrainHeight - $y;

		return $depth <= 0 ? 1.0 : min(1.0, $depth / 12);
	}
}
