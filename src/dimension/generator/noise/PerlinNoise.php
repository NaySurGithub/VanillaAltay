<?php

declare(strict_types=1);

namespace dimension\generator\noise;

use dimension\generator\math\GenerationMath;
use dimension\generator\random\RandomSource;
use function count;

/**
 * Multi octave perlin noise. Octave i has the frequency 2^(firstOctave + i)
 * and the weight amplitudes[i]; octaves with a zero amplitude are skipped
 * but still consume their share of the random sequence.
 */
final class PerlinNoise{

	/** @var array<int, PerlinNoiseSampler|null> */
	private array $noiseLevels = [];
	private float $lowestFreqValueFactor;
	private float $lowestFreqInputFactor;
	private float $maxValue;

	/**
	 * @param float[] $amplitudes
	 */
	public function __construct(RandomSource $random, int $firstOctave, private array $amplitudes){
		$octaves = count($amplitudes);
		$zeroOctaveIndex = -$firstOctave;
		for($i = 0; $i < $octaves; $i++){
			$this->noiseLevels[$i] = null;
		}

		$zeroOctave = new PerlinNoiseSampler($random);
		if($zeroOctaveIndex >= 0 && $zeroOctaveIndex < $octaves && $amplitudes[$zeroOctaveIndex] != 0.0){
			$this->noiseLevels[$zeroOctaveIndex] = $zeroOctave;
		}

		for($ix = $zeroOctaveIndex - 1; $ix >= 0; --$ix){
			if($ix < $octaves && $amplitudes[$ix] != 0.0){
				$this->noiseLevels[$ix] = new PerlinNoiseSampler($random);
			}else{
				$random->setSeed(Lcg::skip262()->nextSeed($random->getSeed()));
			}
		}

		$this->lowestFreqInputFactor = 2.0 ** (-$zeroOctaveIndex);
		$this->lowestFreqValueFactor = 2.0 ** ($octaves - 1) / (2.0 ** $octaves - 1.0);
		$this->maxValue = $this->edgeValue(2.0);
	}

	public function getValue(float $x, float $y, float $z) : float{
		$value = 0.0;
		$factor = $this->lowestFreqInputFactor;
		$valueFactor = $this->lowestFreqValueFactor;
		foreach($this->noiseLevels as $i => $noise){
			if($noise !== null){
				$noiseValue = $noise->sample(
					GenerationMath::maintainPrecision($x * $factor),
					GenerationMath::maintainPrecision($y * $factor),
					GenerationMath::maintainPrecision($z * $factor),
					0.0,
					0.0
				);
				$value += $this->amplitudes[$i] * $noiseValue * $valueFactor;
			}
			$factor *= 2.0;
			$valueFactor /= 2.0;
		}
		return $value;
	}

	public function maxValue() : float{
		return $this->maxValue;
	}

	private function edgeValue(float $noiseValue) : float{
		$value = 0.0;
		$valueFactor = $this->lowestFreqValueFactor;
		foreach($this->noiseLevels as $i => $noise){
			if($noise !== null){
				$value += $this->amplitudes[$i] * $noiseValue * $valueFactor;
			}
			$valueFactor /= 2.0;
		}
		return $value;
	}
}
