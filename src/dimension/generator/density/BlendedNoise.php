<?php

declare(strict_types=1);

namespace dimension\generator\density;

use dimension\generator\math\GenerationMath;
use dimension\generator\noise\OctavePerlinNoiseSampler;
use dimension\generator\random\RandomSource;

/**
 * Base 3D terrain noise: a main noise selects, per block, a blend between a
 * lower and an upper limit noise, all of them smeared vertically.
 */
final class BlendedNoise implements DensityFunction{

	private const MAIN_NOISE_DIVISOR = 10.0;
	private const LIMIT_NOISE_DIVISOR = 512.0;
	private const RESULT_DIVISOR = 128.0;
	private const BASE_SCALE = 684.412;

	private function __construct(
		private OctavePerlinNoiseSampler $minLimitNoise,
		private OctavePerlinNoiseSampler $maxLimitNoise,
		private OctavePerlinNoiseSampler $mainNoise,
		private float $xzScale,
		private float $yScale,
		private float $xzFactor,
		private float $yFactor,
		private float $smearScaleMultiplier
	){}

	/**
	 * Draws the lower limit, upper limit and main noises from three forks of $random, in that order.
	 */
	public static function create(RandomSource $random, float $xzScale, float $yScale, float $xzFactor, float $yFactor, float $smearScaleMultiplier) : BlendedNoise{
		$limitOctaves = self::descendingOctaves(-15, 0);
		$mainOctaves = self::descendingOctaves(-7, 0);
		return new BlendedNoise(
			new OctavePerlinNoiseSampler($random->fork(), $limitOctaves),
			new OctavePerlinNoiseSampler($random->fork(), $limitOctaves),
			new OctavePerlinNoiseSampler($random->fork(), $mainOctaves),
			$xzScale,
			$yScale,
			$xzFactor,
			$yFactor,
			$smearScaleMultiplier
		);
	}

	public static function overworld(RandomSource $random) : BlendedNoise{
		return self::create($random, 0.25, 0.125, 80.0, 160.0, 8.0);
	}

	public static function nether(RandomSource $random) : BlendedNoise{
		return self::create($random, 0.25, 0.375, 80.0, 60.0, 8.0);
	}

	/**
	 * @return int[]
	 */
	private static function descendingOctaves(int $minInclusive, int $maxInclusive) : array{
		$octaves = [];
		for($i = $maxInclusive; $i >= $minInclusive; $i--){
			$octaves[] = $i;
		}
		return $octaves;
	}

	public function compute(int $x, int $y, int $z) : float{
		$scaledXZ = self::BASE_SCALE * $this->xzScale;
		$scaledY = self::BASE_SCALE * $this->yScale;
		$mainScaledXZ = $scaledXZ / $this->xzFactor;
		$mainScaledY = $scaledY / $this->yFactor;
		$smearScale = $scaledY * $this->smearScaleMultiplier;

		$mainValue = 0.0;
		$frequency = 1.0;
		$count = $this->mainNoise->getCount();
		for($octave = 0; $octave < $count; $octave++){
			$sampler = $this->mainNoise->getOctave($octave);
			if($sampler !== null){
				$mainValue += $sampler->sample(
					$x * $mainScaledXZ * $frequency,
					$y * $mainScaledY * $frequency,
					$z * $mainScaledXZ * $frequency,
					$smearScale * $frequency,
					$y * $mainScaledY * $frequency
				) / $frequency;
			}
			$frequency /= 2.0;
		}

		$blend = GenerationMath::clamp($mainValue / self::MAIN_NOISE_DIVISOR + 1.0, 0.0, 2.0) * 0.5;
		$useOnlyMax = $blend >= 1.0;
		$useOnlyMin = $blend <= 0.0;

		$minValue = 0.0;
		$maxValue = 0.0;
		$frequency = 1.0;
		$octaveCount = $this->minLimitNoise->getCount() < $this->maxLimitNoise->getCount() ? $this->minLimitNoise->getCount() : $this->maxLimitNoise->getCount();
		for($octave = 0; $octave < $octaveCount; $octave++){
			$sampleX = $x * $scaledXZ * $frequency;
			$sampleY = $y * $scaledY * $frequency;
			$sampleZ = $z * $scaledXZ * $frequency;
			$sampleSmear = $smearScale * $frequency;
			if(!$useOnlyMax){
				$sampler = $this->minLimitNoise->getOctave($octave);
				if($sampler !== null){
					$minValue += $sampler->sample($sampleX, $sampleY, $sampleZ, $sampleSmear, $sampleY) / $frequency;
				}
			}
			if(!$useOnlyMin){
				$sampler = $this->maxLimitNoise->getOctave($octave);
				if($sampler !== null){
					$maxValue += $sampler->sample($sampleX, $sampleY, $sampleZ, $sampleSmear, $sampleY) / $frequency;
				}
			}
			$frequency /= 2.0;
		}

		$lower = $minValue / self::LIMIT_NOISE_DIVISOR;
		$upper = $maxValue / self::LIMIT_NOISE_DIVISOR;
		return ($lower + $blend * ($upper - $lower)) / self::RESULT_DIVISOR;
	}

	public function minValue() : float{
		return -1.5;
	}

	public function maxValue() : float{
		return 1.5;
	}
}
