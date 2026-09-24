<?php

declare(strict_types=1);

namespace dimension\generator\end;

use dimension\generator\holder\EndObjectHolder;
use dimension\generator\noise\SimplexNoise;
use dimension\generator\random\Xoroshiro128;

/**
 * Island height of the End as the terrain generator computes it, for the
 * populators that decide from it. The island noise is built from the seed
 * exactly like the generator does, once per seed and per thread.
 */
final class EndIslands{

	private static ?int $seed = null;
	private static ?SimplexNoise $noise = null;

	private function __construct(){
	}

	public static function height(int $seed, int $chunkX, int $chunkZ, int $x, int $z) : float{
		return EndGenerator::getIslandHeight($chunkX, $chunkZ, $x, $z, self::noise($seed));
	}

	private static function noise(int $seed) : SimplexNoise{
		if(self::$noise === null || self::$seed !== $seed){
			self::$noise = (new EndObjectHolder(new Xoroshiro128($seed)))->getTerrainHolder()->getIslandNoise();
			self::$seed = $seed;
		}
		return self::$noise;
	}
}
