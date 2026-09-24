<?php

declare(strict_types=1);

namespace dimension\generator\noise;

use dimension\generator\math\Int64;
use dimension\generator\random\RandomSource;
use function floor;

/**
 * Single octave 3D improved perlin noise with a random origin and a
 * shuffled 256 entry permutation table.
 */
final class PerlinNoiseSampler{

	private const GRADIENTS = [
		1, 1, 0, 0,
		-1, 1, 0, 0,
		1, -1, 0, 0,
		-1, -1, 0, 0,
		1, 0, 1, 0,
		-1, 0, 1, 0,
		1, 0, -1, 0,
		-1, 0, -1, 0,
		0, 1, 1, 0,
		0, -1, 1, 0,
		0, 1, -1, 0,
		0, -1, -1, 0,
		1, 1, 0, 0,
		0, -1, 1, 0,
		-1, 1, 0, 0,
		0, -1, -1, 0
	];

	public readonly float $originX;
	public readonly float $originY;
	public readonly float $originZ;
	/** @var int[] 0-255 */
	private array $permutations = [];

	public function __construct(RandomSource $random){
		$this->originX = $random->nextDouble() * 256.0;
		$this->originY = $random->nextDouble() * 256.0;
		$this->originZ = $random->nextDouble() * 256.0;
		$permutations = [];
		for($j = 0; $j < 256; ++$j){
			$permutations[$j] = $j;
		}
		for($index = 0; $index < 256; ++$index){
			$randomIndex = $random->nextBoundedInt(255 - $index) + $index;
			$temp = $permutations[$index];
			$permutations[$index] = $permutations[$randomIndex];
			$permutations[$randomIndex] = $temp;
		}
		$this->permutations = $permutations;
	}

	/**
	 * @param float $yAmplification when not zero, the local Y is snapped down to a multiple of it
	 * @param float $minY           upper limit of the local Y used for that snapping, ignored when negative
	 */
	public function sample(float $x, float $y, float $z, float $yAmplification, float $minY) : float{
		$offsetX = $x + $this->originX;
		$offsetY = $y + $this->originY;
		$offsetZ = $z + $this->originZ;
		$floorX = floor($offsetX);
		$floorY = floor($offsetY);
		$floorZ = floor($offsetZ);
		$localX = $offsetX - $floorX;
		$localY = $offsetY - $floorY;
		$localZ = $offsetZ - $floorZ;
		$yOffset = 0.0;
		if($yAmplification != 0.0){
			$yClamp = $minY >= 0.0 && $minY < $localY ? $minY : $localY;
			$yOffset = floor($yClamp / $yAmplification + 1.0E-7) * $yAmplification;
		}
		return $this->sampleSection(
			Int64::doubleToInt32($floorX),
			Int64::doubleToInt32($floorY),
			Int64::doubleToInt32($floorZ),
			$localX,
			$localY - $yOffset,
			$localZ,
			$localY
		);
	}

	private function sampleSection(int $sectionX, int $sectionY, int $sectionZ, float $localX, float $localY, float $localZ, float $fadeLocalY) : float{
		$p = $this->permutations;
		$g = self::GRADIENTS;
		$a = $p[$sectionX & 0xFF];
		$b = $p[($sectionX + 1) & 0xFF];
		$aa = $p[($a + $sectionY) & 0xFF];
		$ba = $p[($b + $sectionY) & 0xFF];
		$ab = $p[($a + $sectionY + 1) & 0xFF];
		$bb = $p[($b + $sectionY + 1) & 0xFF];

		$h000 = ($p[($aa + $sectionZ) & 0xFF] & 15) << 2;
		$h100 = ($p[($ba + $sectionZ) & 0xFF] & 15) << 2;
		$h010 = ($p[($ab + $sectionZ) & 0xFF] & 15) << 2;
		$h110 = ($p[($bb + $sectionZ) & 0xFF] & 15) << 2;
		$h001 = ($p[($aa + $sectionZ + 1) & 0xFF] & 15) << 2;
		$h101 = ($p[($ba + $sectionZ + 1) & 0xFF] & 15) << 2;
		$h011 = ($p[($ab + $sectionZ + 1) & 0xFF] & 15) << 2;
		$h111 = ($p[($bb + $sectionZ + 1) & 0xFF] & 15) << 2;

		$xMinusOne = $localX - 1.0;
		$yMinusOne = $localY - 1.0;
		$zMinusOne = $localZ - 1.0;
		$grad000 = $g[$h000] * $localX + $g[$h000 | 1] * $localY + $g[$h000 | 2] * $localZ;
		$grad100 = $g[$h100] * $xMinusOne + $g[$h100 | 1] * $localY + $g[$h100 | 2] * $localZ;
		$grad010 = $g[$h010] * $localX + $g[$h010 | 1] * $yMinusOne + $g[$h010 | 2] * $localZ;
		$grad110 = $g[$h110] * $xMinusOne + $g[$h110 | 1] * $yMinusOne + $g[$h110 | 2] * $localZ;
		$grad001 = $g[$h001] * $localX + $g[$h001 | 1] * $localY + $g[$h001 | 2] * $zMinusOne;
		$grad101 = $g[$h101] * $xMinusOne + $g[$h101 | 1] * $localY + $g[$h101 | 2] * $zMinusOne;
		$grad011 = $g[$h011] * $localX + $g[$h011 | 1] * $yMinusOne + $g[$h011 | 2] * $zMinusOne;
		$grad111 = $g[$h111] * $xMinusOne + $g[$h111 | 1] * $yMinusOne + $g[$h111 | 2] * $zMinusOne;

		$fadeX = $localX * $localX * $localX * ($localX * ($localX * 6.0 - 15.0) + 10.0);
		$fadeY = $fadeLocalY * $fadeLocalY * $fadeLocalY * ($fadeLocalY * ($fadeLocalY * 6.0 - 15.0) + 10.0);
		$fadeZ = $localZ * $localZ * $localZ * ($localZ * ($localZ * 6.0 - 15.0) + 10.0);

		$x00 = $grad000 + $fadeX * ($grad100 - $grad000);
		$x10 = $grad010 + $fadeX * ($grad110 - $grad010);
		$x01 = $grad001 + $fadeX * ($grad101 - $grad001);
		$x11 = $grad011 + $fadeX * ($grad111 - $grad011);
		$y0 = $x00 + $fadeY * ($x10 - $x00);
		$y1 = $x01 + $fadeY * ($x11 - $x01);
		return $y0 + $fadeZ * ($y1 - $y0);
	}
}
