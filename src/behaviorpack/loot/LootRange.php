<?php

declare(strict_types=1);

namespace behaviorpack\loot;

use function is_array;
use function is_numeric;
use function max;
use function min;
use function mt_getrandmax;
use function mt_rand;

/**
 * Reads the numbers of a loot table, which are either a constant or a
 * {"min": a, "max": b} range.
 */
final class LootRange{

	private function __construct(){
	}

	public static function number(mixed $value, float $default) : float{
		return is_numeric($value) ? (float) $value : $default;
	}

	public static function chance() : float{
		return mt_rand() / mt_getrandmax();
	}

	public static function rollInt(mixed $value, int $default) : int{
		if(is_numeric($value)){
			return (int) $value;
		}
		if(is_array($value)){
			$low = (int) self::number($value["min"] ?? $value[0] ?? null, $default);
			$high = (int) self::number($value["max"] ?? $value[1] ?? null, $low);
			return mt_rand(min($low, $high), max($low, $high));
		}
		return $default;
	}

	public static function rollFloat(mixed $value, float $default) : float{
		if(is_numeric($value)){
			return (float) $value;
		}
		if(is_array($value)){
			$low = self::number($value["min"] ?? $value[0] ?? null, $default);
			$high = self::number($value["max"] ?? $value[1] ?? null, $low);
			return $low + ($high - $low) * self::chance();
		}
		return $default;
	}

	public static function contains(mixed $range, int $value) : bool{
		if(is_numeric($range)){
			return $value === (int) $range;
		}
		if(is_array($range)){
			if(isset($range["min"]) && is_numeric($range["min"]) && $value < (int) $range["min"]){
				return false;
			}
			if(isset($range["max"]) && is_numeric($range["max"]) && $value > (int) $range["max"]){
				return false;
			}
		}
		return true;
	}
}
