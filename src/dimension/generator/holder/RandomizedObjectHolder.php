<?php

declare(strict_types=1);

namespace dimension\generator\holder;

use dimension\generator\random\RandomSource;

/**
 * Holds generation objects built from one seeded random source.
 */
abstract class RandomizedObjectHolder{

	public function __construct(
		protected RandomSource $randomSourceProvider
	){}

	public function getRandomSourceProvider() : RandomSource{
		return $this->randomSourceProvider;
	}
}
