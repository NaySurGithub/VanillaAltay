<?php

declare(strict_types=1);

namespace dimension\generator\random;

use dimension\generator\math\Float32;
use dimension\generator\math\Int64;
use function cos;
use function log;
use function microtime;
use function sqrt;
use const M_PI;

/**
 * Xoroshiro128 generator whose 128-bit state is expanded from a 64-bit seed
 * with SplitMix64.
 *
 * Method mapping: nextInt() returns a non negative 31-bit int, nextInt($max)
 * returns nextInt() % $max (0 when $max is 0), nextRangeInt($min, $max)
 * returns $min + nextInt() % ($max - $min) (upper bound exclusive), and
 * nextBoundedInt($max) is nextInt($max + 1) (upper bound inclusive).
 */
final class Xoroshiro128 implements RandomSource{

	private const GOLDEN_GAMMA = -7046029254386353131;
	private const MIX_1 = -4658895280553007687;
	private const MIX_2 = -7723592293110705685;

	private int $seed = 0;
	private int $s0 = 0;
	private int $s1 = 0;

	public function __construct(?int $seed = null){
		$this->setSeed($seed ?? (int) (microtime(true) * 1000000000));
	}

	public function fork() : Xoroshiro128{
		return new Xoroshiro128($this->nextLong());
	}

	public function identical() : Xoroshiro128{
		return new Xoroshiro128($this->seed);
	}

	public function nextInt(?int $max = null) : int{
		$value = $this->nextLong() & 0x7FFFFFFF;
		if($max === null){
			return $value;
		}
		if($max === 0){
			return 0;
		}
		return $value % $max;
	}

	public function nextRangeInt(int $min, int $max) : int{
		return Int64::toInt32($min + ($this->nextInt() % Int64::toInt32($max - $min)));
	}

	public function nextBoundedInt(int $max) : int{
		return $this->nextInt(Int64::toInt32($max + 1));
	}

	public function nextLong() : int{
		$i = $this->s0;
		$j = $this->s1;
		$k = Int64::add(Int64::rotateLeft(Int64::add($i, $j), 17), $i);
		$j ^= $i;
		$this->s0 = Int64::rotateLeft($i, 49) ^ $j ^ ($j << 21);
		$this->s1 = Int64::rotateLeft($j, 28);
		return $k;
	}

	public function nextBoolean() : bool{
		return ($this->nextLong() & 1) !== 0;
	}

	public function nextFloat() : float{
		return Float32::of(Int64::urshift($this->nextLong(), 40) * (1.0 / 16777216));
	}

	public function nextDouble() : float{
		return Int64::urshift($this->nextLong(), 11) * (1.0 / 9007199254740992);
	}

	public function nextGaussian() : float{
		$u1 = $this->nextDouble();
		$u2 = $this->nextDouble();
		return sqrt(-2.0 * log($u1)) * cos(2 * M_PI * $u2);
	}

	public function setSeed(int $seed) : Xoroshiro128{
		$this->seed = $seed;
		$z = $seed;
		$out = [0, 0];
		for($i = 0; $i < 2; $i++){
			$z = Int64::add($z, self::GOLDEN_GAMMA);
			$r = $z;
			$r = Int64::mul($r ^ Int64::urshift($r, 30), self::MIX_1);
			$r = Int64::mul($r ^ Int64::urshift($r, 27), self::MIX_2);
			$r ^= Int64::urshift($r, 31);
			$out[$i] = $r;
		}
		if($out[0] === 0 && $out[1] === 0){
			$out[0] = self::GOLDEN_GAMMA;
			$out[1] = ~self::GOLDEN_GAMMA;
		}
		$this->s0 = $out[0];
		$this->s1 = $out[1];
		return $this;
	}

	public function getSeed() : int{
		return $this->seed;
	}
}
