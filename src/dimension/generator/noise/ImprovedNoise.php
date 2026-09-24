<?php

declare(strict_types=1);

namespace dimension\generator\noise;

use dimension\generator\random\RandomSource;

/**
 * Single octave improved perlin noise that fills whole grids of samples at
 * once.
 */
final class ImprovedNoise{

	private const GRAD_X = [1.0, -1.0, 1.0, -1.0, 1.0, -1.0, 1.0, -1.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0, -1.0, 0.0];
	private const GRAD_Y = [1.0, 1.0, -1.0, -1.0, 0.0, 0.0, 0.0, 0.0, 1.0, -1.0, 1.0, -1.0, 1.0, -1.0, 1.0, -1.0];
	private const GRAD_Z = [0.0, 0.0, 0.0, 0.0, 1.0, 1.0, -1.0, -1.0, 1.0, 1.0, -1.0, -1.0, 0.0, 1.0, 0.0, -1.0];
	private const GRAD_2X = [1.0, -1.0, 1.0, -1.0, 1.0, -1.0, 1.0, -1.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0, -1.0, 0.0];
	private const GRAD_2Z = [0.0, 0.0, 0.0, 0.0, 1.0, 1.0, -1.0, -1.0, 1.0, 1.0, -1.0, -1.0, 0.0, 1.0, 0.0, -1.0];

	/** @var int[] 512 entries, the second half repeating the first */
	private array $permutations = [];
	public float $xCoord;
	public float $yCoord;
	public float $zCoord;

	public function __construct(RandomSource $random){
		$this->xCoord = $random->nextDouble() * 256.0;
		$this->yCoord = $random->nextDouble() * 256.0;
		$this->zCoord = $random->nextDouble() * 256.0;
		$p = [];
		for($i = 0; $i < 512; $i++){
			$p[$i] = $i < 256 ? $i : 0;
		}
		for($l = 0; $l < 256; ++$l){
			$j = $random->nextBoundedInt(255 - $l) + $l;
			$k = $p[$l];
			$p[$l] = $p[$j];
			$p[$j] = $k;
			$p[$l + 256] = $p[$l];
		}
		$this->permutations = $p;
	}

	public function lerp(float $delta, float $start, float $end) : float{
		return $start + $delta * ($end - $start);
	}

	public function grad2(int $hash, float $x, float $z) : float{
		$i = $hash & 15;
		return self::GRAD_2X[$i] * $x + self::GRAD_2Z[$i] * $z;
	}

	public function grad(int $hash, float $x, float $y, float $z) : float{
		$i = $hash & 15;
		return self::GRAD_X[$i] * $x + self::GRAD_Y[$i] * $y + self::GRAD_Z[$i] * $z;
	}

	/**
	 * Adds this octave into $noiseArray, a grid of xSize * zSize * ySize
	 * samples indexed ((x * zSize) + z) * ySize + y, each sample divided by
	 * $noiseScale.
	 *
	 * @param float[] $noiseArray
	 */
	public function populateNoiseArray(array &$noiseArray, float $xOffset, float $yOffset, float $zOffset, int $xSize, int $ySize, int $zSize, float $xScale, float $yScale, float $zScale, float $noiseScale) : void{
		$p = $this->permutations;
		if($ySize === 1){
			$index = 0;
			$d16 = 1.0 / $noiseScale;
			for($j2 = 0; $j2 < $xSize; ++$j2){
				$d17 = $xOffset + $j2 * $xScale + $this->xCoord;
				$i6 = (int) $d17;
				if($d17 < $i6){
					--$i6;
				}
				$k2 = $i6 & 255;
				$d17 -= $i6;
				$d18 = $d17 * $d17 * $d17 * ($d17 * ($d17 * 6.0 - 15.0) + 10.0);
				for($j6 = 0; $j6 < $zSize; ++$j6){
					$d19 = $zOffset + $j6 * $zScale + $this->zCoord;
					$k6 = (int) $d19;
					if($d19 < $k6){
						--$k6;
					}
					$l6 = $k6 & 255;
					$d19 -= $k6;
					$d20 = $d19 * $d19 * $d19 * ($d19 * ($d19 * 6.0 - 15.0) + 10.0);
					$i5 = $p[$k2];
					$j5 = $p[$i5] + $l6;
					$j = $p[$k2 + 1];
					$k5 = $p[$j] + $l6;
					$d14 = $this->lerp($d18, $this->grad2($p[$j5], $d17, $d19), $this->grad($p[$k5], $d17 - 1.0, 0.0, $d19));
					$d15 = $this->lerp($d18, $this->grad($p[$j5 + 1], $d17, 0.0, $d19 - 1.0), $this->grad($p[$k5 + 1], $d17 - 1.0, 0.0, $d19 - 1.0));
					$d21 = $this->lerp($d20, $d14, $d15);
					$noiseArray[$index++] += $d21 * $d16;
				}
			}
			return;
		}

		$index = 0;
		$d0 = 1.0 / $noiseScale;
		$k = -1;
		$d1 = 0.0;
		$d2 = 0.0;
		$d3 = 0.0;
		$d4 = 0.0;
		for($l2 = 0; $l2 < $xSize; ++$l2){
			$d5 = $xOffset + $l2 * $xScale + $this->xCoord;
			$i3 = (int) $d5;
			if($d5 < $i3){
				--$i3;
			}
			$j3 = $i3 & 255;
			$d5 -= $i3;
			$d6 = $d5 * $d5 * $d5 * ($d5 * ($d5 * 6.0 - 15.0) + 10.0);
			for($k3 = 0; $k3 < $zSize; ++$k3){
				$d7 = $zOffset + $k3 * $zScale + $this->zCoord;
				$l3 = (int) $d7;
				if($d7 < $l3){
					--$l3;
				}
				$i4 = $l3 & 255;
				$d7 -= $l3;
				$d8 = $d7 * $d7 * $d7 * ($d7 * ($d7 * 6.0 - 15.0) + 10.0);
				for($j4 = 0; $j4 < $ySize; ++$j4){
					$d9 = $yOffset + $j4 * $yScale + $this->yCoord;
					$k4 = (int) $d9;
					if($d9 < $k4){
						--$k4;
					}
					$l4 = $k4 & 255;
					$d9 -= $k4;
					$d10 = $d9 * $d9 * $d9 * ($d9 * ($d9 * 6.0 - 15.0) + 10.0);
					if($j4 === 0 || $l4 !== $k){
						$k = $l4;
						$l = $p[$j3] + $l4;
						$i1 = $p[$l] + $i4;
						$j1 = $p[$l + 1] + $i4;
						$k1 = $p[$j3 + 1] + $l4;
						$l1 = $p[$k1] + $i4;
						$i2 = $p[$k1 + 1] + $i4;
						$d1 = $this->lerp($d6, $this->grad($p[$i1], $d5, $d9, $d7), $this->grad($p[$l1], $d5 - 1.0, $d9, $d7));
						$d2 = $this->lerp($d6, $this->grad($p[$j1], $d5, $d9 - 1.0, $d7), $this->grad($p[$i2], $d5 - 1.0, $d9 - 1.0, $d7));
						$d3 = $this->lerp($d6, $this->grad($p[$i1 + 1], $d5, $d9, $d7 - 1.0), $this->grad($p[$l1 + 1], $d5 - 1.0, $d9, $d7 - 1.0));
						$d4 = $this->lerp($d6, $this->grad($p[$j1 + 1], $d5, $d9 - 1.0, $d7 - 1.0), $this->grad($p[$i2 + 1], $d5 - 1.0, $d9 - 1.0, $d7 - 1.0));
					}
					$d11 = $this->lerp($d10, $d1, $d2);
					$d12 = $this->lerp($d10, $d3, $d4);
					$d13 = $this->lerp($d8, $d11, $d12);
					$noiseArray[$index++] += $d13 * $d0;
				}
			}
		}
	}
}
