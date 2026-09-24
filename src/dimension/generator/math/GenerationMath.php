<?php

declare(strict_types=1);

namespace dimension\generator\math;

use dimension\generator\random\RandomSource;
use function abs;
use function intdiv;
use function min;
use function sin;
use function sqrt;

/**
 * Math helpers of the terrain generators.
 *
 * Integer results follow signed 32-bit (int) or 64-bit (long) wrapping as
 * documented per method. Methods documented as single precision round their
 * result to binary32.
 */
final class GenerationMath{

	/** @var float[]|null */
	private static ?array $sinTable = null;

	private function __construct(){
	}

	public static function floorDouble(float $n) : int{
		$i = Int64::doubleToInt32($n);
		return $n >= $i ? $i : $i - 1;
	}

	public static function ceilDouble(float $n) : int{
		$i = Int64::doubleToInt32($n);
		return $n > $i ? $i + 1 : $i;
	}

	public static function floorFloat(float $n) : int{
		return self::floorDouble($n);
	}

	public static function ceilFloat(float $n) : int{
		return self::ceilDouble($n);
	}

	/**
	 * start + nextInt() % (end + 1 - start), 32-bit wrapping. With no bounds
	 * the range is [0, 0x7fffffff]; with only a start it is [start, 0x7fffffff].
	 */
	public static function randomRange(RandomSource $random, int $start = 0, int $end = 0x7fffffff) : int{
		return Int64::toInt32($start + ($random->nextInt() % Int64::toInt32($end + 1 - $start)));
	}

	public static function randomRangeTriangle(RandomSource $random, int $start, int $end) : int{
		$heightDiff = abs($end - $start);
		$heightDiffHalf = intdiv($heightDiff, 2);
		$heightDiffHalf2 = $heightDiff - $heightDiffHalf;
		return min($start, $end) + self::randomRange($random, 0, $heightDiffHalf2) + self::randomRange($random, 0, $heightDiffHalf);
	}

	/**
	 * Clamps $value into [$min, $max]. Returns an int when all arguments are ints.
	 */
	public static function clamp(int|float $value, int|float $min, int|float $max) : int|float{
		return $value < $min ? $min : ($value > $max ? $max : $value);
	}

	/**
	 * Single precision linear remap of $input from [$inMin, $inMax] to [$outMin, $outMax].
	 */
	public static function remap(float $input, float $inMin, float $inMax, float $outMin, float $outMax) : float{
		$a = Float32::of($input - $inMin);
		$b = Float32::of($inMax - $inMin);
		$c = Float32::of($outMax - $outMin);
		return Float32::of($outMin + Float32::of(Float32::of($a / $b) * $c));
	}

	public static function remapNormalized(float $input, float $inMin, float $inMax) : float{
		return self::remap($input, $inMin, $inMax, -1, 1);
	}

	public static function remapFromNormalized(float $input, float $outMin, float $outMax) : float{
		return self::remap($input, -1, 1, $outMin, $outMax);
	}

	/**
	 * Piecewise linear interpolation of $t between the points ($at, $a) and ($bt, $b), clamped at both ends.
	 */
	public static function lerp(float $t, float $at, float $a, float $bt, float $b) : float{
		if($at < $bt){
			if($t <= $at){
				return $a;
			}
			if($t >= $bt){
				return $b;
			}
		}else{
			if($t >= $at){
				return $a;
			}
			if($t <= $bt){
				return $b;
			}
		}
		return $a * ($t - $bt) / ($at - $bt) + $b * ($t - $at) / ($bt - $at);
	}

	public static function isPowerOf2(int $value) : bool{
		return ($value & Int64::sub(0, $value)) === $value;
	}

	public static function getPow2(int $bits) : int{
		return 1 << $bits;
	}

	public static function getMask(int $bits) : int{
		return $bits >= 64 ? ~0 : (1 << $bits) - 1;
	}

	public static function mask(int $value, int $bits) : int{
		return $value & self::getMask($bits);
	}

	/**
	 * Keeps the low $bits bits of $value and sign extends them.
	 */
	public static function maskSigned(int $value, int $bits) : int{
		return ($value << (64 - $bits)) >> (64 - $bits);
	}

	/**
	 * Multiplicative inverse of an odd $value modulo 2^$bits.
	 */
	public static function modInverse(int $value, int $bits = 64) : int{
		$x = (((($value << 1) ^ $value) & 4) << 1) ^ $value;
		for($i = 0; $i < 4; $i++){
			$x = Int64::mul($x, Int64::sub(2, Int64::mul($value, $x)));
		}
		return self::mask($x, $bits);
	}

	/**
	 * Single precision square root.
	 */
	public static function sqrt(float $value) : float{
		return Float32::of(sqrt($value));
	}

	/**
	 * Single precision table sine of an angle in radians (65536 steps per turn).
	 */
	public static function sin(float $value) : float{
		return self::table()[Int64::doubleToInt32($value * 10430.3779296875) & 0xFFFF];
	}

	/**
	 * Single precision table cosine of an angle in radians (65536 steps per turn).
	 */
	public static function cos(float $value) : float{
		return self::table()[Int64::doubleToInt32($value * 10430.3779296875 + 16384.0) & 0xFFFF];
	}

	/**
	 * Single precision table sine; $value is treated as a binary32 number.
	 */
	public static function sinFloat(float $value) : float{
		return self::table()[Int64::doubleToInt32(Float32::of($value * 10430.3779296875)) & 0xFFFF];
	}

	/**
	 * Single precision table cosine; $value is treated as a binary32 number.
	 */
	public static function cosFloat(float $value) : float{
		return self::table()[Int64::doubleToInt32(Float32::of(Float32::of($value * 10430.3779296875) + 16384.0)) & 0xFFFF];
	}

	/**
	 * Largest 32-bit int not greater than $value.
	 */
	public static function floor(float $value) : int{
		$i = Int64::doubleToInt32($value);
		return $value < $i ? $i - 1 : $i;
	}

	/**
	 * Largest 64-bit int not greater than $value.
	 */
	public static function lfloor(float $value) : int{
		$l = Int64::doubleToInt64($value);
		return $value < $l ? $l - 1 : $l;
	}

	public static function floor_double_long(float $value) : int{
		$l = Int64::doubleToInt64($value);
		return $value >= $l ? $l : $l - 1;
	}

	public static function floor_float_int(float $value) : int{
		$i = Int64::doubleToInt32($value);
		return $value >= $i ? $i : $i - 1;
	}

	public static function abs(int $number) : int{
		return $number > 0 ? $number : Int64::toInt32(-$number);
	}

	/**
	 * Removes the multiples of 2^25 that would lose precision in the noise samplers.
	 */
	public static function maintainPrecision(float $value) : float{
		return $value - self::lfloor($value / 3.3554432E7 + 0.5) * 3.3554432E7;
	}

	/**
	 * Gradient dot product of the 16 perlin gradients selected by the low 4 bits of $hash.
	 */
	public static function grad(int $hash, float $x, float $y, float $z) : float{
		return match($hash & 0xF){
			0x0 => $x + $y,
			0x1 => -$x + $y,
			0x2 => $x - $y,
			0x3 => -$x - $y,
			0x4 => $x + $z,
			0x5 => -$x + $z,
			0x6 => $x - $z,
			0x7 => -$x - $z,
			0x8 => $y + $z,
			0x9, 0xD => -$y + $z,
			0xA => $y - $z,
			0xB, 0xF => -$y - $z,
			0xC => $y + $x,
			default => $y - $x
		};
	}

	/**
	 * @return float[]
	 */
	private static function table() : array{
		if(self::$sinTable === null){
			$table = [];
			for($i = 0; $i < 65536; $i++){
				$table[$i] = Float32::of(sin($i * 3.141592653589793 * 2.0 / 65536.0));
			}
			self::$sinTable = $table;
		}
		return self::$sinTable;
	}
}
