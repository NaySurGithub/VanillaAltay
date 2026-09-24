<?php

declare(strict_types=1);

namespace dimension\generator\density;

final class ConstantDensity implements DensityFunction{

	public function __construct(
		public readonly float $value
	){}

	public function compute(int $x, int $y, int $z) : float{
		return $this->value;
	}

	public function minValue() : float{
		return $this->value;
	}

	public function maxValue() : float{
		return $this->value;
	}
}
