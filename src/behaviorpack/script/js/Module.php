<?php

declare(strict_types=1);

namespace behaviorpack\script\js;

use Closure;
use function in_array;

/**
 * A module record. Exports map an exported name to a binding:
 * ["local", Scope, name], ["value", value], ["reexport", Module, name] or
 * ["namespace", Module]. Host modules have value bindings only, and may
 * provide stand-ins for the names they do not export through $missing.
 */
final class Module{

	public const NEW = 0;
	public const EVALUATING = 1;
	public const EVALUATED = 2;
	public const FAILED = 3;

	public int $status = self::NEW;

	public ?Scope $scope = null;

	/** @var array<string, mixed>|null */
	public ?array $node = null;

	/** @var array<string, array<int, mixed>> */
	public array $exports = [];

	/** @var list<Module> */
	public array $starExports = [];

	public ?JsObject $namespace = null;

	public mixed $error = null;

	/** @var (Closure(string) : mixed)|null */
	public ?Closure $missing = null;

	public function __construct(
		public string $path,
		public ?string $pack = null
	){}

	/**
	 * @param list<Module> $visited
	 * @return array<int, mixed>|null
	 */
	public function resolve(string $name, array $visited = []) : ?array{
		if(in_array($this, $visited, true)){
			return null;
		}
		$visited[] = $this;
		$binding = $this->exports[$name] ?? null;
		if($binding !== null){
			if($binding[0] === "reexport"){
				return $binding[1]->resolve($binding[2], $visited);
			}
			return $binding;
		}
		if($name === "default"){
			return null;
		}
		foreach($this->starExports as $module){
			$resolved = $module->resolve($name, $visited);
			if($resolved !== null){
				return $resolved;
			}
		}
		return null;
	}

	/**
	 * @param list<Module> $visited
	 * @return list<string>
	 */
	public function exportNames(array $visited = []) : array{
		if(in_array($this, $visited, true)){
			return [];
		}
		$visited[] = $this;
		$names = [];
		foreach($this->exports as $name => $binding){
			$names[(string) $name] = true;
		}
		foreach($this->starExports as $module){
			foreach($module->exportNames($visited) as $name){
				if($name !== "default"){
					$names[$name] = true;
				}
			}
		}
		$list = [];
		foreach($names as $name => $unused){
			$list[] = (string) $name;
		}
		return $list;
	}
}
