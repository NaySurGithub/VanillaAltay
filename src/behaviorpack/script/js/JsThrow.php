<?php

declare(strict_types=1);

namespace behaviorpack\script\js;

use Exception;

/**
 * A value thrown by JavaScript code, catchable by try/catch.
 */
final class JsThrow extends Exception{

	public function __construct(
		public mixed $value,
		string $message = "JavaScript exception"
	){
		parent::__construct($message);
	}
}
