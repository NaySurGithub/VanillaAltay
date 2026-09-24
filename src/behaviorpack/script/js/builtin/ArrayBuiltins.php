<?php

declare(strict_types=1);

namespace behaviorpack\script\js\builtin;

use behaviorpack\script\js\Interpreter;
use behaviorpack\script\js\JsArray;
use behaviorpack\script\js\JsCallable;
use behaviorpack\script\js\JsNull;
use behaviorpack\script\js\JsObject;
use function array_fill;
use function array_merge;
use function array_reverse;
use function array_slice;
use function array_splice;
use function count;
use function implode;
use function is_array;
use function is_float;
use function is_int;
use function is_nan;
use function is_string;
use function max;
use function min;
use function spl_object_id;
use function strcmp;
use function usort;

/**
 * Array and its prototype, and array iterators.
 */
final class ArrayBuiltins{

	private JsObject $iteratorPrototype;

	public function __construct(
		private Interpreter $js
	){}

	public function install() : void{
		$js = $this->js;
		$proto = $js->arrayPrototype;
		$construct = function(array $args, JsObject $newTarget) use ($js) : JsObject{
			$array = new JsArray($js->prototypeFor($newTarget, $js->arrayPrototype));
			if(count($args) === 1 && (is_int($args[0]) || is_float($args[0]))){
				$length = $args[0];
				if(!is_int($length) || $length < 0 || $length > 50_000_000){
					$js->throwError("RangeError", "Invalid array length");
				}
				$array->items = $length === 0 ? [] : array_fill(0, $length, null);
				return $array;
			}
			$array->items = $args;
			return $array;
		};
		$array = $js->makeClass("Array", $proto, $construct, function(mixed $thisValue, array $args) use ($construct, $js) : mixed{
			return $construct($args, $js->constructors["Array"]);
		}, 1);
		$js->constructors["Array"] = $array;
		$js->defineHidden($js->global, "Array", $array);

		$js->defineMethod($array, "isArray", 1, function(mixed $thisValue, array $args) : mixed{
			$value = $args[0] ?? null;
			return $value instanceof JsArray && $value->className === "Array";
		});
		$js->defineMethod($array, "of", 0, function(mixed $thisValue, array $args) use ($js) : mixed{
			return $js->newArray($args);
		});
		$js->defineMethod($array, "from", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$source = $args[0] ?? null;
			$mapper = $args[1] ?? null;
			if($source === null || $source instanceof JsNull){
				$js->throwError("TypeError", "Array.from requires an array-like object");
			}
			if(is_string($source) || ($source instanceof JsObject && $js->getSymbol($source, $js->symIterator) instanceof JsCallable)){
				$items = $js->iterableToList($source);
			}elseif($source instanceof JsObject){
				$length = $js->toIntegerOrInfinity($js->get($source, "length"));
				$items = [];
				for($i = 0; $i < $length && $i < 50_000_000; ++$i){
					$items[] = $js->get($source, (string) $i);
				}
			}else{
				$items = [];
			}
			if($mapper instanceof JsCallable){
				foreach($items as $index => $item){
					$items[$index] = $js->call($mapper, $args[2] ?? null, [$item, $index]);
				}
			}
			return $js->newArray($items);
		});

		$this->iteratorPrototype = new JsObject($js->iteratorPrototype);
		$this->iteratorPrototype->symbols[spl_object_id($js->symToStringTag)] = [$js->symToStringTag, "Array Iterator"];
		$js->defineMethod($this->iteratorPrototype, "next", 0, function(mixed $thisValue, array $args) use ($js) : mixed{
			if(!$thisValue instanceof JsObject || !is_array($thisValue->host) || !isset($thisValue->host["kind"])){
				$js->throwError("TypeError", "next method called on incompatible receiver");
			}
			$state = $thisValue->host;
			$source = $state["source"];
			$items = $source instanceof JsArray ? $source->items : [];
			$index = $state["index"];
			if($state["done"] || $index >= count($items)){
				$thisValue->host["done"] = true;
				return $js->iterResult(null, true);
			}
			$thisValue->host["index"] = $index + 1;
			return match($state["kind"]){
				"keys" => $js->iterResult($index, false),
				"entries" => $js->iterResult($js->newArray([$index, $items[$index]]), false),
				default => $js->iterResult($items[$index], false)
			};
		});

		$this->installAccessors($proto);
		$this->installSearch($proto);
		$this->installIteration($proto);
		$this->installTransforms($proto);
	}

	/**
	 * Creates an iterator over an array, of the given kind: "values",
	 * "keys" or "entries".
	 */
	public function createIterator(JsArray $array, string $kind) : JsObject{
		$iterator = new JsObject($this->iteratorPrototype);
		$iterator->className = "Array Iterator";
		$iterator->host = ["source" => $array, "index" => 0, "kind" => $kind, "done" => false];
		return $iterator;
	}

	private function thisArray(mixed $thisValue) : JsArray{
		if($thisValue instanceof JsArray){
			return $thisValue;
		}
		$js = $this->js;
		if($thisValue instanceof JsObject){
			$length = $js->toIntegerOrInfinity($js->get($thisValue, "length"));
			$items = [];
			for($i = 0; $i < $length && $i < 1_000_000; ++$i){
				$items[] = $js->get($thisValue, (string) $i);
			}
			return $js->newArray($items);
		}
		if(is_string($thisValue)){
			return $js->newArray($js->iterableToList($thisValue));
		}
		$js->throwError("TypeError", "Array.prototype method called on incompatible receiver");
	}

	private function mutable(mixed $thisValue) : JsArray{
		if(!$thisValue instanceof JsArray){
			$this->js->throwError("TypeError", "Array.prototype method called on incompatible receiver");
		}
		if($thisValue->frozen){
			$this->js->throwError("TypeError", "Cannot modify a frozen array");
		}
		return $thisValue;
	}

	private function relativeIndex(mixed $value, int $length, int $default) : int{
		if($value === null){
			return $default;
		}
		$index = $this->js->toIntegerOrInfinity($value);
		if($index < 0){
			return (int) max(0, $length + $index);
		}
		return (int) min($index, $length);
	}

	private function callback(mixed $value) : JsCallable{
		if(!$value instanceof JsCallable){
			$this->js->throwError("TypeError", $this->js->describeValue($value) . " is not a function");
		}
		return $value;
	}

	private function installAccessors(JsObject $proto) : void{
		$js = $this->js;
		$js->defineMethod($proto, "push", 1, function(mixed $thisValue, array $args) : mixed{
			$array = $this->mutable($thisValue);
			foreach($args as $arg){
				$array->items[] = $arg;
			}
			return count($array->items);
		});
		$js->defineMethod($proto, "pop", 0, function(mixed $thisValue, array $args) : mixed{
			$array = $this->mutable($thisValue);
			if(count($array->items) === 0){
				return null;
			}
			return \array_pop($array->items);
		});
		$js->defineMethod($proto, "shift", 0, function(mixed $thisValue, array $args) : mixed{
			$array = $this->mutable($thisValue);
			if(count($array->items) === 0){
				return null;
			}
			return \array_shift($array->items);
		});
		$js->defineMethod($proto, "unshift", 1, function(mixed $thisValue, array $args) : mixed{
			$array = $this->mutable($thisValue);
			$array->items = array_merge($args, $array->items);
			return count($array->items);
		});
		$js->defineMethod($proto, "slice", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			$array = $this->thisArray($thisValue);
			$length = count($array->items);
			$start = $this->relativeIndex($args[0] ?? null, $length, 0);
			$end = $this->relativeIndex($args[1] ?? null, $length, $length);
			return $js->newArray($end > $start ? array_slice($array->items, $start, $end - $start) : []);
		});
		$js->defineMethod($proto, "splice", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			$array = $this->mutable($thisValue);
			$length = count($array->items);
			$start = $this->relativeIndex($args[0] ?? null, $length, 0);
			if(count($args) === 0){
				$deleteCount = 0;
			}elseif(count($args) === 1){
				$deleteCount = $length - $start;
			}else{
				$deleteCount = (int) max(0, min($js->toIntegerOrInfinity($args[1]), $length - $start));
			}
			$removed = array_splice($array->items, $start, $deleteCount, array_slice($args, 2));
			return $js->newArray($removed);
		});
		$js->defineMethod($proto, "toSpliced", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			$items = $this->thisArray($thisValue)->items;
			$length = count($items);
			$start = $this->relativeIndex($args[0] ?? null, $length, 0);
			$deleteCount = count($args) < 2 ? $length - $start : (int) max(0, min($js->toIntegerOrInfinity($args[1]), $length - $start));
			array_splice($items, $start, $deleteCount, array_slice($args, 2));
			return $js->newArray($items);
		});
		$js->defineMethod($proto, "concat", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$items = $this->thisArray($thisValue)->items;
			foreach($args as $arg){
				if($arg instanceof JsArray && $arg->className === "Array"){
					foreach($arg->items as $item){
						$items[] = $item;
					}
				}else{
					$items[] = $arg;
				}
			}
			return $js->newArray($items);
		});
		$js->defineMethod($proto, "join", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$separator = ($args[0] ?? null) === null ? "," : $js->toString($args[0]);
			return $this->join($this->thisArray($thisValue), $separator);
		});
		$js->defineMethod($proto, "toString", 0, function(mixed $thisValue, array $args) : mixed{
			return $this->join($this->thisArray($thisValue), ",");
		});
		$js->defineMethod($proto, "toLocaleString", 0, function(mixed $thisValue, array $args) : mixed{
			return $this->join($this->thisArray($thisValue), ",");
		});
		$js->defineMethod($proto, "reverse", 0, function(mixed $thisValue, array $args) : mixed{
			$array = $this->mutable($thisValue);
			$array->items = array_reverse($array->items);
			return $array;
		});
		$js->defineMethod($proto, "toReversed", 0, function(mixed $thisValue, array $args) use ($js) : mixed{
			return $js->newArray(array_reverse($this->thisArray($thisValue)->items));
		});
		$js->defineMethod($proto, "at", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$items = $this->thisArray($thisValue)->items;
			$index = (int) $js->toIntegerOrInfinity($args[0] ?? null);
			if($index < 0){
				$index += count($items);
			}
			return $items[$index] ?? null;
		});
		$js->defineMethod($proto, "with", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			$items = $this->thisArray($thisValue)->items;
			$index = (int) $js->toIntegerOrInfinity($args[0] ?? null);
			if($index < 0){
				$index += count($items);
			}
			if($index < 0 || $index >= count($items)){
				$js->throwError("RangeError", "Invalid index");
			}
			$items[$index] = $args[1] ?? null;
			return $js->newArray($items);
		});
		$js->defineMethod($proto, "fill", 1, function(mixed $thisValue, array $args) : mixed{
			$array = $this->mutable($thisValue);
			$length = count($array->items);
			$start = $this->relativeIndex($args[1] ?? null, $length, 0);
			$end = $this->relativeIndex($args[2] ?? null, $length, $length);
			for($i = $start; $i < $end; ++$i){
				$array->items[$i] = $args[0] ?? null;
			}
			return $array;
		});
		$js->defineMethod($proto, "copyWithin", 2, function(mixed $thisValue, array $args) : mixed{
			$array = $this->mutable($thisValue);
			$length = count($array->items);
			$target = $this->relativeIndex($args[0] ?? null, $length, 0);
			$start = $this->relativeIndex($args[1] ?? null, $length, 0);
			$end = $this->relativeIndex($args[2] ?? null, $length, $length);
			$chunk = array_slice($array->items, $start, max(0, $end - $start));
			foreach($chunk as $offset => $item){
				if($target + $offset < $length){
					$array->items[$target + $offset] = $item;
				}
			}
			return $array;
		});
	}

	private function join(JsArray $array, string $separator) : string{
		$parts = [];
		foreach($array->items as $item){
			$parts[] = ($item === null || $item instanceof JsNull) ? "" : $this->js->toString($item);
		}
		return implode($separator, $parts);
	}

	private function installSearch(JsObject $proto) : void{
		$js = $this->js;
		$js->defineMethod($proto, "indexOf", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$items = $this->thisArray($thisValue)->items;
			$start = $this->relativeIndex($args[1] ?? null, count($items), 0);
			$search = $args[0] ?? null;
			for($i = $start, $count = count($items); $i < $count; ++$i){
				if($js->strictEquals($items[$i], $search)){
					return $i;
				}
			}
			return -1;
		});
		$js->defineMethod($proto, "lastIndexOf", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$items = $this->thisArray($thisValue)->items;
			$search = $args[0] ?? null;
			$start = count($args) > 1 ? (int) $js->toIntegerOrInfinity($args[1]) : count($items) - 1;
			if($start < 0){
				$start += count($items);
			}
			for($i = min($start, count($items) - 1); $i >= 0; --$i){
				if($js->strictEquals($items[$i], $search)){
					return $i;
				}
			}
			return -1;
		});
		$js->defineMethod($proto, "includes", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$items = $this->thisArray($thisValue)->items;
			$start = $this->relativeIndex($args[1] ?? null, count($items), 0);
			$search = $args[0] ?? null;
			for($i = $start, $count = count($items); $i < $count; ++$i){
				if($js->sameValueZero($items[$i], $search)){
					return true;
				}
			}
			return false;
		});
		$finders = [
			"find" => [false, false],
			"findIndex" => [false, true],
			"findLast" => [true, false],
			"findLastIndex" => [true, true]
		];
		foreach($finders as $name => [$reverse, $index]){
			$js->defineMethod($proto, $name, 1, function(mixed $thisValue, array $args) use ($js, $reverse, $index) : mixed{
				$array = $this->thisArray($thisValue);
				$callback = $this->callback($args[0] ?? null);
				$count = count($array->items);
				for($step = 0; $step < $count; ++$step){
					$i = $reverse ? $count - 1 - $step : $step;
					$item = $array->items[$i] ?? null;
					if($js->toBoolean($js->call($callback, $args[1] ?? null, [$item, $i, $array]))){
						return $index ? $i : $item;
					}
				}
				return $index ? -1 : null;
			});
		}
	}

	private function installIteration(JsObject $proto) : void{
		$js = $this->js;
		$js->defineMethod($proto, "forEach", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$array = $this->thisArray($thisValue);
			$callback = $this->callback($args[0] ?? null);
			for($i = 0; $i < count($array->items); ++$i){
				$js->call($callback, $args[1] ?? null, [$array->items[$i], $i, $array]);
			}
			return null;
		});
		$js->defineMethod($proto, "map", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$array = $this->thisArray($thisValue);
			$callback = $this->callback($args[0] ?? null);
			$result = [];
			$count = count($array->items);
			for($i = 0; $i < $count; ++$i){
				$result[] = $js->call($callback, $args[1] ?? null, [$array->items[$i] ?? null, $i, $array]);
			}
			return $js->newArray($result);
		});
		$js->defineMethod($proto, "filter", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$array = $this->thisArray($thisValue);
			$callback = $this->callback($args[0] ?? null);
			$result = [];
			$count = count($array->items);
			for($i = 0; $i < $count; ++$i){
				$item = $array->items[$i] ?? null;
				if($js->toBoolean($js->call($callback, $args[1] ?? null, [$item, $i, $array]))){
					$result[] = $item;
				}
			}
			return $js->newArray($result);
		});
		$js->defineMethod($proto, "some", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$array = $this->thisArray($thisValue);
			$callback = $this->callback($args[0] ?? null);
			for($i = 0; $i < count($array->items); ++$i){
				if($js->toBoolean($js->call($callback, $args[1] ?? null, [$array->items[$i], $i, $array]))){
					return true;
				}
			}
			return false;
		});
		$js->defineMethod($proto, "every", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$array = $this->thisArray($thisValue);
			$callback = $this->callback($args[0] ?? null);
			for($i = 0; $i < count($array->items); ++$i){
				if(!$js->toBoolean($js->call($callback, $args[1] ?? null, [$array->items[$i], $i, $array]))){
					return false;
				}
			}
			return true;
		});
		$js->defineMethod($proto, "reduce", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$array = $this->thisArray($thisValue);
			$callback = $this->callback($args[0] ?? null);
			$count = count($array->items);
			$index = 0;
			if(count($args) > 1){
				$accumulator = $args[1];
			}else{
				if($count === 0){
					$js->throwError("TypeError", "Reduce of empty array with no initial value");
				}
				$accumulator = $array->items[0];
				$index = 1;
			}
			for($i = $index; $i < $count; ++$i){
				$accumulator = $js->call($callback, null, [$accumulator, $array->items[$i] ?? null, $i, $array]);
			}
			return $accumulator;
		});
		$js->defineMethod($proto, "reduceRight", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$array = $this->thisArray($thisValue);
			$callback = $this->callback($args[0] ?? null);
			$index = count($array->items) - 1;
			if(count($args) > 1){
				$accumulator = $args[1];
			}else{
				if($index < 0){
					$js->throwError("TypeError", "Reduce of empty array with no initial value");
				}
				$accumulator = $array->items[$index];
				$index--;
			}
			for($i = $index; $i >= 0; --$i){
				$accumulator = $js->call($callback, null, [$accumulator, $array->items[$i] ?? null, $i, $array]);
			}
			return $accumulator;
		});
		$values = $js->defineMethod($proto, "values", 0, function(mixed $thisValue, array $args) : mixed{
			return $this->createIterator($this->thisArray($thisValue), "values");
		});
		$js->defineMethod($proto, "keys", 0, function(mixed $thisValue, array $args) : mixed{
			return $this->createIterator($this->thisArray($thisValue), "keys");
		});
		$js->defineMethod($proto, "entries", 0, function(mixed $thisValue, array $args) : mixed{
			return $this->createIterator($this->thisArray($thisValue), "entries");
		});
		$js->defineHidden($proto, $js->symIterator, $values);
		$js->setNativeArrayIterator($values);
	}

	private function installTransforms(JsObject $proto) : void{
		$js = $this->js;
		$sort = function(array $items, mixed $comparator) use ($js) : array{
			$defined = [];
			$undefined = 0;
			foreach($items as $item){
				if($item === null){
					$undefined++;
				}else{
					$defined[] = $item;
				}
			}
			if($comparator instanceof JsCallable){
				usort($defined, function(mixed $a, mixed $b) use ($js, $comparator) : int{
					$result = $js->toNumber($js->call($comparator, null, [$a, $b]));
					if(is_float($result) && is_nan($result)){
						return 0;
					}
					return $result < 0 ? -1 : ($result > 0 ? 1 : 0);
				});
			}elseif($comparator !== null){
				$js->throwError("TypeError", "The comparison function must be either a function or undefined");
			}else{
				usort($defined, function(mixed $a, mixed $b) use ($js) : int{
					return strcmp($js->toString($a), $js->toString($b));
				});
			}
			for($i = 0; $i < $undefined; ++$i){
				$defined[] = null;
			}
			return $defined;
		};
		$js->defineMethod($proto, "sort", 1, function(mixed $thisValue, array $args) use ($sort) : mixed{
			$array = $this->mutable($thisValue);
			$array->items = $sort($array->items, $args[0] ?? null);
			return $array;
		});
		$js->defineMethod($proto, "toSorted", 1, function(mixed $thisValue, array $args) use ($sort, $js) : mixed{
			return $js->newArray($sort($this->thisArray($thisValue)->items, $args[0] ?? null));
		});
		$js->defineMethod($proto, "flat", 0, function(mixed $thisValue, array $args) use ($js) : mixed{
			$depth = ($args[0] ?? null) === null ? 1 : (int) $js->toIntegerOrInfinity($args[0]);
			return $js->newArray($this->flatten($this->thisArray($thisValue)->items, $depth));
		});
		$js->defineMethod($proto, "flatMap", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$array = $this->thisArray($thisValue);
			$callback = $this->callback($args[0] ?? null);
			$mapped = [];
			foreach($array->items as $i => $item){
				$mapped[] = $js->call($callback, $args[1] ?? null, [$item, $i, $array]);
			}
			return $js->newArray($this->flatten($mapped, 1));
		});
	}

	/**
	 * @param list<mixed> $items
	 * @return list<mixed>
	 */
	private function flatten(array $items, int $depth) : array{
		$result = [];
		foreach($items as $item){
			if($depth > 0 && $item instanceof JsArray && $item->className === "Array"){
				foreach($this->flatten($item->items, $depth - 1) as $inner){
					$result[] = $inner;
				}
			}else{
				$result[] = $item;
			}
		}
		return $result;
	}
}
