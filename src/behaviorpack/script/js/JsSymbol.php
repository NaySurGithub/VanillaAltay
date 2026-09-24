<?php

declare(strict_types=1);

namespace behaviorpack\script\js;

/**
 * A JavaScript symbol, compared by identity.
 */
final class JsSymbol{

	public function __construct(
		public ?string $description = null
	){}
}
