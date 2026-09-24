<?php

declare(strict_types=1);

namespace dimension\generator\density;

/**
 * Factories of density function combinations.
 */
final class Densities{

	private function __construct(){
	}

	public static function constant(float $value) : DensityFunction{
		return new ConstantDensity($value);
	}

	public static function add(DensityFunction $f1, DensityFunction $f2) : DensityFunction{
		return TwoArgumentDensity::create(TwoArgumentDensity::ADD, $f1, $f2);
	}

	public static function mul(DensityFunction $f1, DensityFunction $f2) : DensityFunction{
		return TwoArgumentDensity::create(TwoArgumentDensity::MUL, $f1, $f2);
	}

	public static function min(DensityFunction $f1, DensityFunction $f2) : DensityFunction{
		return TwoArgumentDensity::create(TwoArgumentDensity::MIN, $f1, $f2);
	}

	public static function max(DensityFunction $f1, DensityFunction $f2) : DensityFunction{
		return TwoArgumentDensity::create(TwoArgumentDensity::MAX, $f1, $f2);
	}

	public static function yClampedGradient(int $fromY, int $toY, float $fromValue, float $toValue) : DensityFunction{
		return new YClampedGradient($fromY, $toY, $fromValue, $toValue);
	}

	/**
	 * No chunk blending happens here: the input is returned as is.
	 */
	public static function blendDensity(DensityFunction $input) : DensityFunction{
		return $input;
	}

	public static function interpolated(DensityFunction $input) : DensityFunction{
		return new InterpolatedDensity($input);
	}

	/**
	 * @param int $type one of the MappedDensity constants
	 */
	public static function map(DensityFunction $input, int $type) : DensityFunction{
		return MappedDensity::create($type, $input);
	}
}
