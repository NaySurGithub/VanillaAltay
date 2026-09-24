<?php

declare(strict_types=1);

namespace dimension\generator\holder;

use dimension\generator\random\RandomSource;

/**
 * Generation objects of the nether, built from the world seed.
 */
final class NetherObjectHolder extends RandomizedObjectHolder{

	private NetherTerrainHolder $terrainHolder;
	private BasaltDeltaHolder $basaltDeltasHolder;

	public function __construct(RandomSource $randomSourceProvider){
		parent::__construct($randomSourceProvider);
		$this->terrainHolder = new NetherTerrainHolder($randomSourceProvider);
		$this->basaltDeltasHolder = new BasaltDeltaHolder($randomSourceProvider);
	}

	public function getTerrainHolder() : NetherTerrainHolder{
		return $this->terrainHolder;
	}

	public function getBasaltDeltasHolder() : BasaltDeltaHolder{
		return $this->basaltDeltasHolder;
	}
}
