<?php

declare(strict_types=1);

namespace behaviorpack\script\js;

use Closure;

/**
 * A JavaScript object. Data properties live in $props, accessor properties
 * in $accessors as [getter, setter]; $hidden holds the non-enumerable keys
 * and $locked the non-writable ones. Symbol-keyed properties are kept in
 * $symbols by symbol id.
 *
 * Host objects keep their PHP payload in $host. When a property is found
 * nowhere on the prototype chain, the first $miss handler of the chain is
 * called with the receiver and the key, and its result is returned.
 */
class JsObject{

	/** @var array<string|int, mixed> */
	public array $props = [];

	/** @var array<string|int, array{0: JsCallable|null, 1: JsCallable|null}> */
	public array $accessors = [];

	/** @var array<string|int, true> */
	public array $hidden = [];

	/** @var array<string|int, true> */
	public array $locked = [];

	/** @var array<int, array{0: JsSymbol, 1: mixed}> */
	public array $symbols = [];

	public bool $extensible = true;

	public string $className = "Object";

	public mixed $host = null;

	/** @var Closure(JsObject, string) : mixed|null */
	public ?Closure $miss = null;

	public function __construct(
		public ?JsObject $proto = null
	){}
}
