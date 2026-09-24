<?php

declare(strict_types=1);

namespace dimension\generator\noise;

use dimension\generator\math\Int64;

/**
 * Linear congruential step seed = (seed * multiplier + addend) mod 2^48,
 * with the constants of the classic 48-bit generator, and its combination
 * over several steps.
 */
final class Lcg{

	private const MULTIPLIER = 25214903917;
	private const ADDEND = 11;
	private const MASK = (1 << 48) - 1;

	private static ?Lcg $skip262 = null;

	private function __construct(
		private int $multiplier,
		private int $addend
	){}

	/**
	 * The generator advanced 262 steps at once: the seed skip of one unused noise octave.
	 */
	public static function skip262() : Lcg{
		return self::$skip262 ??= self::combine(262);
	}

	public function nextSeed(int $seed) : int{
		return Int64::add(Int64::mul($seed, $this->multiplier), $this->addend) & self::MASK;
	}

	private static function combine(int $steps) : Lcg{
		$multiplier = 1;
		$addend = 0;
		$intermediateMultiplier = self::MULTIPLIER;
		$intermediateAddend = self::ADDEND;
		for($k = $steps; $k !== 0; $k = Int64::urshift($k, 1)){
			if(($k & 1) !== 0){
				$multiplier = Int64::mul($multiplier, $intermediateMultiplier);
				$addend = Int64::add(Int64::mul($intermediateMultiplier, $addend), $intermediateAddend);
			}
			$intermediateAddend = Int64::mul(Int64::add($intermediateMultiplier, 1), $intermediateAddend);
			$intermediateMultiplier = Int64::mul($intermediateMultiplier, $intermediateMultiplier);
		}
		return new Lcg($multiplier & self::MASK, $addend & self::MASK);
	}
}
