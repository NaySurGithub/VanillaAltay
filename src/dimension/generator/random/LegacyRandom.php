<?php

declare(strict_types=1);

namespace dimension\generator\random;

use dimension\generator\math\Float32;
use dimension\generator\math\Int64;
use function cos;
use function count;
use function log;
use function max;
use function microtime;
use function min;
use function mt_getrandmax;
use function mt_rand;
use function sqrt;
use const M_PI;

/**
 * MT19937 Mersenne Twister whose 624-word state is filled from a 64-bit seed
 * (the seed is expanded to 624 ints with a SplitMix64 style mixer, then
 * mixed in with the standard init_by_array procedure).
 *
 * Method mapping: nextInt() is a full signed 32-bit int, nextInt($max) is a
 * uniform int in [0, $max] (inclusive), nextRangeInt($min, $max) and
 * nextRange($min, $max) are uniform ints in [$min, $max] (inclusive), and
 * nextBoundedInt($max) is nextInt($max). A bound below the minimum throws
 * \InvalidArgumentException.
 *
 * nextGaussian() is not reproducible from the seed: it draws from an
 * independent, unseeded source, is gaussian with a standard deviation of
 * 1/3 and is clamped to [-1, 1].
 */
final class LegacyRandom implements RandomSource{

	private const N = 624;
	private const M = 397;
	private const MASK_32 = 0xFFFFFFFF;
	private const UPPER_MASK = 0x80000000;
	private const LOWER_MASK = 0x7FFFFFFF;
	private const MATRIX_A = 0x9908B0DF;
	private const GOLDEN_RATIO = -7046029254386353131;
	private const MIX_1 = -4658895280553007687;
	private const MIX_2 = -7723592293110705685;
	private const EMPTY_BOOLEAN_SOURCE = 1;

	private int $seed = 0;
	/** @var int[] unsigned 32-bit words */
	private array $mt = [];
	private int $mti = self::N;
	private int $booleanSource = self::EMPTY_BOOLEAN_SOURCE;

	public function __construct(?int $seed = null){
		$this->setSeed($seed ?? (int) (microtime(true) * 1000));
	}

	public function fork() : LegacyRandom{
		return new LegacyRandom($this->nextLong());
	}

	public function identical() : LegacyRandom{
		return new LegacyRandom($this->seed);
	}

	public function setSeed(int $seed) : LegacyRandom{
		$this->seed = $seed;
		$this->fillState(self::expandSeed($seed));
		$this->mti = self::N;
		$this->booleanSource = self::EMPTY_BOOLEAN_SOURCE;
		return $this;
	}

	public function getSeed() : int{
		return $this->seed;
	}

	public function nextInt(?int $max = null) : int{
		if($max === null){
			return $this->next();
		}
		return $this->nextIntBounded(Int64::toInt32($max + 1));
	}

	public function nextRangeInt(int $min, int $max) : int{
		return $this->nextIntRange($min, Int64::toInt32($max + 1));
	}

	public function nextRange(int $min, int $max) : int{
		return $this->nextIntRange($min, Int64::toInt32($max + 1));
	}

	public function nextBoundedInt(int $max) : int{
		return $this->nextInt($max);
	}

	public function nextLong() : int{
		$high = $this->next();
		$low = $this->next();
		return ($high << 32) | ($low & self::MASK_32);
	}

	public function nextBoolean() : bool{
		$bits = $this->booleanSource;
		if($bits === 1){
			$bits = $this->next();
			$this->booleanSource = Int64::toInt32(self::UPPER_MASK | (($bits & self::MASK_32) >> 1));
			return ($bits & 1) === 1;
		}
		$this->booleanSource = ($bits & self::MASK_32) >> 1;
		return ($bits & 1) === 1;
	}

	public function nextFloat() : float{
		return Float32::of((($this->next() & self::MASK_32) >> 8) * (1.0 / 16777216));
	}

	public function nextDouble() : float{
		$v = $this->next() & self::MASK_32;
		$w = $this->next() & self::MASK_32;
		return ((($v >> 6) << 27) | ($w >> 5)) * (1.0 / 9007199254740992);
	}

	public function nextGaussian() : float{
		$scale = mt_getrandmax() + 1.0;
		$u1 = (mt_rand() + 1.0) / $scale;
		$u2 = mt_rand() / $scale;
		$sample = sqrt(-2.0 * log($u1)) * cos(2 * M_PI * $u2) * 0.33333;
		return min(1.0, max($sample, -1.0));
	}

	/**
	 * Uniform int in [0, $bound), $bound being a positive 32-bit int.
	 */
	private function nextIntBounded(int $bound) : int{
		if($bound <= 0){
			throw new \InvalidArgumentException("Upper bound $bound must be above zero");
		}
		$m = ($this->next() & self::MASK_32) * $bound;
		$l = $m & self::MASK_32;
		if($l < $bound){
			$t = 4294967296 % $bound;
			while($l < $t){
				$m = ($this->next() & self::MASK_32) * $bound;
				$l = $m & self::MASK_32;
			}
		}
		return $m >> 32;
	}

	/**
	 * Uniform int in [$origin, $bound).
	 */
	private function nextIntRange(int $origin, int $bound) : int{
		if($origin >= $bound){
			throw new \InvalidArgumentException("Range [$origin, $bound) is empty");
		}
		$n = Int64::toInt32($bound - $origin);
		if($n > 0){
			return $this->nextIntBounded($n) + $origin;
		}
		$v = $this->next();
		while($v < $origin || $v >= $bound){
			$v = $this->next();
		}
		return $v;
	}

	/**
	 * Next tempered 32-bit output as a signed int.
	 */
	private function next() : int{
		$mt = &$this->mt;
		if($this->mti >= self::N){
			for($k = 0; $k < self::N - self::M; ++$k){
				$y = ($mt[$k] & self::UPPER_MASK) | ($mt[$k + 1] & self::LOWER_MASK);
				$mt[$k] = $mt[$k + self::M] ^ ($y >> 1) ^ (($y & 1) === 0 ? 0 : self::MATRIX_A);
			}
			for($k = self::N - self::M; $k < self::N - 1; ++$k){
				$y = ($mt[$k] & self::UPPER_MASK) | ($mt[$k + 1] & self::LOWER_MASK);
				$mt[$k] = $mt[$k + (self::M - self::N)] ^ ($y >> 1) ^ (($y & 1) === 0 ? 0 : self::MATRIX_A);
			}
			$y = ($mt[self::N - 1] & self::UPPER_MASK) | ($mt[0] & self::LOWER_MASK);
			$mt[self::N - 1] = $mt[self::M - 1] ^ ($y >> 1) ^ (($y & 1) === 0 ? 0 : self::MATRIX_A);
			$this->mti = 0;
		}

		$y = $mt[$this->mti++];
		$y ^= $y >> 11;
		$y ^= ($y << 7) & 0x9D2C5680;
		$y ^= ($y << 15) & 0xEFC60000;
		$y ^= $y >> 18;
		return Int64::toInt32($y);
	}

	/**
	 * Expands a 64-bit seed into the 624 ints fed to the state initialisation.
	 *
	 * @return int[] signed 32-bit ints
	 */
	private static function expandSeed(int $input) : array{
		$v = $input === Int64::sub(0, self::GOLDEN_RATIO) ? ~$input : $input;
		$output = [];
		for($i = 0; $i < self::N; $i += 2){
			$v = Int64::add($v, self::GOLDEN_RATIO);
			$x = self::mix($v);
			$output[$i] = Int64::toInt32($x);
			$output[$i + 1] = Int64::toInt32(Int64::urshift($x, 32));
		}
		return $output;
	}

	private static function mix(int $x) : int{
		$x = Int64::mul($x ^ Int64::urshift($x, 30), self::MIX_1);
		$x = Int64::mul($x ^ Int64::urshift($x, 27), self::MIX_2);
		return $x ^ Int64::urshift($x, 31);
	}

	/**
	 * @param int[] $seed signed 32-bit ints
	 */
	private function fillState(array $seed) : void{
		$state = [];
		$mt = 19650218;
		$state[0] = $mt;
		for($i = 1; $i < self::N; $i++){
			$mt = (1812433253 * ($mt ^ ($mt >> 30)) + $i) & self::MASK_32;
			$state[$i] = $mt;
		}

		$seedLength = count($seed);
		$i = 1;
		$j = 0;
		for($k = max(self::N, $seedLength); $k > 0; $k--){
			$a = $state[$i];
			$b = $state[$i - 1];
			$c = ($a ^ (($b ^ ($b >> 30)) * 1664525)) + $seed[$j] + $j;
			$state[$i] = $c & self::MASK_32;
			$i++;
			$j++;
			if($i >= self::N){
				$state[0] = $state[self::N - 1];
				$i = 1;
			}
			if($j >= $seedLength){
				$j = 0;
			}
		}

		for($k = self::N - 1; $k > 0; $k--){
			$a = $state[$i];
			$b = $state[$i - 1];
			$c = ($a ^ (($b ^ ($b >> 30)) * 1566083941)) - $i;
			$state[$i] = $c & self::MASK_32;
			$i++;
			if($i >= self::N){
				$state[0] = $state[self::N - 1];
				$i = 1;
			}
		}

		$state[0] = self::UPPER_MASK;
		$this->mt = $state;
	}
}
