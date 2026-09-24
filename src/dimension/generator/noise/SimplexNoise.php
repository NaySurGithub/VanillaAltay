<?php

declare(strict_types=1);

namespace dimension\generator\noise;

use dimension\generator\random\RandomSource;
use function sqrt;

/**
 * 2D simplex noise with a random origin and a shuffled permutation table.
 */
final class SimplexNoise{

	private const GRAD3 = [[1, 1, 0], [-1, 1, 0], [1, -1, 0], [-1, -1, 0], [1, 0, 1], [-1, 0, 1], [1, 0, -1], [-1, 0, -1], [0, 1, 1], [0, -1, 1], [0, 1, -1], [0, -1, -1]];

	private static ?float $sqrt3 = null;

	/** @var int[] 512 entries, the second half repeating the first */
	private array $p = [];
	public float $xo;
	public float $yo;
	public float $zo;

	public function __construct(RandomSource $random){
		$this->xo = $random->nextDouble() * 256.0;
		$this->yo = $random->nextDouble() * 256.0;
		$this->zo = $random->nextDouble() * 256.0;
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
		$this->p = $p;
	}

	private static function fastFloor(float $value) : int{
		return $value > 0.0 ? (int) $value : (int) $value - 1;
	}

	/**
	 * @param int[] $gradient
	 */
	private static function dot(array $gradient, float $x, float $y) : float{
		return $gradient[0] * $x + $gradient[1] * $y;
	}

	public function getValue(float $x, float $y) : float{
		$sqrt3 = self::$sqrt3 ??= sqrt(3.0);
		$f2 = 0.5 * ($sqrt3 - 1.0);
		$s = ($x + $y) * $f2;
		$i = self::fastFloor($x + $s);
		$j = self::fastFloor($y + $s);
		$g2 = (3.0 - $sqrt3) / 6.0;
		$t = ($i + $j) * $g2;
		$x0 = $x - ($i - $t);
		$y0 = $y - ($j - $t);
		if($x0 > $y0){
			$i1 = 1;
			$j1 = 0;
		}else{
			$i1 = 0;
			$j1 = 1;
		}
		$x1 = $x0 - $i1 + $g2;
		$y1 = $y0 - $j1 + $g2;
		$x2 = $x0 - 1.0 + 2.0 * $g2;
		$y2 = $y0 - 1.0 + 2.0 * $g2;
		$ii = $i & 255;
		$jj = $j & 255;
		$p = $this->p;
		$gi0 = $p[$ii + $p[$jj]] % 12;
		$gi1 = $p[$ii + $i1 + $p[$jj + $j1]] % 12;
		$gi2 = $p[$ii + 1 + $p[$jj + 1]] % 12;

		$t0 = 0.5 - $x0 * $x0 - $y0 * $y0;
		if($t0 < 0.0){
			$n0 = 0.0;
		}else{
			$t0 *= $t0;
			$n0 = $t0 * $t0 * self::dot(self::GRAD3[$gi0], $x0, $y0);
		}
		$t1 = 0.5 - $x1 * $x1 - $y1 * $y1;
		if($t1 < 0.0){
			$n1 = 0.0;
		}else{
			$t1 *= $t1;
			$n1 = $t1 * $t1 * self::dot(self::GRAD3[$gi1], $x1, $y1);
		}
		$t2 = 0.5 - $x2 * $x2 - $y2 * $y2;
		if($t2 < 0.0){
			$n2 = 0.0;
		}else{
			$t2 *= $t2;
			$n2 = $t2 * $t2 * self::dot(self::GRAD3[$gi2], $x2, $y2);
		}
		return 70.0 * ($n0 + $n1 + $n2);
	}
}
