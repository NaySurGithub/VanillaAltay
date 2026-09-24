<?php

declare(strict_types=1);

namespace behaviorpack\script\js;

/**
 * An abrupt completion of a statement: break, continue or return.
 */
final class Completion{

	public const BREAK = 1;
	public const CONTINUE = 2;
	public const RETURN = 3;

	public function __construct(
		public int $type,
		public mixed $value = null,
		public ?string $label = null
	){}
}
