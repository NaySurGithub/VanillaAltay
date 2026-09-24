<?php

declare(strict_types=1);

namespace dimension\generator\math;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * Two's complement 64-bit and 32-bit integer arithmetic that wraps on
 * overflow instead of turning into a float.
 */
final class Int64{

	private const MASK_32 = 0xFFFFFFFF;

	private function __construct(){
	}

	/**
	 * Wrapping 64-bit addition.
	 */
	public static function add(int $a, int $b) : int{
		$low = ($a & self::MASK_32) + ($b & self::MASK_32);
		$high = (($a >> 32) + ($b >> 32) + ($low >> 32)) & self::MASK_32;
		return ($high << 32) | ($low & self::MASK_32);
	}

	/**
	 * Wrapping 64-bit subtraction.
	 */
	public static function sub(int $a, int $b) : int{
		return self::add($a, self::add(~$b, 1));
	}

	/**
	 * Wrapping 64-bit multiplication.
	 */
	public static function mul(int $a, int $b) : int{
		$aLow = $a & self::MASK_32;
		$aHigh = ($a >> 32) & self::MASK_32;
		$bLow = $b & self::MASK_32;
		$bHigh = ($b >> 32) & self::MASK_32;
		$low = self::add($aLow * ($bLow & 0xFFFF), ($aLow * ($bLow >> 16)) << 16);
		$cross = (self::mulLow32($aHigh, $bLow) + self::mulLow32($aLow, $bHigh)) & self::MASK_32;
		return self::add($low, $cross << 32);
	}

	/**
	 * Logical (unsigned) right shift of a 64-bit value.
	 */
	public static function urshift(int $value, int $bits) : int{
		$bits &= 63;
		if($bits === 0){
			return $value;
		}
		return ($value >> $bits) & (PHP_INT_MAX >> ($bits - 1));
	}

	/**
	 * 64-bit left rotation.
	 */
	public static function rotateLeft(int $value, int $bits) : int{
		return ($value << $bits) | self::urshift($value, 64 - $bits);
	}

	/**
	 * Truncates to a signed 32-bit integer.
	 */
	public static function toInt32(int $value) : int{
		return (($value & self::MASK_32) ^ 0x80000000) - 0x80000000;
	}

	/**
	 * Logical (unsigned) right shift of a signed 32-bit value, returning a signed 32-bit value.
	 */
	public static function urshift32(int $value, int $bits) : int{
		$bits &= 31;
		return self::toInt32(($value & self::MASK_32) >> $bits);
	}

	/**
	 * Wrapping 32-bit multiplication.
	 */
	public static function mul32(int $a, int $b) : int{
		return self::toInt32(self::mulLow32($a & self::MASK_32, $b & self::MASK_32));
	}

	/**
	 * Truncates a float toward zero into a signed 32-bit integer, saturating
	 * out of range values and mapping NaN to zero.
	 */
	public static function doubleToInt32(float $value) : int{
		if($value !== $value){
			return 0;
		}
		if($value >= 2147483647.0){
			return 2147483647;
		}
		if($value <= -2147483648.0){
			return -2147483648;
		}
		return (int) $value;
	}

	/**
	 * Truncates a float toward zero into a signed 64-bit integer, saturating
	 * out of range values and mapping NaN to zero.
	 */
	public static function doubleToInt64(float $value) : int{
		if($value !== $value){
			return 0;
		}
		if($value >= 9.2233720368547758E18){
			return PHP_INT_MAX;
		}
		if($value <= -9.2233720368547758E18){
			return PHP_INT_MIN;
		}
		return (int) $value;
	}

	/**
	 * Low 32 bits of the product of two unsigned 32-bit values.
	 */
	private static function mulLow32(int $a, int $b) : int{
		return (($a * ($b & 0xFFFF)) + ((($a * ($b >> 16)) & 0xFFFF) << 16)) & self::MASK_32;
	}
}
