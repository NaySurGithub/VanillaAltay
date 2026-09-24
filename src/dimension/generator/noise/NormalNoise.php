<?php

declare(strict_types=1);

namespace dimension\generator\noise;

use dimension\generator\math\Float32;
use dimension\generator\random\RandomSource;
use function max;
use function min;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * Sum of two multi octave perlin noises (the second sampled at a slightly
 * larger scale), normalised so its values roughly spread over [-1, 1].
 * Values are single precision.
 */
final class NormalNoise{

	private const INPUT_FACTOR = 1.0181268882175227;

	private PerlinNoise $first;
	private PerlinNoise $second;
	private float $valueFactor;
	private float $maxValue;

	/**
	 * @param float[] $amplitudes single precision amplitudes, one per octave
	 */
	public function __construct(RandomSource $random, int $firstOctave, array $amplitudes){
		$octaveAmplitudes = [];
		$minIndex = PHP_INT_MAX;
		$maxIndex = PHP_INT_MIN;
		foreach($amplitudes as $i => $amplitude){
			$amplitude = Float32::of((float) $amplitude);
			$octaveAmplitudes[$i] = $amplitude;
			if($amplitude != 0.0){
				$minIndex = min($minIndex, $i);
				$maxIndex = max($maxIndex, $i);
			}
		}
		$this->first = new PerlinNoise($random, $firstOctave, $octaveAmplitudes);
		$this->second = new PerlinNoise($random, $firstOctave, $octaveAmplitudes);
		$this->valueFactor = 1.0 / 6.0 / self::expectedDeviation($maxIndex - $minIndex);
		$this->maxValue = ($this->first->maxValue() + $this->second->maxValue()) * $this->valueFactor;
	}

	public function getValue(float $x, float $y, float $z) : float{
		$x2 = $x * self::INPUT_FACTOR;
		$y2 = $y * self::INPUT_FACTOR;
		$z2 = $z * self::INPUT_FACTOR;
		return Float32::of(($this->first->getValue($x, $y, $z) + $this->second->getValue($x2, $y2, $z2)) * $this->valueFactor);
	}

	public function getMax() : float{
		return $this->maxValue;
	}

	private static function expectedDeviation(int $octaveSpan) : float{
		return 0.1 * (1.0 + 1.0 / ($octaveSpan + 1.0));
	}
}
