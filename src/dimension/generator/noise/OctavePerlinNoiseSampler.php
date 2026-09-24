<?php

declare(strict_types=1);

namespace dimension\generator\noise;

use dimension\generator\math\Int64;
use dimension\generator\random\RandomSource;
use function count;
use function floor;
use function in_array;
use function is_array;
use function sort;

/**
 * A stack of perlin octaves.
 *
 * Built from an octave count, it holds that many samplers drawn in order.
 * Built from a list of octave numbers (0 is the base frequency, -n is 2^n
 * times lower), octave index i is the frequency 2^-i relative to the
 * highest listed octave; unlisted octaves are null but still consume their
 * share of the random sequence.
 */
final class OctavePerlinNoiseSampler{

	public readonly float $lacunarity;
	public readonly float $persistence;
	/** @var array<int, PerlinNoiseSampler|null> */
	private array $octaveSamplers = [];

	/**
	 * @param int|int[] $octaves octave count, or the list of octave numbers
	 */
	public function __construct(RandomSource $random, int|array $octaves){
		if(!is_array($octaves)){
			for($i = 0; $i < $octaves; $i++){
				$this->octaveSamplers[$i] = new PerlinNoiseSampler($random);
			}
			$this->lacunarity = 1.0;
			$this->persistence = 1.0;
			return;
		}

		sort($octaves);
		if(count($octaves) === 0){
			throw new \InvalidArgumentException("Need some octaves!");
		}
		$start = -$octaves[0];
		$end = $octaves[count($octaves) - 1];
		$length = $start + $end + 1;
		if($length < 1){
			throw new \InvalidArgumentException("Total number of octaves needs to be >= 1");
		}

		$perlin = new PerlinNoiseSampler($random);
		for($i = 0; $i < $length; $i++){
			$this->octaveSamplers[$i] = null;
		}
		if($end >= 0 && $end < $length && in_array(0, $octaves, true)){
			$this->octaveSamplers[$end] = $perlin;
		}

		for($idx = $end + 1; $idx < $length; ++$idx){
			if($idx >= 0 && in_array($end - $idx, $octaves, true)){
				$this->octaveSamplers[$idx] = new PerlinNoiseSampler($random);
			}else{
				$random->setSeed(Lcg::skip262()->nextSeed($random->getSeed()));
			}
		}

		if($end > 0){
			$noiseSeed = Int64::doubleToInt64($perlin->sample(0.0, 0.0, 0.0, 0.0, 0.0) * 9.223372036854776E18);
			$random->setSeed($noiseSeed);
			for($index = $end - 1; $index >= 0; --$index){
				if($index < $length && in_array($end - $index, $octaves, true)){
					$this->octaveSamplers[$index] = new PerlinNoiseSampler($random);
				}else{
					$random->setSeed(Lcg::skip262()->nextSeed($random->getSeed()));
				}
			}
		}

		$this->persistence = 2.0 ** $end;
		$this->lacunarity = 1.0 / (2.0 ** $length - 1.0);
	}

	public function getCount() : int{
		return count($this->octaveSamplers);
	}

	public function getOctave(int $octave) : ?PerlinNoiseSampler{
		return $this->octaveSamplers[$octave];
	}

	public function sample(float $x, float $y, float $z, float $yAmplification = 0.0, float $minY = 0.0, bool $useDefaultY = false) : float{
		$noise = 0.0;
		$persistence = $this->persistence;
		$lacunarity = $this->lacunarity;
		foreach($this->octaveSamplers as $sampler){
			if($sampler !== null){
				$noise += $sampler->sample(
					self::maintainPrecision($x * $persistence),
					$useDefaultY ? -$sampler->originY : self::maintainPrecision($y * $persistence),
					self::maintainPrecision($z * $persistence),
					$yAmplification * $persistence,
					$minY * $persistence
				) * $lacunarity;
			}
			$persistence /= 2.0;
			$lacunarity *= 2.0;
		}
		return $noise;
	}

	public static function maintainPrecision(float $value) : float{
		return $value - floor($value / 3.3554432E7 + 0.5) * 3.3554432E7;
	}
}
