<?php

declare(strict_types=1);

namespace behaviorpack\script\js\builtin;

use behaviorpack\script\js\Interpreter;
use behaviorpack\script\js\JsCallable;
use behaviorpack\script\js\JsNull;
use behaviorpack\script\js\JsObject;
use behaviorpack\script\js\JsSymbol;
use function array_key_exists;
use function array_keys;
use function count;
use function floor;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_nan;
use function is_string;
use function spl_object_id;

/**
 * Map, Set, WeakMap and WeakSet. Entries are kept in insertion order in PHP
 * arrays keyed by a normalized form of the key.
 */
final class CollectionBuiltins{

	private JsObject $iteratorPrototype;

	public function __construct(
		private Interpreter $js
	){}

	public function install() : void{
		$js = $this->js;
		$this->iteratorPrototype = new JsObject($js->iteratorPrototype);
		$js->defineMethod($this->iteratorPrototype, "next", 0, function(mixed $thisValue, array $args) use ($js) : mixed{
			if(!$thisValue instanceof JsObject || !is_array($thisValue->host) || !isset($thisValue->host["collection"])){
				$js->throwError("TypeError", "next method called on incompatible receiver");
			}
			$state = &$thisValue->host;
			$collection = $state["collection"]->host;
			while($state["position"] < count($state["keys"])){
				$key = $state["keys"][$state["position"]++];
				if(!array_key_exists($key, $collection["keys"])){
					continue;
				}
				$entryKey = $collection["keys"][$key];
				$entryValue = $collection["values"][$key];
				return match($state["kind"]){
					"keys" => $js->iterResult($entryKey, false),
					"values" => $js->iterResult($entryValue, false),
					default => $js->iterResult($js->newArray([$entryKey, $entryValue]), false)
				};
			}
			return $js->iterResult(null, true);
		});
		$this->installMap("Map", false);
		$this->installMap("WeakMap", true);
		$this->installSet("Set", false);
		$this->installSet("WeakSet", true);
	}

	public function keyOf(mixed $value) : string{
		if($value instanceof JsObject || $value instanceof JsSymbol){
			return "o" . spl_object_id($value);
		}
		if(is_string($value)){
			return "s" . $value;
		}
		if(is_int($value)){
			return "n" . $value;
		}
		if(is_float($value)){
			if(is_nan($value)){
				return "nan";
			}
			if($value === floor($value) && $value > -9.0e15 && $value < 9.0e15){
				return "n" . (int) $value;
			}
			return "f" . Interpreter::numberToString($value);
		}
		if(is_bool($value)){
			return $value ? "b1" : "b0";
		}
		if($value instanceof JsNull){
			return "l";
		}
		return "u";
	}

	private function collection(mixed $thisValue, string $className) : JsObject{
		if(!$thisValue instanceof JsObject || $thisValue->className !== $className || !is_array($thisValue->host)){
			$this->js->throwError("TypeError", "Method " . $className . ".prototype called on incompatible receiver");
		}
		return $thisValue;
	}

	private function iterator(JsObject $collection, string $kind) : JsObject{
		$iterator = new JsObject($this->iteratorPrototype);
		$iterator->className = $collection->className . " Iterator";
		$iterator->host = ["collection" => $collection, "keys" => array_keys($collection->host["keys"]), "position" => 0, "kind" => $kind];
		return $iterator;
	}

	private function checkWeakKey(mixed $key, bool $weak) : void{
		if($weak && !$key instanceof JsObject && !$key instanceof JsSymbol){
			$this->js->throwError("TypeError", "Invalid value used as weak collection key");
		}
	}

	private function installMap(string $name, bool $weak) : void{
		$js = $this->js;
		$proto = new JsObject($js->objectPrototype);
		$proto->symbols[spl_object_id($js->symToStringTag)] = [$js->symToStringTag, $name];
		$constructor = $js->makeClass($name, $proto, function(array $args, JsObject $newTarget) use ($js, $proto, $name, $weak) : JsObject{
			$map = new JsObject($js->prototypeFor($newTarget, $proto));
			$map->className = $name;
			$map->host = ["keys" => [], "values" => []];
			$source = $args[0] ?? null;
			if($source !== null && !$source instanceof JsNull){
				foreach($js->iterate($source) as $entry){
					if(!$entry instanceof JsObject){
						$js->throwError("TypeError", "Iterator value " . $js->toStringSafe($entry) . " is not an entry object");
					}
					$key = $js->get($entry, "0");
					$this->checkWeakKey($key, $weak);
					$normalized = $this->keyOf($key);
					$map->host["keys"][$normalized] = $key;
					$map->host["values"][$normalized] = $js->get($entry, "1");
				}
			}
			return $map;
		}, null, 0);
		$js->constructors[$name] = $constructor;
		$js->defineHidden($js->global, $name, $constructor);

		$js->defineMethod($proto, "get", 1, function(mixed $thisValue, array $args) use ($name) : mixed{
			$map = $this->collection($thisValue, $name);
			return $map->host["values"][$this->keyOf($args[0] ?? null)] ?? null;
		});
		$js->defineMethod($proto, "set", 2, function(mixed $thisValue, array $args) use ($name, $weak) : mixed{
			$map = $this->collection($thisValue, $name);
			$key = $args[0] ?? null;
			$this->checkWeakKey($key, $weak);
			if(is_float($key) && $key == 0.0){
				$key = 0;
			}
			$normalized = $this->keyOf($key);
			if(!array_key_exists($normalized, $map->host["keys"])){
				$map->host["keys"][$normalized] = $key;
			}
			$map->host["values"][$normalized] = $args[1] ?? null;
			return $map;
		});
		$js->defineMethod($proto, "has", 1, function(mixed $thisValue, array $args) use ($name) : mixed{
			$map = $this->collection($thisValue, $name);
			return array_key_exists($this->keyOf($args[0] ?? null), $map->host["keys"]);
		});
		$js->defineMethod($proto, "delete", 1, function(mixed $thisValue, array $args) use ($name) : mixed{
			$map = $this->collection($thisValue, $name);
			$normalized = $this->keyOf($args[0] ?? null);
			if(!array_key_exists($normalized, $map->host["keys"])){
				return false;
			}
			unset($map->host["keys"][$normalized], $map->host["values"][$normalized]);
			return true;
		});
		if($weak){
			return;
		}
		$js->defineMethod($proto, "clear", 0, function(mixed $thisValue, array $args) use ($name) : mixed{
			$map = $this->collection($thisValue, $name);
			$map->host = ["keys" => [], "values" => []];
			return null;
		});
		$js->defineGetter($proto, "size", function(mixed $thisValue) use ($name) : mixed{
			return count($this->collection($thisValue, $name)->host["keys"]);
		});
		$js->defineMethod($proto, "forEach", 1, function(mixed $thisValue, array $args) use ($js, $name) : mixed{
			$map = $this->collection($thisValue, $name);
			$callback = $args[0] ?? null;
			if(!$callback instanceof JsCallable){
				$js->throwError("TypeError", $js->describeValue($callback) . " is not a function");
			}
			foreach(array_keys($map->host["keys"]) as $normalized){
				if(!array_key_exists($normalized, $map->host["keys"])){
					continue;
				}
				$js->call($callback, $args[1] ?? null, [$map->host["values"][$normalized], $map->host["keys"][$normalized], $map]);
			}
			return null;
		});
		$js->defineMethod($proto, "keys", 0, function(mixed $thisValue, array $args) use ($name) : mixed{
			return $this->iterator($this->collection($thisValue, $name), "keys");
		});
		$js->defineMethod($proto, "values", 0, function(mixed $thisValue, array $args) use ($name) : mixed{
			return $this->iterator($this->collection($thisValue, $name), "values");
		});
		$entries = $js->defineMethod($proto, "entries", 0, function(mixed $thisValue, array $args) use ($name) : mixed{
			return $this->iterator($this->collection($thisValue, $name), "entries");
		});
		$js->defineHidden($proto, $js->symIterator, $entries);
		$js->defineMethod($constructor, "groupBy", 2, function(mixed $thisValue, array $args) use ($js, $proto, $name) : mixed{
			$map = new JsObject($proto);
			$map->className = $name;
			$map->host = ["keys" => [], "values" => []];
			$index = 0;
			foreach($js->iterate($args[0] ?? null) as $value){
				$key = $js->call($args[1] ?? null, null, [$value, $index++]);
				$normalized = $this->keyOf($key);
				if(!array_key_exists($normalized, $map->host["keys"])){
					$map->host["keys"][$normalized] = $key;
					$map->host["values"][$normalized] = $js->newArray();
				}
				$map->host["values"][$normalized]->items[] = $value;
			}
			return $map;
		});
	}

	private function installSet(string $name, bool $weak) : void{
		$js = $this->js;
		$proto = new JsObject($js->objectPrototype);
		$proto->symbols[spl_object_id($js->symToStringTag)] = [$js->symToStringTag, $name];
		$constructor = $js->makeClass($name, $proto, function(array $args, JsObject $newTarget) use ($js, $proto, $name, $weak) : JsObject{
			$set = new JsObject($js->prototypeFor($newTarget, $proto));
			$set->className = $name;
			$set->host = ["keys" => [], "values" => []];
			$source = $args[0] ?? null;
			if($source !== null && !$source instanceof JsNull){
				foreach($js->iterate($source) as $value){
					$this->checkWeakKey($value, $weak);
					$normalized = $this->keyOf($value);
					$set->host["keys"][$normalized] = $value;
					$set->host["values"][$normalized] = $value;
				}
			}
			return $set;
		}, null, 0);
		$js->constructors[$name] = $constructor;
		$js->defineHidden($js->global, $name, $constructor);

		$js->defineMethod($proto, "add", 1, function(mixed $thisValue, array $args) use ($name, $weak) : mixed{
			$set = $this->collection($thisValue, $name);
			$value = $args[0] ?? null;
			$this->checkWeakKey($value, $weak);
			if(is_float($value) && $value == 0.0){
				$value = 0;
			}
			$normalized = $this->keyOf($value);
			if(!array_key_exists($normalized, $set->host["keys"])){
				$set->host["keys"][$normalized] = $value;
				$set->host["values"][$normalized] = $value;
			}
			return $set;
		});
		$js->defineMethod($proto, "has", 1, function(mixed $thisValue, array $args) use ($name) : mixed{
			$set = $this->collection($thisValue, $name);
			return array_key_exists($this->keyOf($args[0] ?? null), $set->host["keys"]);
		});
		$js->defineMethod($proto, "delete", 1, function(mixed $thisValue, array $args) use ($name) : mixed{
			$set = $this->collection($thisValue, $name);
			$normalized = $this->keyOf($args[0] ?? null);
			if(!array_key_exists($normalized, $set->host["keys"])){
				return false;
			}
			unset($set->host["keys"][$normalized], $set->host["values"][$normalized]);
			return true;
		});
		if($weak){
			return;
		}
		$js->defineMethod($proto, "clear", 0, function(mixed $thisValue, array $args) use ($name) : mixed{
			$set = $this->collection($thisValue, $name);
			$set->host = ["keys" => [], "values" => []];
			return null;
		});
		$js->defineGetter($proto, "size", function(mixed $thisValue) use ($name) : mixed{
			return count($this->collection($thisValue, $name)->host["keys"]);
		});
		$js->defineMethod($proto, "forEach", 1, function(mixed $thisValue, array $args) use ($js, $name) : mixed{
			$set = $this->collection($thisValue, $name);
			$callback = $args[0] ?? null;
			if(!$callback instanceof JsCallable){
				$js->throwError("TypeError", $js->describeValue($callback) . " is not a function");
			}
			foreach(array_keys($set->host["keys"]) as $normalized){
				if(!array_key_exists($normalized, $set->host["keys"])){
					continue;
				}
				$value = $set->host["keys"][$normalized];
				$js->call($callback, $args[1] ?? null, [$value, $value, $set]);
			}
			return null;
		});
		$values = $js->defineMethod($proto, "values", 0, function(mixed $thisValue, array $args) use ($name) : mixed{
			return $this->iterator($this->collection($thisValue, $name), "values");
		});
		$js->defineHidden($proto, "keys", $values);
		$js->defineMethod($proto, "entries", 0, function(mixed $thisValue, array $args) use ($name) : mixed{
			return $this->iterator($this->collection($thisValue, $name), "entries");
		});
		$js->defineHidden($proto, $js->symIterator, $values);
	}
}
