<?php

declare(strict_types=1);

namespace dimension\generator\density;

/**
 * A scalar field over block coordinates. Positive terrain density means
 * solid.
 */
interface DensityFunction{

	public function compute(int $x, int $y, int $z) : float;

	/**
	 * Lower bound of compute().
	 */
	public function minValue() : float;

	/**
	 * Upper bound of compute().
	 */
	public function maxValue() : float;
}
