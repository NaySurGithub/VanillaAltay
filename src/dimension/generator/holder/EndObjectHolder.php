<?php

declare(strict_types=1);

namespace dimension\generator\holder;

use dimension\generator\random\RandomSource;

/**
 * Generation objects of the end, built from the world seed.
 */
final class EndObjectHolder extends RandomizedObjectHolder{

	private EndTerrainHolder $terrainHolder;

	public function __construct(RandomSource $randomSourceProvider){
		parent::__construct($randomSourceProvider);
		$this->terrainHolder = new EndTerrainHolder($randomSourceProvider);
	}

	public function getTerrainHolder() : EndTerrainHolder{
		return $this->terrainHolder;
	}
}
