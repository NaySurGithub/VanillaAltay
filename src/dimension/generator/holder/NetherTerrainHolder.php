<?php

declare(strict_types=1);

namespace dimension\generator\holder;

use dimension\generator\density\BlendedNoise;
use dimension\generator\density\DensityFunction;
use dimension\generator\density\NetherDensity;
use dimension\generator\noise\NormalNoise;
use dimension\generator\random\RandomSource;

/**
 * Noises of the nether terrain and surface. Each noise is drawn from a
 * fresh source seeded with the world seed.
 */
final class NetherTerrainHolder extends RandomizedObjectHolder{

	private NormalNoise $surfaceNoise;
	private NormalNoise $patchNoise;
	private NormalNoise $soulsandNoise;
	private NormalNoise $netherStateNoise;
	private NormalNoise $netherwartNoise;
	private DensityFunction $base3dNoise;
	private DensityFunction $densityFunction;

	public function __construct(RandomSource $randomSourceProvider){
		parent::__construct($randomSourceProvider);
		$this->surfaceNoise = new NormalNoise($randomSourceProvider->identical(), -6, [1.0, 1.0, 1.0]);
		$this->patchNoise = new NormalNoise($randomSourceProvider->identical(), -5, [1.0, 0.0, 0.0, 0.0, 0.0, 0.013333333333333334]);
		$this->soulsandNoise = new NormalNoise($randomSourceProvider->identical(), -8, [1.0, 1.0, 1.0, 1.0, 0.0, 0.0, 0.0, 0.0, 0.013333333333333334]);
		$this->netherStateNoise = new NormalNoise($randomSourceProvider->identical(), -4, [1.0]);
		$this->netherwartNoise = new NormalNoise($randomSourceProvider->identical(), -3, [1.0, 0.0, 0.0, 0.9]);
		$this->base3dNoise = BlendedNoise::nether($randomSourceProvider->identical());
		$this->densityFunction = NetherDensity::finalDensity($this->base3dNoise);
	}

	public function getSurfaceNoise() : NormalNoise{
		return $this->surfaceNoise;
	}

	public function getPatchNoise() : NormalNoise{
		return $this->patchNoise;
	}

	public function getSoulsandNoise() : NormalNoise{
		return $this->soulsandNoise;
	}

	public function getNetherStateNoise() : NormalNoise{
		return $this->netherStateNoise;
	}

	public function getNetherwartNoise() : NormalNoise{
		return $this->netherwartNoise;
	}

	public function getBase3dNoise() : DensityFunction{
		return $this->base3dNoise;
	}

	public function getDensityFunction() : DensityFunction{
		return $this->densityFunction;
	}
}
