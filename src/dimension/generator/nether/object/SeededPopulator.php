<?php

declare(strict_types=1);

namespace dimension\generator\nether\object;

use dimension\generator\Populator;
use dimension\generator\random\Xoroshiro128;

/**
 * A populator owning the Xoroshiro128 random it reseeds for every chunk.
 */
abstract class SeededPopulator implements Populator{

	protected Xoroshiro128 $random;

	public function __construct(){
		$this->random = new Xoroshiro128(0);
	}
}
