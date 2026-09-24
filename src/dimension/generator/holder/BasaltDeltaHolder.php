<?php

declare(strict_types=1);

namespace dimension\generator\holder;

use dimension\generator\noise\NormalNoise;
use dimension\generator\random\RandomSource;

/**
 * Surface noises of the basalt deltas. Each noise is drawn from a fresh
 * source seeded with the world seed.
 */
final class BasaltDeltaHolder extends RandomizedObjectHolder{

	private NormalNoise $surfaceNoise;
	private NormalNoise $surfaceSecNoise;

	public function __construct(RandomSource $randomSourceProvider){
		parent::__construct($randomSourceProvider);
		$this->surfaceNoise = new NormalNoise($randomSourceProvider->identical(), -6, [1.0, 1.0, 1.0]);
		$this->surfaceSecNoise = new NormalNoise($randomSourceProvider->identical(), -6, [1.0, 0.0, 1.0, 1.0]);
	}

	public function getSurfaceNoise() : NormalNoise{
		return $this->surfaceNoise;
	}

	public function getSurfaceSecNoise() : NormalNoise{
		return $this->surfaceSecNoise;
	}
}
