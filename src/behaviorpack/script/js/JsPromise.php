<?php

declare(strict_types=1);

namespace behaviorpack\script\js;

use Closure;

/**
 * A JavaScript promise. Reactions are PHP closures receiving the settled
 * state and value.
 */
final class JsPromise extends JsObject{

	public const PENDING = 0;
	public const FULFILLED = 1;
	public const REJECTED = 2;

	public int $state = self::PENDING;

	public mixed $value = null;

	public bool $resolving = false;

	public bool $handled = false;

	/** @var list<Closure(int, mixed) : void> */
	public array $reactions = [];

	public function __construct(?JsObject $proto){
		parent::__construct($proto);
		$this->className = "Promise";
	}
}
