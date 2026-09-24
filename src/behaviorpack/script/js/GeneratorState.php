<?php

declare(strict_types=1);

namespace behaviorpack\script\js;

use Fiber;

/**
 * The state of a generator object, whose body runs in a fiber.
 */
final class GeneratorState{

	public bool $started = false;

	public bool $running = false;

	public bool $done = false;

	public function __construct(
		public Fiber $fiber
	){}
}
