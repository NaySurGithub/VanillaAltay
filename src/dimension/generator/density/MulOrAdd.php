<?php

declare(strict_types=1);

namespace dimension\generator\density;

/**
 * Adds a constant to, or multiplies by a constant, another function.
 */
final class MulOrAdd implements DensityFunction{

	public const ADD = 0;
	public const MUL = 1;

	public function __construct(
		private int $type,
		private DensityFunction $input,
		private float $minValue,
		private float $maxValue,
		private float $argument
	){}

	public function compute(int $x, int $y, int $z) : float{
		$value = $this->input->compute($x, $y, $z);
		return $this->type === self::MUL ? $value * $this->argument : $value + $this->argument;
	}

	public function minValue() : float{
		return $this->minValue;
	}

	public function maxValue() : float{
		return $this->maxValue;
	}
}
