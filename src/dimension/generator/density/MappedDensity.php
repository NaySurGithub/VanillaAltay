<?php

declare(strict_types=1);

namespace dimension\generator\density;

use dimension\generator\math\GenerationMath;
use function abs;
use function max;
use function min;
use const INF;

/**
 * A unary transform of another function.
 */
final class MappedDensity implements DensityFunction{

	public const ABS = 0;
	public const SQUARE = 1;
	public const CUBE = 2;
	public const HALF_NEGATIVE = 3;
	public const QUARTER_NEGATIVE = 4;
	public const INVERT = 5;
	/** clamp to [-1, 1] then x / 2 - x^3 / 24 */
	public const SQUEEZE = 6;

	private function __construct(
		private int $type,
		private DensityFunction $input,
		private float $minValue,
		private float $maxValue
	){}

	public static function create(int $type, DensityFunction $input) : MappedDensity{
		$min = $input->minValue();
		$max = $input->maxValue();
		$transformedMin = self::apply($type, $min);
		$transformedMax = self::apply($type, $max);
		if($type === self::INVERT){
			if($min < 0.0 && $max > 0.0){
				return new self($type, $input, -INF, INF);
			}
			return new self($type, $input, min($transformedMin, $transformedMax), max($transformedMin, $transformedMax));
		}
		if($type === self::ABS || $type === self::SQUARE){
			return new self($type, $input, max(0.0, min($transformedMin, $transformedMax)), max($transformedMin, $transformedMax));
		}
		return new self($type, $input, min($transformedMin, $transformedMax), max($transformedMin, $transformedMax));
	}

	public static function apply(int $type, float $input) : float{
		switch($type){
			case self::ABS:
				return abs($input);
			case self::SQUARE:
				return $input * $input;
			case self::CUBE:
				return $input * $input * $input;
			case self::HALF_NEGATIVE:
				return $input > 0.0 ? $input : $input * 0.5;
			case self::QUARTER_NEGATIVE:
				return $input > 0.0 ? $input : $input * 0.25;
			case self::INVERT:
				return $input == 0.0 ? INF : 1.0 / $input;
			default:
				$clamped = GenerationMath::clamp($input, -1.0, 1.0);
				return $clamped / 2.0 - $clamped * $clamped * $clamped / 24.0;
		}
	}

	public function compute(int $x, int $y, int $z) : float{
		return self::apply($this->type, $this->input->compute($x, $y, $z));
	}

	public function minValue() : float{
		return $this->minValue;
	}

	public function maxValue() : float{
		return $this->maxValue;
	}
}
