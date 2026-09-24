<?php

declare(strict_types=1);

namespace dimension\generator\density;

use function max;
use function min;

/**
 * Sum, product, minimum or maximum of two functions. The second argument is
 * skipped when the first one already decides the result.
 */
final class TwoArgumentDensity implements DensityFunction{

	public const ADD = 0;
	public const MUL = 1;
	public const MIN = 2;
	public const MAX = 3;

	private function __construct(
		private int $type,
		private DensityFunction $argument1,
		private DensityFunction $argument2,
		private float $minValue,
		private float $maxValue
	){}

	/**
	 * Builds the combination, folding a constant argument of a sum or a
	 * product into a MulOrAdd.
	 */
	public static function create(int $type, DensityFunction $argument1, DensityFunction $argument2) : DensityFunction{
		$min1 = $argument1->minValue();
		$min2 = $argument2->minValue();
		$max1 = $argument1->maxValue();
		$max2 = $argument2->maxValue();

		$minValue = match($type){
			self::ADD => $min1 + $min2,
			self::MUL => $min1 > 0.0 && $min2 > 0.0
				? $min1 * $min2
				: ($max1 < 0.0 && $max2 < 0.0 ? $max1 * $max2 : min($min1 * $max2, $max1 * $min2)),
			self::MIN => min($min1, $min2),
			default => max($min1, $min2)
		};
		$maxValue = match($type){
			self::ADD => $max1 + $max2,
			self::MUL => $min1 > 0.0 && $min2 > 0.0
				? $max1 * $max2
				: ($max1 < 0.0 && $max2 < 0.0 ? $min1 * $min2 : max($min1 * $min2, $max1 * $max2)),
			self::MIN => min($max1, $max2),
			default => max($max1, $max2)
		};

		if(($type === self::ADD || $type === self::MUL) && $argument1 instanceof ConstantDensity){
			return new MulOrAdd($type === self::ADD ? MulOrAdd::ADD : MulOrAdd::MUL, $argument2, $minValue, $maxValue, $argument1->value);
		}
		if(($type === self::ADD || $type === self::MUL) && $argument2 instanceof ConstantDensity){
			return new MulOrAdd($type === self::ADD ? MulOrAdd::ADD : MulOrAdd::MUL, $argument1, $minValue, $maxValue, $argument2->value);
		}
		return new self($type, $argument1, $argument2, $minValue, $maxValue);
	}

	public function compute(int $x, int $y, int $z) : float{
		$v1 = $this->argument1->compute($x, $y, $z);
		return match($this->type){
			self::ADD => $v1 + $this->argument2->compute($x, $y, $z),
			self::MUL => $v1 == 0.0 ? 0.0 : $v1 * $this->argument2->compute($x, $y, $z),
			self::MIN => $v1 < $this->argument2->minValue() ? $v1 : min($v1, $this->argument2->compute($x, $y, $z)),
			default => $v1 > $this->argument2->maxValue() ? $v1 : max($v1, $this->argument2->compute($x, $y, $z))
		};
	}

	public function minValue() : float{
		return $this->minValue;
	}

	public function maxValue() : float{
		return $this->maxValue;
	}
}
