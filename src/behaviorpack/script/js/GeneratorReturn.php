<?php

declare(strict_types=1);

namespace behaviorpack\script\js;

use Exception;

/**
 * Unwinds a suspended generator when its return() method is called, running
 * its finally blocks.
 */
final class GeneratorReturn extends Exception{

	public function __construct(
		public mixed $value
	){
		parent::__construct("Generator return");
	}
}
