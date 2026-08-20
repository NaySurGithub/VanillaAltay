<?php

declare(strict_types=1);

namespace VanillaAltay\world\generator\carver;

use pocketmine\utils\Random;
use pocketmine\world\generator\noise\Simplex;
use function abs;
use function ceil;
use function max;
use function min;

/**
 * Decides where caves are dug.
 *
 * Vanilla uses two families of noise for this: wide "cheese" caves that hollow out rooms, and thin winding
 * tunnels that connect them. Both fields are sampled on a coarse grid and interpolated, since evaluating them
 * per block costs more than the rest of the generator put together.
 */
final class CaveCarver{

	private const CHEESE_THRESHOLD = 0.78;
	private const TUNNEL_THRESHOLD = 0.022;

	private const SAMPLING_RATE = 8;

	/**
	 * Caves change slowly with height, so they can be sampled twice as coarsely vertically for half the cost.
	 */
	public const SAMPLING_RATE_Y = 8;

	/**
	 * Caves stop this far below the surface so they don't open the ground everywhere.
	 */
	public const SURFACE_MARGIN = 6;

	private Simplex $cheese;
	private Simplex $tunnels;

	public function __construct(Random $random, private int $seaLevel){
		$this->cheese = new Simplex($random, 3, 0.5, 1 / 48);
		$this->tunnels = new Simplex($random, 2, 0.5, 1 / 72);
	}

	public function sample(int $chunkX, int $chunkZ, int $floor, int $ceiling) : CaveNoise{
		$ySize = (int) ceil(max(1, $ceiling - $floor + 1) / self::SAMPLING_RATE_Y) * self::SAMPLING_RATE_Y;
		$baseX = $chunkX * 16;
		$baseZ = $chunkZ * 16;

		return new CaveNoise(
			$this->cheese->getFastNoise3D(16, $ySize, 16, self::SAMPLING_RATE, self::SAMPLING_RATE_Y, self::SAMPLING_RATE, $baseX, $floor, $baseZ),
			$this->tunnels->getFastNoise3D(16, $ySize, 16, self::SAMPLING_RATE, self::SAMPLING_RATE_Y, self::SAMPLING_RATE, $baseX, $floor, $baseZ),
			$floor,
			$this->seaLevel
		);
	}

	/**
	 * Returns whether a cave can cross the cell between two vertical samples.
	 *
	 * The sampled noise is interpolated linearly, so a value inside the cell never leaves the interval its two
	 * ends define. When that interval stays clear of both thresholds, the whole cell is solid and the caller can
	 * skip it instead of testing its blocks one by one.
	 */
	public static function cellMayContainCave(float $cheese0, float $cheese1, float $tunnel0, float $tunnel1) : bool{
		if(($tunnel0 < 0) !== ($tunnel1 < 0) || min(abs($tunnel0), abs($tunnel1)) < self::TUNNEL_THRESHOLD){
			return true;
		}

		return max($cheese0, $cheese1) > self::CHEESE_THRESHOLD;
	}

	public static function isCave(float $cheese, float $tunnel, int $y, int $seaLevel) : bool{
		if(abs($tunnel) < self::TUNNEL_THRESHOLD){
			return true;
		}

		//rooms get rarer as they approach the sea level, so caves stay mostly underground
		return $cheese > self::CHEESE_THRESHOLD + max(0.0, 0.2 - (min(40, max(0, $seaLevel - $y)) / 200));
	}
}
