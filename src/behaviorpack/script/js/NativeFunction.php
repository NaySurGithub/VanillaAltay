<?php

declare(strict_types=1);

namespace behaviorpack\script\js;

use Closure;

/**
 * A function implemented in PHP. $call receives the this value and the
 * arguments; $construct, when set, receives the arguments and new.target
 * and returns the constructed object.
 */
final class NativeFunction extends JsCallable{

	/**
	 * @param Closure(mixed, list<mixed>) : mixed                  $call
	 * @param (Closure(list<mixed>, JsObject) : JsObject)|null      $construct
	 */
	public function __construct(
		?JsObject $proto,
		string $name,
		int $length,
		public Closure $call,
		public ?Closure $construct = null
	){
		parent::__construct($proto);
		$this->name = $name;
		$this->length = $length;
	}
}
