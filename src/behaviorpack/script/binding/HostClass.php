<?php

declare(strict_types=1);

namespace behaviorpack\script\binding;

use behaviorpack\script\js\JsObject;
use behaviorpack\script\js\NativeFunction;

/**
 * A JavaScript class implemented in PHP: its constructor and prototype.
 */
final class HostClass{

	public function __construct(
		public string $name,
		public NativeFunction $constructor,
		public JsObject $prototype
	){}
}
