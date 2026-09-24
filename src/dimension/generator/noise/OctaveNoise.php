<?php

declare(strict_types=1);

namespace dimension\generator\noise;

use dimension\generator\math\GenerationMath;
use dimension\generator\random\RandomSource;
use function array_fill;

/**
 * Several ImprovedNoise octaves summed over a grid, each octave at half the
 * frequency and twice the amplitude of the previous one.
 */
final class OctaveNoise{

	/** @var ImprovedNoise[] */
	private array $generatorCollection = [];

	public function __construct(RandomSource $random, private int $octaves){
		for($i = 0; $i < $octaves; ++$i){
			$this->generatorCollection[$i] = new ImprovedNoise($random);
		}
	}

	/**
	 * Grid of xSize * zSize * ySize samples indexed ((x * zSize) + z) * ySize + y.
	 *
	 * @return float[]
	 */
	public function generateNoiseOctaves(int $xOffset, int $yOffset, int $zOffset, int $xSize, int $ySize, int $zSize, float $xScale, float $yScale, float $zScale) : array{
		$noiseArray = array_fill(0, $xSize * $ySize * $zSize, 0.0);
		$d3 = 1.0;
		for($j = 0; $j < $this->octaves; ++$j){
			$d0 = $xOffset * $d3 * $xScale;
			$d1 = $yOffset * $d3 * $yScale;
			$d2 = $zOffset * $d3 * $zScale;
			$k = GenerationMath::floor_double_long($d0);
			$l = GenerationMath::floor_double_long($d2);
			$d0 -= $k;
			$d2 -= $l;
			$k %= 16777216;
			$l %= 16777216;
			$d0 += $k;
			$d2 += $l;
			$this->generatorCollection[$j]->populateNoiseArray($noiseArray, $d0, $d1, $d2, $xSize, $ySize, $zSize, $xScale * $d3, $yScale * $d3, $zScale * $d3, $d3);
			$d3 /= 2.0;
		}
		return $noiseArray;
	}

	/**
	 * Single layer grid at Y = 10.
	 *
	 * @return float[]
	 */
	public function generateNoiseOctaves2D(int $xOffset, int $zOffset, int $xSize, int $zSize, float $xScale, float $zScale) : array{
		return $this->generateNoiseOctaves($xOffset, 10, $zOffset, $xSize, 1, $zSize, $xScale, 1.0, $zScale);
	}
}
