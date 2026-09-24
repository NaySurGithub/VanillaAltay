<?php

declare(strict_types=1);

namespace behaviorpack\script\js;

/**
 * A function defined by a script. $node is the "Function" node, or null for
 * the implicit constructor of a class. Class constructors keep the instance
 * fields to initialize in $fields.
 */
final class JsFunction extends JsCallable{

	public ?JsObject $home = null;

	public bool $isClass = false;

	public bool $derived = false;

	public bool $needsPrototype = false;

	public string $file = "";

	/** @var list<array{0: string|JsSymbol, 1: array<string, mixed>|null, 2: bool}> */
	public array $fields = [];

	/**
	 * @param array<string, mixed>|null $node
	 */
	public function __construct(
		?JsObject $proto,
		public ?array $node,
		public Scope $scope
	){
		parent::__construct($proto);
	}
}
