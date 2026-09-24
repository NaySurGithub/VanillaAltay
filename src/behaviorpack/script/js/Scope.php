<?php

declare(strict_types=1);

namespace behaviorpack\script\js;

/**
 * A lexical environment. Function scopes also hold the reserved bindings
 * "this", "%fn" (the running function) and "%nt" (new.target). Imported
 * bindings are live references to the export of another module.
 */
final class Scope{

	/** @var array<string, mixed> */
	public array $vars = [];

	/** @var array<string, true> */
	public array $consts = [];

	/** @var array<string, array{0: Module, 1: string}> */
	public array $imports = [];

	public function __construct(
		public ?Scope $parent = null,
		public bool $function = false
	){}
}
