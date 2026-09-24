<?php

declare(strict_types=1);

namespace behaviorpack\script\js;

use Closure;
use Throwable;
use function dirname;
use function file_get_contents;
use function is_dir;
use function is_file;
use function realpath;
use function rtrim;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Loads, links and evaluates ES modules. Relative specifiers are resolved
 * inside the pack of the importing module; "@minecraft/..." specifiers map
 * to host modules, and unknown ones to modules whose every export is a
 * stand-in built by $stubFactory.
 */
final class ModuleLoader{

	/** @var array<string, Module> */
	private array $modules = [];

	/** @var array<string, Module> */
	private array $hostModules = [];

	/** @var array<string, Module> */
	private array $byDisplayPath = [];

	/**
	 * @param Closure(string, string) : mixed $stubFactory receives the module and export names
	 */
	public function __construct(
		private Interpreter $js,
		private Closure $stubFactory
	){
		$js->dynamicImport = function(string $fromFile, string $specifier) : JsPromise{
			$promise = $this->js->newPromise();
			try{
				$from = $this->modules[$fromFile] ?? $this->byDisplayPath[($this->js->context ?? "") . "|" . $fromFile] ?? null;
				$module = $this->resolve($specifier, $from?->path ?? $fromFile, $from?->pack, $this->packRoot($from));
				$this->evaluate($module);
				$this->js->resolvePromise($promise, $this->js->moduleNamespace($module));
			}catch(JsThrow $e){
				$this->js->rejectPromise($promise, $e->value);
			}catch(SyntaxErrorException $e){
				$this->js->rejectPromise($promise, $this->js->makeError("SyntaxError", $e->getMessage()));
			}
			return $promise;
		};
	}

	/**
	 * @param array<string, mixed>              $exports
	 * @param (Closure(string) : mixed)|null    $missing
	 */
	public function addHostModule(string $specifier, array $exports, ?Closure $missing = null) : Module{
		$module = new Module($specifier);
		foreach($exports as $name => $value){
			$module->exports[(string) $name] = ["value", $value];
		}
		$module->missing = $missing;
		$module->status = Module::EVALUATED;
		$this->hostModules[$specifier] = $module;
		return $module;
	}

	/** @var array<string, string> */
	private array $packRoots = [];

	private function packRoot(?Module $module) : string{
		if($module === null){
			return "";
		}
		return $this->packRoots[$module->path] ?? "";
	}

	/**
	 * Loads and evaluates the entry module of a pack.
	 */
	public function run(string $entry, string $packRoot, string $pack) : Module{
		$path = $this->normalize($entry);
		$module = $this->load($path, $pack, $this->normalize($packRoot));
		$this->evaluate($module);
		return $module;
	}

	private function normalize(string $path) : string{
		$real = realpath($path);
		return str_replace("\\", "/", $real === false ? $path : $real);
	}

	private function resolve(string $specifier, string $fromFile, ?string $pack, string $packRoot) : Module{
		if(isset($this->hostModules[$specifier])){
			return $this->hostModules[$specifier];
		}
		if(str_starts_with($specifier, "@minecraft/")){
			$factory = $this->stubFactory;
			return $this->addHostModule($specifier, [], function(string $name) use ($factory, $specifier) : mixed{
				return $factory($specifier, $name);
			});
		}
		if(!str_starts_with($specifier, "./") && !str_starts_with($specifier, "../") && !str_starts_with($specifier, "/")){
			throw new SyntaxErrorException("Cannot find module '" . $specifier . "' imported from " . $fromFile);
		}
		$base = str_starts_with($specifier, "/") ? $packRoot : dirname($fromFile);
		$candidate = rtrim($base, "/") . "/" . $specifier;
		foreach([$candidate, $candidate . ".js", $candidate . ".mjs", $candidate . "/index.js"] as $option){
			if(is_file($option) && !is_dir($option)){
				$path = $this->normalize($option);
				if($packRoot !== "" && !str_starts_with($path, rtrim($packRoot, "/") . "/")){
					throw new SyntaxErrorException("Module '" . $specifier . "' is outside of its pack");
				}
				return $this->load($path, $pack, $packRoot);
			}
		}
		throw new SyntaxErrorException("Cannot find module '" . $specifier . "' imported from " . $fromFile);
	}

	private function load(string $path, ?string $pack, string $packRoot) : Module{
		if(isset($this->modules[$path])){
			return $this->modules[$path];
		}
		$source = @file_get_contents($path);
		if($source === false){
			throw new SyntaxErrorException("Cannot read module " . $path);
		}
		$module = new Module($path, $pack);
		$this->modules[$path] = $module;
		$this->packRoots[$path] = $packRoot;
		$this->byDisplayPath[($pack ?? "") . "|" . $this->displayPath($path, $packRoot)] = $module;
		$module->node = (new Parser($source, $this->displayPath($path, $packRoot)))->parseProgram();
		$this->link($module, $packRoot);
		return $module;
	}

	private function displayPath(string $path, string $packRoot) : string{
		$root = rtrim($packRoot, "/") . "/";
		if($packRoot !== "" && str_starts_with($path, $root)){
			return substr($path, strlen($root));
		}
		return $path;
	}

	private function link(Module $module, string $packRoot) : void{
		$js = $this->js;
		$node = $module->node;
		$scope = new Scope(null, true);
		$scope->vars["this"] = null;
		$module->scope = $scope;
		$savedFile = $js->file;
		$js->file = $this->displayPath($module->path, $packRoot);
		try{
			foreach($node["vars"] as $name){
				$scope->vars[$name] = null;
			}
			$js->hoistLexical($node, $scope);
			foreach($node["body"] as $statement){
				if($statement["type"] !== "ImportDeclaration"){
					continue;
				}
				$dependency = $this->resolve($statement["source"], $module->path, $module->pack, $packRoot);
				foreach($statement["specifiers"] as $specifier){
					if($specifier["kind"] === "namespace"){
						$scope->vars[$specifier["local"]] = $js->moduleNamespace($dependency);
						$scope->consts[$specifier["local"]] = true;
						continue;
					}
					$scope->imports[$specifier["local"]] = [$dependency, $specifier["imported"]];
				}
			}
			foreach($node["body"] as $statement){
				$this->collectExports($module, $statement, $packRoot);
			}
		}finally{
			$js->file = $savedFile;
		}
	}

	/**
	 * @param array<string, mixed> $statement
	 */
	private function collectExports(Module $module, array $statement, string $packRoot) : void{
		$scope = $module->scope;
		switch($statement["type"]){
			case "ExportNamedDeclaration":
				$declaration = $statement["declaration"];
				if($declaration !== null){
					foreach($this->declaredNames($declaration) as $name){
						$module->exports[$name] = ["local", $scope, $name];
					}
					return;
				}
				$source = $statement["source"] === null ? null : $this->resolve($statement["source"], $module->path, $module->pack, $packRoot);
				foreach($statement["specifiers"] as $specifier){
					$local = $specifier["local"];
					$exported = $specifier["exported"];
					if($source !== null){
						$module->exports[$exported] = ["reexport", $source, $local];
					}elseif(isset($scope->imports[$local])){
						$module->exports[$exported] = ["reexport", $scope->imports[$local][0], $scope->imports[$local][1]];
					}else{
						$module->exports[$exported] = ["local", $scope, $local];
					}
				}
				return;
			case "ExportDefaultDeclaration":
				$declaration = $statement["declaration"];
				if($declaration !== null){
					$names = $this->declaredNames($declaration);
					$module->exports["default"] = ["local", $scope, $names[0]];
					return;
				}
				$scope->vars["*default*"] = Tdz::get();
				$module->exports["default"] = ["local", $scope, "*default*"];
				return;
			case "ExportAllDeclaration":
				$source = $this->resolve($statement["source"], $module->path, $module->pack, $packRoot);
				if($statement["exported"] !== null){
					$module->exports[$statement["exported"]] = ["namespace", $source];
				}else{
					$module->starExports[] = $source;
				}
				return;
		}
	}

	/**
	 * @param array<string, mixed> $declaration
	 * @return list<string>
	 */
	private function declaredNames(array $declaration) : array{
		switch($declaration["type"]){
			case "VariableDeclaration":
				$names = [];
				foreach($declaration["declarations"] as $declarator){
					foreach(Parser::patternNames($declarator["id"]) as $name){
						$names[] = $name;
					}
				}
				return $names;
			case "FunctionDeclaration":
				return [$declaration["fn"]["id"]];
			case "ClassDeclaration":
				return [$declaration["class"]["id"]];
		}
		return [];
	}

	/**
	 * Evaluates a module after its dependencies.
	 */
	public function evaluate(Module $module) : void{
		if($module->status === Module::EVALUATED || $module->status === Module::EVALUATING){
			return;
		}
		if($module->status === Module::FAILED){
			throw new JsThrow($module->error);
		}
		$module->status = Module::EVALUATING;
		$js = $this->js;
		try{
			foreach($module->node["body"] as $statement){
				$source = match($statement["type"]){
					"ImportDeclaration", "ExportAllDeclaration" => $statement["source"],
					"ExportNamedDeclaration" => $statement["source"],
					default => null
				};
				if($source !== null){
					$this->evaluate($this->resolve($source, $module->path, $module->pack, $this->packRoots[$module->path] ?? ""));
				}
			}
			$savedFile = $js->file;
			$js->file = $this->displayPath($module->path, $this->packRoots[$module->path] ?? "");
			try{
				foreach($module->node["body"] as $statement){
					$js->execute($statement, $module->scope);
				}
			}finally{
				$js->file = $savedFile;
			}
		}catch(JsThrow $e){
			$module->status = Module::FAILED;
			$module->error = $e->value;
			throw $e;
		}catch(Throwable $e){
			$module->status = Module::FAILED;
			throw $e;
		}
		$module->status = Module::EVALUATED;
	}
}
