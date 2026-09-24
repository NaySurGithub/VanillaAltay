<?php

declare(strict_types=1);

namespace dimension\generator\biome;

/**
 * The biome chosen for a column, as a BiomeIds id.
 */
class BiomeResult{

	public function __construct(
		protected int $biomeId
	){}

	public function getBiomeId() : int{
		return $this->biomeId;
	}
}
