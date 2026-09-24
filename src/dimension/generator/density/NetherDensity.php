<?php

declare(strict_types=1);

namespace dimension\generator\density;

/**
 * Final terrain density of the nether: the base noise faded toward solid
 * near the floor (Y -8 to 24) and the roof (Y 104 to 128), interpolated per
 * cell, scaled and squeezed.
 */
final class NetherDensity{

	private function __construct(){
	}

	public static function finalDensity(DensityFunction $base3dNoise) : DensityFunction{
		$density = Densities::add(Densities::constant(-0.9375), $base3dNoise);
		$density = Densities::mul(Densities::yClampedGradient(104, 128, 1.0, 0.0), $density);
		$density = Densities::add(Densities::constant(0.9375), $density);
		$density = Densities::add(Densities::constant(-2.5), $density);
		$density = Densities::mul(Densities::yClampedGradient(-8, 24, 0.0, 1.0), $density);
		$density = Densities::add(Densities::constant(2.5), $density);
		$density = Densities::blendDensity($density);
		$density = Densities::interpolated($density);
		$density = Densities::mul(Densities::constant(0.64), $density);
		return Densities::map($density, MappedDensity::SQUEEZE);
	}
}
