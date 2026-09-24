<?php

declare(strict_types=1);

namespace behaviorpack\script\js\builtin;

use behaviorpack\script\js\Interpreter;
use behaviorpack\script\js\JsCallable;
use behaviorpack\script\js\JsObject;
use behaviorpack\script\js\JsPromise;
use behaviorpack\script\js\JsThrow;
use behaviorpack\script\js\NativeFunction;
use function count;
use function spl_object_id;

/**
 * Promise and the generator prototype.
 */
final class PromiseBuiltins{

	public function __construct(
		private Interpreter $js
	){}

	public function install() : void{
		$this->installPromise();
		$this->installGenerator();
	}

	/**
	 * @return array{0: NativeFunction, 1: NativeFunction}
	 */
	public function resolvingFunctions(JsPromise $promise) : array{
		$js = $this->js;
		$resolve = $js->native("", 1, function(mixed $thisValue, array $args) use ($js, $promise) : mixed{
			$js->resolvePromise($promise, $args[0] ?? null);
			return null;
		});
		$reject = $js->native("", 1, function(mixed $thisValue, array $args) use ($js, $promise) : mixed{
			$js->rejectPromise($promise, $args[0] ?? null);
			return null;
		});
		return [$resolve, $reject];
	}

	private function thisPromise(mixed $thisValue) : JsPromise{
		if(!$thisValue instanceof JsPromise){
			$this->js->throwError("TypeError", "Method Promise.prototype.then called on incompatible receiver");
		}
		return $thisValue;
	}

	private function installPromise() : void{
		$js = $this->js;
		$proto = $js->promisePrototype;
		$proto->symbols[spl_object_id($js->symToStringTag)] = [$js->symToStringTag, "Promise"];
		$constructor = $js->makeClass("Promise", $proto, function(array $args, JsObject $newTarget) use ($js) : JsObject{
			$executor = $args[0] ?? null;
			if(!$executor instanceof JsCallable){
				$js->throwError("TypeError", "Promise resolver " . $js->describeValue($executor) . " is not a function");
			}
			$promise = new JsPromise($js->prototypeFor($newTarget, $js->promisePrototype));
			[$resolve, $reject] = $this->resolvingFunctions($promise);
			try{
				$js->call($executor, null, [$resolve, $reject]);
			}catch(JsThrow $e){
				$js->rejectPromise($promise, $e->value);
			}
			return $promise;
		}, null, 1);
		$js->constructors["Promise"] = $constructor;
		$js->defineHidden($js->global, "Promise", $constructor);

		$js->defineMethod($proto, "then", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			return $js->promiseThen($this->thisPromise($thisValue), $args[0] ?? null, $args[1] ?? null);
		});
		$js->defineMethod($proto, "catch", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			return $js->promiseThen($this->thisPromise($thisValue), null, $args[0] ?? null);
		});
		$js->defineMethod($proto, "finally", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$promise = $this->thisPromise($thisValue);
			$callback = $args[0] ?? null;
			if(!$callback instanceof JsCallable){
				return $js->promiseThen($promise, $callback, $callback);
			}
			$onFulfilled = $js->native("", 1, function(mixed $ignored, array $values) use ($js, $callback) : mixed{
				$value = $values[0] ?? null;
				$result = $js->promiseResolve($js->call($callback, null, []));
				return $js->promiseThen($result, $js->native("", 0, function(mixed $unused, array $none) use ($value) : mixed{
					return $value;
				}), null);
			});
			$onRejected = $js->native("", 1, function(mixed $ignored, array $values) use ($js, $callback) : mixed{
				$reason = $values[0] ?? null;
				$result = $js->promiseResolve($js->call($callback, null, []));
				return $js->promiseThen($result, $js->native("", 0, function(mixed $unused, array $none) use ($reason) : mixed{
					throw new JsThrow($reason);
				}), null);
			});
			return $js->promiseThen($promise, $onFulfilled, $onRejected);
		});

		$js->defineMethod($constructor, "resolve", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			return $js->promiseResolve($args[0] ?? null);
		});
		$js->defineMethod($constructor, "reject", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$promise = $js->newPromise();
			$js->rejectPromise($promise, $args[0] ?? null);
			return $promise;
		});
		$js->defineMethod($constructor, "withResolvers", 0, function(mixed $thisValue, array $args) use ($js) : mixed{
			$promise = $js->newPromise();
			[$resolve, $reject] = $this->resolvingFunctions($promise);
			$result = $js->newObject();
			$result->props = ["promise" => $promise, "resolve" => $resolve, "reject" => $reject];
			return $result;
		});
		$js->defineMethod($constructor, "all", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$result = $js->newPromise();
			$items = $js->iterableToList($args[0] ?? null);
			$values = [];
			$remaining = count($items);
			if($remaining === 0){
				$js->resolvePromise($result, $js->newArray());
				return $result;
			}
			foreach($items as $index => $item){
				$values[$index] = null;
				$js->onSettled($js->promiseResolve($item), function(int $state, mixed $value) use ($js, $result, $index, &$values, &$remaining) : void{
					if($state === JsPromise::REJECTED){
						$js->rejectPromise($result, $value);
						return;
					}
					$values[$index] = $value;
					if(--$remaining === 0){
						$js->resolvePromise($result, $js->newArray($values));
					}
				});
			}
			return $result;
		});
		$js->defineMethod($constructor, "allSettled", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$result = $js->newPromise();
			$items = $js->iterableToList($args[0] ?? null);
			$values = [];
			$remaining = count($items);
			if($remaining === 0){
				$js->resolvePromise($result, $js->newArray());
				return $result;
			}
			foreach($items as $index => $item){
				$values[$index] = null;
				$js->onSettled($js->promiseResolve($item), function(int $state, mixed $value) use ($js, $result, $index, &$values, &$remaining) : void{
					$entry = $js->newObject();
					if($state === JsPromise::FULFILLED){
						$entry->props = ["status" => "fulfilled", "value" => $value];
					}else{
						$entry->props = ["status" => "rejected", "reason" => $value];
					}
					$values[$index] = $entry;
					if(--$remaining === 0){
						$js->resolvePromise($result, $js->newArray($values));
					}
				});
			}
			return $result;
		});
		$js->defineMethod($constructor, "race", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$result = $js->newPromise();
			foreach($js->iterableToList($args[0] ?? null) as $item){
				$js->onSettled($js->promiseResolve($item), function(int $state, mixed $value) use ($js, $result) : void{
					if($state === JsPromise::FULFILLED){
						$js->resolvePromise($result, $value);
					}else{
						$js->rejectPromise($result, $value);
					}
				});
			}
			return $result;
		});
		$js->defineMethod($constructor, "any", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$result = $js->newPromise();
			$items = $js->iterableToList($args[0] ?? null);
			$errors = [];
			$remaining = count($items);
			if($remaining === 0){
				$js->rejectPromise($result, $js->construct($js->constructors["AggregateError"], [$js->newArray(), "All promises were rejected"]));
				return $result;
			}
			foreach($items as $index => $item){
				$errors[$index] = null;
				$js->onSettled($js->promiseResolve($item), function(int $state, mixed $value) use ($js, $result, $index, &$errors, &$remaining) : void{
					if($state === JsPromise::FULFILLED){
						$js->resolvePromise($result, $value);
						return;
					}
					$errors[$index] = $value;
					if(--$remaining === 0){
						$js->rejectPromise($result, $js->construct($js->constructors["AggregateError"], [$js->newArray($errors), "All promises were rejected"]));
					}
				});
			}
			return $result;
		});
	}

	private function installGenerator() : void{
		$js = $this->js;
		$proto = $js->generatorPrototype;
		$proto->symbols[spl_object_id($js->symToStringTag)] = [$js->symToStringTag, "Generator"];
		$js->defineMethod($proto, "next", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			return $js->generatorResume($thisValue, 0, $args[0] ?? null);
		});
		$js->defineMethod($proto, "return", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			return $js->generatorResume($thisValue, 1, $args[0] ?? null);
		});
		$js->defineMethod($proto, "throw", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			return $js->generatorResume($thisValue, 2, $args[0] ?? null);
		});
	}
}
