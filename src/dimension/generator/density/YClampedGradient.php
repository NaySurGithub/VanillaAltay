<?php

declare(strict_types=1);

namespace dimension\generator\density;

use dimension\generator\math\GenerationMath;
use function max;
use function min;

/**
 * Linear gradient from $fromValue at $fromY to $toValue at $toY, constant
 * outside that range.
 */
final class YClampedGradient implements DensityFunction{

	public function __construct(
		private int $fromY,
		private int $toY,
		private float $fromValue,
		private float $toValue
	){}

	public function compute(int $x, int $y, int $z) : float{
		if($this->fromY === $this->toY){
			return $y < $this->fromY ? $this->fromValue : $this->toValue;
		}
		$t = GenerationMath::clamp(($y - $this->fromY) / ($this->toY - $this->fromY), 0.0, 1.0);
		return $this->fromValue + $t * ($this->toValue - $this->fromValue);
	}

	public function minValue() : float{
		return min($this->fromValue, $this->toValue);
	}

	public function maxValue() : float{
		return max($this->fromValue, $this->toValue);
	}
}
