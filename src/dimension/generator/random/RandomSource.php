<?php

declare(strict_types=1);

namespace dimension\generator\random;

/**
 * A seedable source of randomness used by the terrain generators.
 *
 * Overloads are mapped as follows: nextInt() and nextInt($max) share one
 * method with an optional bound, and the two argument form nextInt(min, max)
 * is nextRangeInt($min, $max).
 */
interface RandomSource{

	/**
	 * Creates a new source seeded with the next random long of this one.
	 */
	public function fork() : RandomSource;

	/**
	 * Creates a new source seeded with the seed this one was last seeded with.
	 */
	public function identical() : RandomSource;

	/**
	 * Without bound: a random int. With a bound: see the implementation for
	 * the exact range.
	 */
	public function nextInt(?int $max = null) : int;

	/**
	 * A random int between $min and $max, see the implementation for the
	 * exact range.
	 */
	public function nextRangeInt(int $min, int $max) : int;

	public function nextBoundedInt(int $max) : int;

	public function nextLong() : int;

	public function nextBoolean() : bool;

	/**
	 * A single precision value in [0, 1).
	 */
	public function nextFloat() : float;

	/**
	 * A value in [0, 1).
	 */
	public function nextDouble() : float;

	/**
	 * A gaussian distributed value.
	 */
	public function nextGaussian() : float;

	public function setSeed(int $seed) : RandomSource;

	public function getSeed() : int;
}
