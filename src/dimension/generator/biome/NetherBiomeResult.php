<?php

declare(strict_types=1);

namespace dimension\generator\biome;

/**
 * A nether biome with the climate values it was picked from.
 */
final class NetherBiomeResult extends BiomeResult{

	public function __construct(
		int $biomeId,
		private float $temperature,
		private float $humidity
	){
		parent::__construct($biomeId);
	}

	public function getTemperature() : float{
		return $this->temperature;
	}

	public function getHumidity() : float{
		return $this->humidity;
	}
}
