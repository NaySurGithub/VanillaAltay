<?php

declare(strict_types=1);

namespace behaviorpack\script\js\builtin;

use behaviorpack\script\js\Interpreter;
use behaviorpack\script\js\JsArray;
use behaviorpack\script\js\JsCallable;
use behaviorpack\script\js\JsFunction;
use behaviorpack\script\js\JsNull;
use behaviorpack\script\js\JsObject;
use behaviorpack\script\js\JsPromise;
use behaviorpack\script\js\JsSymbol;
use behaviorpack\script\js\JsThrow;
use behaviorpack\script\js\NativeFunction;
use stdClass;
use function abs;
use function acos;
use function acosh;
use function array_key_exists;
use function array_pop;
use function array_slice;
use function asin;
use function asinh;
use function atan;
use function atan2;
use function atanh;
use function base_convert;
use function ceil;
use function cos;
use function cosh;
use function count;
use function exp;
use function expm1;
use function floor;
use function fmod;
use function get_object_vars;
use function implode;
use function in_array;
use function intdiv;
use function is_array;
use function is_bool;
use function is_finite;
use function is_float;
use function is_infinite;
use function is_int;
use function is_nan;
use function is_string;
use function json_decode;
use function json_encode;
use function log;
use function log10;
use function log1p;
use function max;
use function min;
use function mt_getrandmax;
use function mt_rand;
use function number_format;
use function pack;
use function pow;
use function preg_match;
use function preg_replace_callback;
use function rawurldecode;
use function rawurlencode;
use function round;
use function rtrim;
use function sin;
use function sinh;
use function spl_object_id;
use function sprintf;
use function sqrt;
use function str_contains;
use function str_repeat;
use function strlen;
use function strpos;
use function strtolower;
use function strtr;
use function substr;
use function tan;
use function tanh;
use function trim;
use function unpack;
use const INF;
use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const M_E;
use const M_LN10;
use const M_LN2;
use const M_LOG10E;
use const M_LOG2E;
use const M_PI;
use const M_SQRT1_2;
use const M_SQRT2;
use const NAN;

/**
 * Object, Function, Symbol, errors, Boolean, Number, Math, JSON, Reflect,
 * console and the global functions.
 */
final class CoreBuiltins{

	private const ERROR_TYPES = ["TypeError", "RangeError", "SyntaxError", "ReferenceError", "EvalError", "URIError", "AggregateError"];

	/** @var array<string, JsSymbol> */
	private array $registry = [];

	public function __construct(
		private Interpreter $js
	){}

	public function install() : void{
		$js = $this->js;
		$global = $js->global;
		$js->defineHidden($global, "globalThis", $global);
		$global->props["undefined"] = null;
		$global->props["NaN"] = NAN;
		$global->props["Infinity"] = INF;
		$global->hidden["undefined"] = true;
		$global->hidden["NaN"] = true;
		$global->hidden["Infinity"] = true;
		$global->locked["undefined"] = true;
		$global->locked["NaN"] = true;
		$global->locked["Infinity"] = true;

		$this->installObject();
		$this->installFunction();
		$this->installSymbol();
		$this->installErrors();
		$this->installBoolean();
		$this->installNumber();
		$this->installMath();
		$this->installJson();
		$this->installReflect();
		$this->installGlobals();
		$this->installConsole();

		$js->defineMethod($js->iteratorPrototype, $js->symIterator, 0, function(mixed $thisValue, array $args) : mixed{
			return $thisValue;
		});
	}

	private function installObject() : void{
		$js = $this->js;
		$proto = $js->objectPrototype;
		$construct = function(array $args, JsObject $newTarget) use ($js) : JsObject{
			$value = $args[0] ?? null;
			if($value === null || $value instanceof JsNull){
				return $js->newObject($js->prototypeFor($newTarget, $js->objectPrototype));
			}
			return $js->toObject($value);
		};
		$object = $js->makeClass("Object", $proto, $construct, function(mixed $thisValue, array $args) use ($construct, $js) : mixed{
			return $construct($args, $js->constructors["Object"]);
		}, 1);
		$js->constructors["Object"] = $object;
		$js->defineHidden($js->global, "Object", $object);

		$js->defineMethod($object, "keys", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$target = $js->toObject($args[0] ?? null);
			return $js->newArray($this->enumerableKeys($target));
		});
		$js->defineMethod($object, "values", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$target = $js->toObject($args[0] ?? null);
			$values = [];
			foreach($this->enumerableKeys($target) as $key){
				$values[] = $js->getFrom($target, $key, $target);
			}
			return $js->newArray($values);
		});
		$js->defineMethod($object, "entries", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$target = $js->toObject($args[0] ?? null);
			$entries = [];
			foreach($this->enumerableKeys($target) as $key){
				$entries[] = $js->newArray([$key, $js->getFrom($target, $key, $target)]);
			}
			return $js->newArray($entries);
		});
		$js->defineMethod($object, "assign", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			$target = $js->toObject($args[0] ?? null);
			foreach(array_slice($args, 1) as $source){
				if($source instanceof JsObject){
					foreach($this->enumerableKeys($source) as $key){
						$js->set($target, $key, $js->getFrom($source, $key, $source));
					}
					foreach($source->symbols as [$symbol, $value]){
						$js->set($target, $symbol, $value);
					}
				}elseif(is_string($source)){
					$js->copyDataProperties($target, $source);
				}
			}
			return $target;
		});
		$js->defineMethod($object, "freeze", 1, function(mixed $thisValue, array $args) : mixed{
			$target = $args[0] ?? null;
			if($target instanceof JsObject){
				$this->freeze($target);
			}
			return $target;
		});
		$js->defineMethod($object, "isFrozen", 1, function(mixed $thisValue, array $args) : mixed{
			$target = $args[0] ?? null;
			if(!$target instanceof JsObject){
				return true;
			}
			if($target->extensible || ($target instanceof JsArray && !$target->frozen && count($target->items) > 0)){
				return false;
			}
			foreach($target->props as $key => $unused){
				if(!isset($target->locked[$key])){
					return false;
				}
			}
			return true;
		});
		$js->defineMethod($object, "seal", 1, function(mixed $thisValue, array $args) : mixed{
			$target = $args[0] ?? null;
			if($target instanceof JsObject){
				$target->extensible = false;
			}
			return $target;
		});
		$js->defineMethod($object, "isSealed", 1, function(mixed $thisValue, array $args) : mixed{
			$target = $args[0] ?? null;
			return !$target instanceof JsObject || !$target->extensible;
		});
		$js->defineMethod($object, "preventExtensions", 1, function(mixed $thisValue, array $args) : mixed{
			$target = $args[0] ?? null;
			if($target instanceof JsObject){
				$target->extensible = false;
			}
			return $target;
		});
		$js->defineMethod($object, "isExtensible", 1, function(mixed $thisValue, array $args) : mixed{
			$target = $args[0] ?? null;
			return $target instanceof JsObject && $target->extensible;
		});
		$js->defineMethod($object, "create", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			$proto = $args[0] ?? null;
			if(!$proto instanceof JsObject && !$proto instanceof JsNull){
				$js->throwError("TypeError", "Object prototype may only be an Object or null");
			}
			$created = new JsObject($proto instanceof JsObject ? $proto : null);
			if(($args[1] ?? null) instanceof JsObject){
				$this->defineProperties($created, $args[1]);
			}
			return $created;
		});
		$js->defineMethod($object, "getPrototypeOf", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$target = $args[0] ?? null;
			if(is_string($target)){
				return $js->stringPrototype;
			}
			if(is_int($target) || is_float($target)){
				return $js->numberPrototype;
			}
			if(is_bool($target)){
				return $js->booleanPrototype;
			}
			$target = $js->toObject($target);
			return $target->proto ?? JsNull::get();
		});
		$js->defineMethod($object, "setPrototypeOf", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			$target = $args[0] ?? null;
			$proto = $args[1] ?? null;
			if($target instanceof JsObject){
				if($proto instanceof JsObject){
					$target->proto = $proto;
				}elseif($proto instanceof JsNull){
					$target->proto = null;
				}else{
					$js->throwError("TypeError", "Object prototype may only be an Object or null");
				}
			}
			return $target;
		});
		$js->defineMethod($object, "defineProperty", 3, function(mixed $thisValue, array $args) use ($js) : mixed{
			$target = $args[0] ?? null;
			if(!$target instanceof JsObject){
				$js->throwError("TypeError", "Object.defineProperty called on non-object");
			}
			$descriptor = $args[2] ?? null;
			if(!$descriptor instanceof JsObject){
				$js->throwError("TypeError", "Property description must be an object");
			}
			$this->defineProperty($target, $js->toPropertyKey($args[1] ?? null), $descriptor);
			return $target;
		});
		$js->defineMethod($object, "defineProperties", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			$target = $args[0] ?? null;
			if(!$target instanceof JsObject || !($args[1] ?? null) instanceof JsObject){
				$js->throwError("TypeError", "Object.defineProperties called on non-object");
			}
			$this->defineProperties($target, $args[1]);
			return $target;
		});
		$js->defineMethod($object, "getOwnPropertyNames", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$target = $js->toObject($args[0] ?? null);
			$keys = $js->ownKeys($target, true);
			if($target instanceof JsArray){
				$keys[] = "length";
			}
			return $js->newArray($keys);
		});
		$js->defineMethod($object, "getOwnPropertySymbols", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			return $js->newArray($js->ownSymbols($js->toObject($args[0] ?? null)));
		});
		$js->defineMethod($object, "getOwnPropertyDescriptor", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			return $this->descriptor($js->toObject($args[0] ?? null), $js->toPropertyKey($args[1] ?? null));
		});
		$js->defineMethod($object, "getOwnPropertyDescriptors", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$target = $js->toObject($args[0] ?? null);
			$result = $js->newObject();
			foreach($js->ownKeys($target, true) as $key){
				$result->props[$key] = $this->descriptor($target, $key);
			}
			return $result;
		});
		$js->defineMethod($object, "fromEntries", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$result = $js->newObject();
			foreach($js->iterate($args[0] ?? null) as $entry){
				$key = $js->toPropertyKey($js->get($entry, "0"));
				$value = $js->get($entry, "1");
				if($key instanceof JsSymbol){
					$result->symbols[spl_object_id($key)] = [$key, $value];
				}else{
					$js->createDataProperty($result, $key, $value);
				}
			}
			return $result;
		});
		$js->defineMethod($object, "hasOwn", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			return $js->hasOwnProperty($js->toObject($args[0] ?? null), $js->toPropertyKey($args[1] ?? null));
		});
		$js->defineMethod($object, "is", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			$a = $args[0] ?? null;
			$b = $args[1] ?? null;
			if(is_float($a) && is_float($b) && is_nan($a) && is_nan($b)){
				return true;
			}
			return $js->strictEquals($a, $b);
		});
		$js->defineMethod($object, "groupBy", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			$result = new JsObject(null);
			$index = 0;
			foreach($js->iterate($args[0] ?? null) as $value){
				$key = $js->toPropertyKey($js->call($args[1] ?? null, null, [$value, $index++]));
				if($key instanceof JsSymbol){
					continue;
				}
				$group = $result->props[$key] ?? null;
				if(!$group instanceof JsArray){
					$group = $js->newArray();
					$result->props[$key] = $group;
				}
				$group->items[] = $value;
			}
			return $result;
		});

		$js->defineMethod($proto, "hasOwnProperty", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			return $js->hasOwnProperty($js->toObject($thisValue), $js->toPropertyKey($args[0] ?? null));
		});
		$js->defineMethod($proto, "isPrototypeOf", 1, function(mixed $thisValue, array $args) : mixed{
			$value = $args[0] ?? null;
			if(!$value instanceof JsObject || !$thisValue instanceof JsObject){
				return false;
			}
			for($current = $value->proto; $current !== null; $current = $current->proto){
				if($current === $thisValue){
					return true;
				}
			}
			return false;
		});
		$js->defineMethod($proto, "propertyIsEnumerable", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$target = $js->toObject($thisValue);
			$key = $js->toPropertyKey($args[0] ?? null);
			if($key instanceof JsSymbol){
				return isset($target->symbols[spl_object_id($key)]);
			}
			return $js->hasOwnProperty($target, $key) && $js->isEnumerable($target, $key);
		});
		$js->defineMethod($proto, "toString", 0, function(mixed $thisValue, array $args) use ($js) : mixed{
			return "[object " . $this->tag($thisValue) . "]";
		});
		$js->defineMethod($proto, "toLocaleString", 0, function(mixed $thisValue, array $args) use ($js) : mixed{
			return $js->call($js->get($thisValue, "toString"), $thisValue, []);
		});
		$js->defineMethod($proto, "valueOf", 0, function(mixed $thisValue, array $args) use ($js) : mixed{
			return $js->toObject($thisValue);
		});
		$getProto = $js->native("get __proto__", 0, function(mixed $thisValue, array $args) use ($js) : mixed{
			if($thisValue instanceof JsObject){
				return $thisValue->proto ?? JsNull::get();
			}
			return JsNull::get();
		});
		$setProto = $js->native("set __proto__", 1, function(mixed $thisValue, array $args) : mixed{
			$value = $args[0] ?? null;
			if($thisValue instanceof JsObject){
				if($value instanceof JsObject){
					$thisValue->proto = $value;
				}elseif($value instanceof JsNull){
					$thisValue->proto = null;
				}
			}
			return null;
		});
		$proto->accessors["__proto__"] = [$getProto, $setProto];
		$proto->hidden["__proto__"] = true;
	}

	public function tag(mixed $value) : string{
		$js = $this->js;
		if($value === null){
			return "Undefined";
		}
		if($value instanceof JsNull){
			return "Null";
		}
		if($value instanceof JsObject){
			$tag = $js->getSymbol($value, $js->symToStringTag);
			if(is_string($tag)){
				return $tag;
			}
			if($value instanceof JsArray){
				return $value->className === "Arguments" ? "Arguments" : "Array";
			}
			if($value instanceof JsCallable){
				return "Function";
			}
			return in_array($value->className, ["Error", "Boolean", "Number", "String", "Date", "RegExp"], true) ? $value->className : "Object";
		}
		return match(true){
			is_string($value) => "String",
			is_bool($value) => "Boolean",
			default => "Number"
		};
	}

	/**
	 * @return list<string>
	 */
	public function enumerableKeys(JsObject $object) : array{
		return $this->js->ownKeys($object);
	}

	public function freeze(JsObject $object) : void{
		$object->extensible = false;
		foreach($object->props as $key => $unused){
			$object->locked[$key] = true;
		}
		if($object instanceof JsArray){
			$object->frozen = true;
		}
	}

	private function defineProperties(JsObject $target, JsObject $descriptors) : void{
		foreach($this->js->ownKeys($descriptors) as $key){
			$descriptor = $this->js->getFrom($descriptors, $key, $descriptors);
			if($descriptor instanceof JsObject){
				$this->defineProperty($target, $key, $descriptor);
			}
		}
	}

	public function defineProperty(JsObject $target, string|JsSymbol $key, JsObject $descriptor) : void{
		$js = $this->js;
		$has = function(string $name) use ($js, $descriptor) : bool{
			return $js->hasProperty($descriptor, $name);
		};
		if($key instanceof JsSymbol){
			$target->symbols[spl_object_id($key)] = [$key, $has("value") ? $js->get($descriptor, "value") : ($target->symbols[spl_object_id($key)][1] ?? null)];
			return;
		}
		$exists = $js->hasOwnProperty($target, $key);
		if(!$exists && !$target->extensible){
			$js->throwError("TypeError", "Cannot define property " . $key . ", object is not extensible");
		}
		if(isset($target->locked[$key]) && !isset($target->accessors[$key])){
			if($has("value") && !$js->strictEquals($js->get($descriptor, "value"), $target->props[$key] ?? null)){
				$js->throwError("TypeError", "Cannot redefine property: " . $key);
			}
		}
		if($has("get") || $has("set")){
			$getter = $js->get($descriptor, "get");
			$setter = $js->get($descriptor, "set");
			$pair = $target->accessors[$key] ?? [null, null];
			if($has("get")){
				$pair[0] = $getter instanceof JsCallable ? $getter : null;
			}
			if($has("set")){
				$pair[1] = $setter instanceof JsCallable ? $setter : null;
			}
			unset($target->props[$key], $target->locked[$key]);
			$target->accessors[$key] = $pair;
		}else{
			if($target instanceof JsArray && Interpreter::isIndex($key)){
				if($has("value")){
					$js->setOn($target, $key, $js->get($descriptor, "value"), $target);
				}
				return;
			}
			if($has("value") || !$exists || isset($target->accessors[$key])){
				unset($target->accessors[$key]);
				$target->props[$key] = $has("value") ? $js->get($descriptor, "value") : ($target->props[$key] ?? null);
			}
			if($has("writable")){
				if($js->toBoolean($js->get($descriptor, "writable"))){
					unset($target->locked[$key]);
				}else{
					$target->locked[$key] = true;
				}
			}elseif(!$exists){
				$target->locked[$key] = true;
			}
		}
		if($has("enumerable")){
			if($js->toBoolean($js->get($descriptor, "enumerable"))){
				unset($target->hidden[$key]);
			}else{
				$target->hidden[$key] = true;
			}
		}elseif(!$exists){
			$target->hidden[$key] = true;
		}
	}

	private function descriptor(JsObject $target, string|JsSymbol $key) : mixed{
		$js = $this->js;
		$result = $js->newObject();
		if($key instanceof JsSymbol){
			$entry = $target->symbols[spl_object_id($key)] ?? null;
			if($entry === null){
				return null;
			}
			$result->props = ["value" => $entry[1], "writable" => true, "enumerable" => true, "configurable" => true];
			return $result;
		}
		if(isset($target->accessors[$key])){
			$result->props = [
				"get" => $target->accessors[$key][0],
				"set" => $target->accessors[$key][1],
				"enumerable" => !isset($target->hidden[$key]),
				"configurable" => true
			];
			return $result;
		}
		if(!$js->hasOwnProperty($target, $key)){
			return null;
		}
		$writable = !isset($target->locked[$key]) && !($target instanceof JsArray && $target->frozen);
		$result->props = [
			"value" => $js->getFrom($target, $key, $target),
			"writable" => $writable,
			"enumerable" => $js->isEnumerable($target, $key),
			"configurable" => $writable
		];
		return $result;
	}

	private function installFunction() : void{
		$js = $this->js;
		$proto = $js->functionPrototype;
		$function = $js->makeClass("Function", $proto, function(array $args, JsObject $newTarget) use ($js) : JsObject{
			$js->throwError("EvalError", "Code generation from strings is not supported");
		}, function(mixed $thisValue, array $args) use ($js) : mixed{
			$js->throwError("EvalError", "Code generation from strings is not supported");
		}, 1);
		$js->constructors["Function"] = $function;
		$js->defineHidden($js->global, "Function", $function);
		$js->defineMethod($proto, "call", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			return $js->call($thisValue, $args[0] ?? null, array_slice($args, 1));
		});
		$js->defineMethod($proto, "apply", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			$list = $args[1] ?? null;
			$arguments = ($list === null || $list instanceof JsNull) ? [] : $this->arrayLike($list);
			return $js->call($thisValue, $args[0] ?? null, $arguments);
		});
		$js->defineMethod($proto, "bind", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			if(!$thisValue instanceof JsCallable){
				$js->throwError("TypeError", "Bind must be called on a function");
			}
			$target = $thisValue;
			$boundThis = $args[0] ?? null;
			$boundArgs = array_slice($args, 1);
			$construct = null;
			if($js->isConstructor($target)){
				$construct = function(array $callArgs, JsObject $newTarget) use ($js, $target, $boundArgs) : JsObject{
					return $js->construct($target, [...$boundArgs, ...$callArgs]);
				};
			}
			$bound = new NativeFunction($js->functionPrototype, "bound " . $target->name, max(0, $target->length - count($boundArgs)), function(mixed $ignored, array $callArgs) use ($js, $target, $boundThis, $boundArgs) : mixed{
				return $js->call($target, $boundThis, [...$boundArgs, ...$callArgs]);
			}, $construct);
			return $bound;
		});
		$js->defineMethod($proto, "toString", 0, function(mixed $thisValue, array $args) : mixed{
			if($thisValue instanceof JsFunction && $thisValue->isClass){
				return "class " . $thisValue->name . " { }";
			}
			$name = $thisValue instanceof JsCallable ? $thisValue->name : "";
			return "function " . $name . "() { [native code] }";
		});
	}

	/**
	 * @return list<mixed>
	 */
	public function arrayLike(mixed $value) : array{
		$js = $this->js;
		if($value instanceof JsArray){
			return $value->items;
		}
		if(!$value instanceof JsObject){
			$js->throwError("TypeError", "CreateListFromArrayLike called on non-object");
		}
		$length = $js->toIntegerOrInfinity($js->get($value, "length"));
		$items = [];
		for($i = 0; $i < $length && $i < 100_000; ++$i){
			$items[] = $js->get($value, (string) $i);
		}
		return $items;
	}

	private function installSymbol() : void{
		$js = $this->js;
		$proto = $js->symbolPrototype;
		$symbol = $js->makeClass("Symbol", $proto, null, function(mixed $thisValue, array $args) use ($js) : mixed{
			$description = $args[0] ?? null;
			return new JsSymbol($description === null ? null : $js->toString($description));
		});
		$js->constructors["Symbol"] = $symbol;
		$js->defineHidden($js->global, "Symbol", $symbol);
		foreach(["iterator" => $js->symIterator, "asyncIterator" => $js->symAsyncIterator, "hasInstance" => $js->symHasInstance, "toPrimitive" => $js->symToPrimitive, "toStringTag" => $js->symToStringTag] as $name => $value){
			$js->defineHidden($symbol, $name, $value);
			$symbol->locked[$name] = true;
		}
		$js->defineMethod($symbol, "for", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$key = $js->toString($args[0] ?? null);
			return $this->registry[$key] ??= new JsSymbol($key);
		});
		$js->defineMethod($symbol, "keyFor", 1, function(mixed $thisValue, array $args) : mixed{
			foreach($this->registry as $key => $registered){
				if($registered === ($args[0] ?? null)){
					return (string) $key;
				}
			}
			return null;
		});
		$js->defineMethod($proto, "toString", 0, function(mixed $thisValue, array $args) : mixed{
			$symbol = $thisValue instanceof JsObject ? $thisValue->host : $thisValue;
			return $symbol instanceof JsSymbol ? "Symbol(" . ($symbol->description ?? "") . ")" : "Symbol()";
		});
		$js->defineMethod($proto, "valueOf", 0, function(mixed $thisValue, array $args) : mixed{
			return $thisValue instanceof JsObject ? $thisValue->host : $thisValue;
		});
		$js->defineGetter($proto, "description", function(mixed $thisValue) : mixed{
			$symbol = $thisValue instanceof JsObject ? $thisValue->host : $thisValue;
			return $symbol instanceof JsSymbol ? $symbol->description : null;
		});
	}

	private function installErrors() : void{
		$js = $this->js;
		$errorConstructor = $this->makeErrorClass("Error", $js->errorPrototype, null);
		$js->defineMethod($errorConstructor, "captureStackTrace", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$target = $args[0] ?? null;
			if($target instanceof JsObject){
				$js->defineHidden($target, "stack", $js->toStringSafe($js->get($target, "name")) . ": " . $js->toStringSafe($js->get($target, "message")) . $js->stackTrace());
			}
			return null;
		});
		$js->defineMethod($js->errorPrototype, "toString", 0, function(mixed $thisValue, array $args) use ($js) : mixed{
			if(!$thisValue instanceof JsObject){
				$js->throwError("TypeError", "Error.prototype.toString called on non-object");
			}
			$name = $js->get($thisValue, "name");
			$message = $js->get($thisValue, "message");
			$name = $name === null ? "Error" : $js->toString($name);
			$message = $message === null ? "" : $js->toString($message);
			if($name === ""){
				return $message;
			}
			return $message === "" ? $name : $name . ": " . $message;
		});
		foreach(self::ERROR_TYPES as $type){
			$proto = new JsObject($js->errorPrototype);
			$proto->className = "Error";
			$js->errorPrototypes[$type] = $proto;
			$this->makeErrorClass($type, $proto, $errorConstructor);
		}
		$js->errorPrototypes["Error"] = $js->errorPrototype;
	}

	private function makeErrorClass(string $type, JsObject $proto, ?JsObject $parent) : NativeFunction{
		$js = $this->js;
		$construct = function(array $args, JsObject $newTarget) use ($js, $proto, $type) : JsObject{
			$error = new JsObject($js->prototypeFor($newTarget, $proto));
			$error->className = "Error";
			$messageIndex = 0;
			if($type === "AggregateError"){
				$js->defineHidden($error, "errors", $js->newArray($js->iterableToList($args[0] ?? null)));
				$messageIndex = 1;
			}
			$message = $args[$messageIndex] ?? null;
			if($message !== null){
				$js->defineHidden($error, "message", $js->toString($message));
			}
			$options = $args[$messageIndex + 1] ?? null;
			if($options instanceof JsObject && $js->hasProperty($options, "cause")){
				$js->defineHidden($error, "cause", $js->get($options, "cause"));
			}
			$name = $js->get($error, "name");
			$js->defineHidden($error, "stack", $js->toStringSafe($name) . ": " . ($message === null ? "" : $js->toStringSafe($message)) . $js->stackTrace());
			return $error;
		};
		$constructor = $js->makeClass($type, $proto, $construct, function(mixed $thisValue, array $args) use ($construct, &$constructor) : mixed{
			return $construct($args, $constructor);
		}, 1, $parent);
		$js->defineHidden($proto, "name", $type);
		$js->defineHidden($proto, "message", "");
		$js->constructors[$type] = $constructor;
		$js->defineHidden($js->global, $type, $constructor);
		return $constructor;
	}

	private function installBoolean() : void{
		$js = $this->js;
		$proto = $js->booleanPrototype;
		$proto->host = false;
		$boolean = $js->makeClass("Boolean", $proto, function(array $args, JsObject $newTarget) use ($js) : JsObject{
			$wrapper = new JsObject($js->prototypeFor($newTarget, $js->booleanPrototype));
			$wrapper->className = "Boolean";
			$wrapper->host = $js->toBoolean($args[0] ?? null);
			return $wrapper;
		}, function(mixed $thisValue, array $args) use ($js) : mixed{
			return $js->toBoolean($args[0] ?? null);
		}, 1);
		$js->constructors["Boolean"] = $boolean;
		$js->defineHidden($js->global, "Boolean", $boolean);
		$value = function(mixed $thisValue) use ($js) : bool{
			if(is_bool($thisValue)){
				return $thisValue;
			}
			if($thisValue instanceof JsObject && is_bool($thisValue->host)){
				return $thisValue->host;
			}
			$js->throwError("TypeError", "Boolean.prototype.valueOf requires that 'this' be a Boolean");
		};
		$js->defineMethod($proto, "toString", 0, function(mixed $thisValue, array $args) use ($value) : mixed{
			return $value($thisValue) ? "true" : "false";
		});
		$js->defineMethod($proto, "valueOf", 0, function(mixed $thisValue, array $args) use ($value) : mixed{
			return $value($thisValue);
		});
	}

	public function thisNumber(mixed $thisValue) : int|float{
		if(is_int($thisValue) || is_float($thisValue)){
			return $thisValue;
		}
		if($thisValue instanceof JsObject && (is_int($thisValue->host) || is_float($thisValue->host))){
			return $thisValue->host;
		}
		$this->js->throwError("TypeError", "Number.prototype method called on incompatible receiver");
	}

	private function installNumber() : void{
		$js = $this->js;
		$proto = $js->numberPrototype;
		$proto->host = 0;
		$number = $js->makeClass("Number", $proto, function(array $args, JsObject $newTarget) use ($js) : JsObject{
			$wrapper = new JsObject($js->prototypeFor($newTarget, $js->numberPrototype));
			$wrapper->className = "Number";
			$wrapper->host = count($args) === 0 ? 0 : $js->toNumber($args[0]);
			return $wrapper;
		}, function(mixed $thisValue, array $args) use ($js) : mixed{
			return count($args) === 0 ? 0 : $js->toNumber($args[0]);
		}, 1);
		$js->constructors["Number"] = $number;
		$js->defineHidden($js->global, "Number", $number);
		foreach([
			"MAX_SAFE_INTEGER" => 9007199254740991,
			"MIN_SAFE_INTEGER" => -9007199254740991,
			"EPSILON" => 2.220446049250313e-16,
			"MAX_VALUE" => 1.7976931348623157e308,
			"MIN_VALUE" => 5e-324,
			"POSITIVE_INFINITY" => INF,
			"NEGATIVE_INFINITY" => -INF,
			"NaN" => NAN
		] as $name => $value){
			$js->defineHidden($number, $name, $value);
			$number->locked[$name] = true;
		}
		$js->defineMethod($number, "isInteger", 1, function(mixed $thisValue, array $args) : mixed{
			$value = $args[0] ?? null;
			return is_int($value) || (is_float($value) && is_finite($value) && floor($value) === $value);
		});
		$js->defineMethod($number, "isSafeInteger", 1, function(mixed $thisValue, array $args) : mixed{
			$value = $args[0] ?? null;
			if(is_int($value)){
				return abs($value) <= 9007199254740991;
			}
			return is_float($value) && is_finite($value) && floor($value) === $value && abs($value) <= 9007199254740991;
		});
		$js->defineMethod($number, "isFinite", 1, function(mixed $thisValue, array $args) : mixed{
			$value = $args[0] ?? null;
			return is_int($value) || (is_float($value) && is_finite($value));
		});
		$js->defineMethod($number, "isNaN", 1, function(mixed $thisValue, array $args) : mixed{
			$value = $args[0] ?? null;
			return is_float($value) && is_nan($value);
		});

		$js->defineMethod($proto, "toString", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$value = $this->thisNumber($thisValue);
			$radix = ($args[0] ?? null) === null ? 10 : (int) $js->toIntegerOrInfinity($args[0]);
			if($radix < 2 || $radix > 36){
				$js->throwError("RangeError", "toString() radix must be between 2 and 36");
			}
			if($radix === 10 || (is_float($value) && (is_nan($value) || self::isInfinite($value)))){
				return Interpreter::numberToString($value);
			}
			return $this->toRadix($value, $radix);
		});
		$js->defineMethod($proto, "toFixed", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$value = $this->thisNumber($thisValue);
			$digits = (int) $js->toIntegerOrInfinity($args[0] ?? 0);
			if($digits < 0 || $digits > 100){
				$js->throwError("RangeError", "toFixed() digits argument must be between 0 and 100");
			}
			if(is_float($value) && (is_nan($value) || abs($value) >= 1e21 || self::isInfinite($value))){
				return Interpreter::numberToString($value);
			}
			$result = number_format((float) $value, $digits, ".", "");
			return preg_match('/^-0(\.0+)?$/', $result) === 1 ? substr($result, 1) : $result;
		});
		$js->defineMethod($proto, "toPrecision", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$value = (float) $this->thisNumber($thisValue);
			if(($args[0] ?? null) === null || is_nan($value) || self::isInfinite($value)){
				return Interpreter::numberToString($value);
			}
			$precision = (int) $js->toIntegerOrInfinity($args[0]);
			if($precision < 1 || $precision > 100){
				$js->throwError("RangeError", "toPrecision() argument must be between 1 and 100");
			}
			if($value == 0.0){
				return $precision === 1 ? "0" : "0." . str_repeat("0", $precision - 1);
			}
			$exponential = sprintf("%." . ($precision - 1) . "e", $value);
			$exponent = (int) substr($exponential, strpos($exponential, "e") + 1);
			if($exponent < -6 || $exponent >= $precision){
				[$mantissa, $power] = self::splitExponent($exponential);
				return $mantissa . "e" . ($power < 0 ? "-" : "+") . abs($power);
			}
			return number_format($value, max(0, $precision - 1 - $exponent), ".", "");
		});
		$js->defineMethod($proto, "toExponential", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$value = (float) $this->thisNumber($thisValue);
			if(is_nan($value) || self::isInfinite($value)){
				return Interpreter::numberToString($value);
			}
			$digits = ($args[0] ?? null) === null ? 6 : (int) $js->toIntegerOrInfinity($args[0]);
			[$mantissa, $power] = self::splitExponent(sprintf("%." . max(0, $digits) . "e", $value));
			return $mantissa . "e" . ($power < 0 ? "-" : "+") . abs($power);
		});
		$js->defineMethod($proto, "valueOf", 0, function(mixed $thisValue, array $args) : mixed{
			return $this->thisNumber($thisValue);
		});
		$js->defineMethod($proto, "toLocaleString", 0, function(mixed $thisValue, array $args) : mixed{
			$value = $this->thisNumber($thisValue);
			if(is_int($value) || (is_finite($value) && floor($value) === $value)){
				return number_format((float) $value, 0, ".", ",");
			}
			if(!is_finite($value)){
				return Interpreter::numberToString($value);
			}
			return rtrim(rtrim(number_format($value, 3, ".", ","), "0"), ".");
		});
	}

	private function toRadix(int|float $value, int $radix) : string{
		$negative = $value < 0;
		$value = abs($value);
		$integer = floor((float) $value);
		$fraction = (float) $value - $integer;
		$digits = "0123456789abcdefghijklmnopqrstuvwxyz";
		if($integer < 9.0e15){
			$result = base_convert((string) (int) $integer, 10, $radix);
		}else{
			$result = "";
			while($integer >= 1){
				$result = $digits[(int) fmod($integer, $radix)] . $result;
				$integer = floor($integer / $radix);
			}
		}
		if($fraction > 0){
			$result .= ".";
			for($i = 0; $i < 20 && $fraction > 0; ++$i){
				$fraction *= $radix;
				$digit = (int) floor($fraction);
				$result .= $digits[$digit];
				$fraction -= $digit;
			}
		}
		return ($negative ? "-" : "") . $result;
	}

	private function installMath() : void{
		$js = $this->js;
		$math = $js->newObject();
		$math->symbols[spl_object_id($js->symToStringTag)] = [$js->symToStringTag, "Math"];
		$js->defineHidden($js->global, "Math", $math);
		foreach(["PI" => M_PI, "E" => M_E, "LN2" => M_LN2, "LN10" => M_LN10, "LOG2E" => M_LOG2E, "LOG10E" => M_LOG10E, "SQRT2" => M_SQRT2, "SQRT1_2" => M_SQRT1_2] as $name => $value){
			$js->defineHidden($math, $name, $value);
			$math->locked[$name] = true;
		}
		$unary = [
			"abs" => fn(float $x) : float => abs($x),
			"floor" => fn(float $x) : float => floor($x),
			"ceil" => fn(float $x) : float => ceil($x),
			"round" => fn(float $x) : float => is_finite($x) ? floor($x + 0.5) : $x,
			"trunc" => fn(float $x) : float => $x < 0 ? ceil($x) : floor($x),
			"sign" => fn(float $x) : float => is_nan($x) ? NAN : ($x > 0 ? 1.0 : ($x < 0 ? -1.0 : $x)),
			"sqrt" => fn(float $x) : float => $x < 0 ? NAN : sqrt($x),
			"cbrt" => fn(float $x) : float => $x < 0 ? -pow(-$x, 1 / 3) : pow($x, 1 / 3),
			"sin" => fn(float $x) : float => sin($x),
			"cos" => fn(float $x) : float => cos($x),
			"tan" => fn(float $x) : float => tan($x),
			"asin" => fn(float $x) : float => asin($x),
			"acos" => fn(float $x) : float => acos($x),
			"atan" => fn(float $x) : float => atan($x),
			"sinh" => fn(float $x) : float => sinh($x),
			"cosh" => fn(float $x) : float => cosh($x),
			"tanh" => fn(float $x) : float => tanh($x),
			"asinh" => fn(float $x) : float => asinh($x),
			"acosh" => fn(float $x) : float => acosh($x),
			"atanh" => fn(float $x) : float => atanh($x),
			"exp" => fn(float $x) : float => exp($x),
			"expm1" => fn(float $x) : float => expm1($x),
			"log" => fn(float $x) : float => $x < 0 ? NAN : ($x == 0.0 ? -INF : log($x)),
			"log2" => fn(float $x) : float => $x < 0 ? NAN : ($x == 0.0 ? -INF : log($x, 2)),
			"log10" => fn(float $x) : float => $x < 0 ? NAN : ($x == 0.0 ? -INF : log10($x)),
			"log1p" => fn(float $x) : float => $x < -1 ? NAN : log1p($x),
			"fround" => fn(float $x) : float => is_finite($x) ? (float) unpack("g", pack("g", $x))[1] : $x
		];
		foreach($unary as $name => $function){
			$js->defineMethod($math, $name, 1, function(mixed $thisValue, array $args) use ($js, $function) : mixed{
				$value = $js->toNumber($args[0] ?? null);
				if(is_float($value) && is_nan($value)){
					return NAN;
				}
				return Interpreter::intOrFloat($function((float) $value));
			});
		}
		$js->defineMethod($math, "pow", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			return $js->binary("**", $args[0] ?? null, $args[1] ?? null);
		});
		$js->defineMethod($math, "atan2", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			return atan2((float) $js->toNumber($args[0] ?? null), (float) $js->toNumber($args[1] ?? null));
		});
		$js->defineMethod($math, "hypot", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			$sum = 0.0;
			foreach($args as $arg){
				$value = (float) $js->toNumber($arg);
				$sum += $value * $value;
			}
			return Interpreter::intOrFloat(sqrt($sum));
		});
		$js->defineMethod($math, "min", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			$result = INF;
			foreach($args as $arg){
				$value = $js->toNumber($arg);
				if(is_float($value) && is_nan($value)){
					return NAN;
				}
				if($value < $result){
					$result = $value;
				}
			}
			return $result;
		});
		$js->defineMethod($math, "max", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			$result = -INF;
			foreach($args as $arg){
				$value = $js->toNumber($arg);
				if(is_float($value) && is_nan($value)){
					return NAN;
				}
				if($value > $result){
					$result = $value;
				}
			}
			return $result;
		});
		$js->defineMethod($math, "random", 0, function(mixed $thisValue, array $args) : mixed{
			return mt_rand() / (mt_getrandmax() + 1);
		});
		$js->defineMethod($math, "imul", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			$a = $js->toInt32($args[0] ?? null);
			$b = $js->toInt32($args[1] ?? null);
			$low = (($a & 0xFFFF) * $b) & 0xFFFFFFFF;
			$high = ((($a >> 16) & 0xFFFF) * $b) & 0xFFFF;
			return Interpreter::wrapInt32($low + ($high << 16));
		});
		$js->defineMethod($math, "clz32", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$value = $js->toUint32($args[0] ?? null);
			if($value === 0){
				return 32;
			}
			$count = 0;
			while(($value & 0x80000000) === 0){
				$value <<= 1;
				$count++;
			}
			return $count;
		});
	}

	private function installJson() : void{
		$js = $this->js;
		$json = $js->newObject();
		$json->symbols[spl_object_id($js->symToStringTag)] = [$js->symToStringTag, "JSON"];
		$js->defineHidden($js->global, "JSON", $json);
		$js->defineMethod($json, "parse", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			$text = $js->toString($args[0] ?? null);
			try{
				$decoded = json_decode($text, false, 512, JSON_THROW_ON_ERROR);
			}catch(\JsonException $e){
				$js->throwError("SyntaxError", "Unexpected token in JSON: " . $e->getMessage());
			}
			$value = $this->fromJson($decoded);
			$reviver = $args[1] ?? null;
			if($reviver instanceof JsCallable){
				$root = $js->newObject();
				$root->props[""] = $value;
				return $this->revive($root, "", $reviver);
			}
			return $value;
		});
		$js->defineMethod($json, "stringify", 3, function(mixed $thisValue, array $args) use ($js) : mixed{
			return $this->stringify($args[0] ?? null, $args[1] ?? null, $args[2] ?? null);
		});
	}

	public function fromJson(mixed $value) : mixed{
		if($value === null){
			return JsNull::get();
		}
		if(is_array($value)){
			$items = [];
			foreach($value as $item){
				$items[] = $this->fromJson($item);
			}
			return $this->js->newArray($items);
		}
		if($value instanceof stdClass){
			$object = $this->js->newObject();
			foreach(get_object_vars($value) as $key => $item){
				$object->props[$key] = $this->fromJson($item);
			}
			return $object;
		}
		if(is_float($value)){
			return Interpreter::intOrFloat($value);
		}
		return $value;
	}

	private function revive(JsObject $holder, string $key, JsCallable $reviver) : mixed{
		$js = $this->js;
		$value = $js->get($holder, $key);
		if($value instanceof JsObject){
			foreach($js->ownKeys($value) as $childKey){
				$revived = $this->revive($value, $childKey, $reviver);
				if($revived === null){
					$js->deleteProperty($value, $childKey);
				}else{
					$js->createDataProperty($value, $childKey, $revived);
				}
			}
		}
		return $js->call($reviver, $holder, [$key, $value]);
	}

	public function stringify(mixed $value, mixed $replacer = null, mixed $space = null) : mixed{
		$js = $this->js;
		$indent = "";
		if(is_int($space) || is_float($space)){
			$indent = str_repeat(" ", (int) max(0, min(10, $space)));
		}elseif(is_string($space)){
			$indent = substr($space, 0, 10);
		}
		$allowed = null;
		$function = null;
		if($replacer instanceof JsCallable){
			$function = $replacer;
		}elseif($replacer instanceof JsArray){
			$allowed = [];
			foreach($replacer->items as $item){
				if(is_string($item) || is_int($item) || is_float($item)){
					$allowed[] = $js->toString($item);
				}
			}
		}
		$root = $js->newObject();
		$root->props[""] = $value;
		$stack = [];
		$result = $this->serialize($root, "", $value, $function, $allowed, $indent, "", $stack);
		return $result;
	}

	/**
	 * @param list<string>|null  $allowed
	 * @param list<JsObject>     $stack
	 */
	private function serialize(JsObject $holder, string $key, mixed $value, ?JsCallable $replacer, ?array $allowed, string $indent, string $currentIndent, array &$stack) : ?string{
		$js = $this->js;
		if($value instanceof JsObject){
			$toJson = $js->get($value, "toJSON");
			if($toJson instanceof JsCallable){
				$value = $js->call($toJson, $value, [$key]);
			}
		}
		if($replacer !== null){
			$value = $js->call($replacer, $holder, [$key, $value]);
		}
		if($value instanceof JsObject && !$value instanceof JsCallable && !$value instanceof JsArray && !$value instanceof JsPromise){
			$host = $value->host;
			if(($value->className === "Number" || $value->className === "String" || $value->className === "Boolean") && (is_int($host) || is_float($host) || is_string($host) || is_bool($host))){
				$value = $host;
			}
		}
		if($value instanceof JsNull){
			return "null";
		}
		if($value === true){
			return "true";
		}
		if($value === false){
			return "false";
		}
		if(is_string($value)){
			return self::quote($value);
		}
		if(is_int($value)){
			return (string) $value;
		}
		if(is_float($value)){
			return is_finite($value) ? Interpreter::numberToString($value) : "null";
		}
		if($value === null || $value instanceof JsCallable || $value instanceof JsSymbol){
			return null;
		}
		if(!$value instanceof JsObject){
			return null;
		}
		foreach($stack as $entry){
			if($entry === $value){
				$js->throwError("TypeError", "Converting circular structure to JSON");
			}
		}
		$stack[] = $value;
		$innerIndent = $currentIndent . $indent;
		$parts = [];
		if($value instanceof JsArray){
			foreach($value->items as $index => $item){
				$serialized = $this->serialize($value, (string) $index, $item, $replacer, $allowed, $indent, $innerIndent, $stack);
				$parts[] = $serialized ?? "null";
			}
			array_pop($stack);
			if(count($parts) === 0){
				return "[]";
			}
			if($indent === ""){
				return "[" . implode(",", $parts) . "]";
			}
			return "[\n" . $innerIndent . implode(",\n" . $innerIndent, $parts) . "\n" . $currentIndent . "]";
		}
		$keys = $allowed ?? $js->ownKeys($value);
		foreach($keys as $propertyKey){
			if($allowed !== null && !$js->hasOwnProperty($value, $propertyKey)){
				continue;
			}
			$serialized = $this->serialize($value, $propertyKey, $js->getFrom($value, $propertyKey, $value), $replacer, $allowed, $indent, $innerIndent, $stack);
			if($serialized !== null){
				$parts[] = self::quote($propertyKey) . ($indent === "" ? ":" : ": ") . $serialized;
			}
		}
		array_pop($stack);
		if(count($parts) === 0){
			return "{}";
		}
		if($indent === ""){
			return "{" . implode(",", $parts) . "}";
		}
		return "{\n" . $innerIndent . implode(",\n" . $innerIndent, $parts) . "\n" . $currentIndent . "}";
	}

	public static function quote(string $value) : string{
		$encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
		return $encoded === false ? "\"\"" : strtr($encoded, ["\\u2028" => "\u{2028}", "\\u2029" => "\u{2029}"]);
	}

	private function installReflect() : void{
		$js = $this->js;
		$reflect = $js->newObject();
		$js->defineHidden($js->global, "Reflect", $reflect);
		$object = $js->constructors["Object"];
		$js->defineMethod($reflect, "apply", 3, function(mixed $thisValue, array $args) use ($js) : mixed{
			return $js->call($args[0] ?? null, $args[1] ?? null, $this->arrayLike($args[2] ?? null));
		});
		$js->defineMethod($reflect, "construct", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			$newTarget = $args[2] ?? null;
			return $js->construct($args[0] ?? null, $this->arrayLike($args[1] ?? null), $newTarget instanceof JsObject ? $newTarget : null);
		});
		$js->defineMethod($reflect, "get", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			$target = $args[0] ?? null;
			if(!$target instanceof JsObject){
				$js->throwError("TypeError", "Reflect.get called on non-object");
			}
			$key = $js->toPropertyKey($args[1] ?? null);
			if($key instanceof JsSymbol){
				return $js->getSymbol($target, $key);
			}
			return $js->getFrom($target, $key, count($args) > 2 ? $args[2] : $target);
		});
		$js->defineMethod($reflect, "set", 3, function(mixed $thisValue, array $args) use ($js) : mixed{
			$target = $args[0] ?? null;
			if(!$target instanceof JsObject){
				$js->throwError("TypeError", "Reflect.set called on non-object");
			}
			try{
				$js->set($target, $js->toPropertyKey($args[1] ?? null), $args[2] ?? null);
			}catch(JsThrow){
				return false;
			}
			return true;
		});
		$js->defineMethod($reflect, "has", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			$target = $args[0] ?? null;
			if(!$target instanceof JsObject){
				$js->throwError("TypeError", "Reflect.has called on non-object");
			}
			return $js->hasProperty($target, $js->toPropertyKey($args[1] ?? null));
		});
		$js->defineMethod($reflect, "ownKeys", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$target = $args[0] ?? null;
			if(!$target instanceof JsObject){
				$js->throwError("TypeError", "Reflect.ownKeys called on non-object");
			}
			$keys = $js->ownKeys($target, true);
			foreach($js->ownSymbols($target) as $symbol){
				$keys[] = $symbol;
			}
			return $js->newArray($keys);
		});
		$js->defineMethod($reflect, "deleteProperty", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			$target = $args[0] ?? null;
			if(!$target instanceof JsObject){
				$js->throwError("TypeError", "Reflect.deleteProperty called on non-object");
			}
			try{
				return $js->deleteProperty($target, $js->toPropertyKey($args[1] ?? null));
			}catch(JsThrow){
				return false;
			}
		});
		foreach(["getPrototypeOf", "setPrototypeOf", "defineProperty", "getOwnPropertyDescriptor", "isExtensible", "preventExtensions"] as $name){
			$method = $object->props[$name] ?? null;
			if($method instanceof JsCallable){
				$js->defineHidden($reflect, $name, $method);
			}
		}
	}

	private function installGlobals() : void{
		$js = $this->js;
		$global = $js->global;
		$parseInt = $js->defineMethod($global, "parseInt", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			return $this->parseInt($js->toString($args[0] ?? null), ($args[1] ?? null) === null ? 0 : $js->toInt32($args[1]));
		});
		$parseFloat = $js->defineMethod($global, "parseFloat", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$text = trim($js->toString($args[0] ?? null));
			if(preg_match('/^[+-]?Infinity/', $text) === 1){
				return $text[0] === "-" ? -INF : INF;
			}
			if(preg_match('/^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?/', $text, $match) !== 1){
				return NAN;
			}
			return Interpreter::stringToNumber($match[0]);
		});
		$number = $js->constructors["Number"];
		$js->defineHidden($number, "parseInt", $parseInt);
		$js->defineHidden($number, "parseFloat", $parseFloat);
		$js->defineMethod($global, "isNaN", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$value = $js->toNumber($args[0] ?? null);
			return is_float($value) && is_nan($value);
		});
		$js->defineMethod($global, "isFinite", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$value = $js->toNumber($args[0] ?? null);
			return is_int($value) || is_finite($value);
		});
		$js->defineMethod($global, "encodeURIComponent", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			return strtr(rawurlencode($js->toString($args[0] ?? null)), ["%21" => "!", "%2A" => "*", "%27" => "'", "%28" => "(", "%29" => ")"]);
		});
		$js->defineMethod($global, "encodeURI", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$encoded = rawurlencode($js->toString($args[0] ?? null));
			return strtr($encoded, ["%21" => "!", "%2A" => "*", "%27" => "'", "%28" => "(", "%29" => ")", "%3B" => ";", "%2F" => "/", "%3F" => "?", "%3A" => ":", "%40" => "@", "%26" => "&", "%3D" => "=", "%2B" => "+", "%24" => "$", "%2C" => ",", "%23" => "#"]);
		});
		$decode = function(mixed $thisValue, array $args) use ($js) : mixed{
			return rawurldecode($js->toString($args[0] ?? null));
		};
		$js->defineMethod($global, "decodeURIComponent", 1, $decode);
		$js->defineMethod($global, "decodeURI", 1, $decode);
	}

	private function parseInt(string $text, int $radix) : int|float{
		$text = trim($text, " \t\n\r\v\f\xC2\xA0");
		$sign = 1;
		if($text !== "" && ($text[0] === "-" || $text[0] === "+")){
			$sign = $text[0] === "-" ? -1 : 1;
			$text = substr($text, 1);
		}
		if($radix === 0){
			$radix = 10;
			if(strlen($text) > 1 && $text[0] === "0" && ($text[1] === "x" || $text[1] === "X")){
				$radix = 16;
				$text = substr($text, 2);
			}
		}elseif($radix === 16 && strlen($text) > 1 && $text[0] === "0" && ($text[1] === "x" || $text[1] === "X")){
			$text = substr($text, 2);
		}
		if($radix < 2 || $radix > 36){
			return NAN;
		}
		$digits = substr("0123456789abcdefghijklmnopqrstuvwxyz", 0, $radix);
		$lower = strtolower($text);
		$length = 0;
		while($length < strlen($lower) && str_contains($digits, $lower[$length])){
			$length++;
		}
		if($length === 0){
			return NAN;
		}
		$result = 0.0;
		for($i = 0; $i < $length; ++$i){
			$result = $result * $radix + (float) strpos($digits, $lower[$i]);
		}
		return Interpreter::intOrFloat($sign * $result);
	}

	private function installConsole() : void{
		$js = $this->js;
		$console = $js->newObject();
		$js->defineHidden($js->global, "console", $console);
		foreach(["log" => "info", "info" => "info", "debug" => "debug", "warn" => "warning", "error" => "error", "trace" => "info"] as $name => $level){
			$js->defineMethod($console, $name, 0, function(mixed $thisValue, array $args) use ($js, $level) : mixed{
				$js->print($level, $this->formatArgs($args));
				return null;
			});
		}
		$js->defineMethod($console, "assert", 0, function(mixed $thisValue, array $args) use ($js) : mixed{
			if(!$js->toBoolean($args[0] ?? null)){
				$js->print("error", "Assertion failed" . (count($args) > 1 ? ": " . $this->formatArgs(array_slice($args, 1)) : ""));
			}
			return null;
		});
	}

	/**
	 * @param list<mixed> $args
	 */
	public function formatArgs(array $args) : string{
		$js = $this->js;
		$parts = [];
		if(isset($args[0]) && is_string($args[0]) && str_contains($args[0], "%")){
			$index = 1;
			$first = preg_replace_callback('/%[sdifoOjc%]/', function(array $match) use (&$index, $args, $js) : string{
				if($match[0] === "%%"){
					return "%";
				}
				if(!array_key_exists($index, $args)){
					return $match[0];
				}
				$value = $args[$index++];
				return match($match[0]){
					"%s" => is_string($value) ? $value : $this->inspect($value, 1),
					"%d", "%i" => Interpreter::numberToString(Interpreter::intOrFloat(floor((float) $js->toNumber($value)))),
					"%f" => Interpreter::numberToString($js->toNumber($value)),
					"%c" => "",
					default => $this->inspect($value, 2)
				};
			}, $args[0]) ?? $args[0];
			$parts[] = $first;
			$args = array_slice($args, $index);
		}
		foreach($args as $arg){
			$parts[] = is_string($arg) ? $arg : $this->inspect($arg, 2);
		}
		return implode(" ", $parts);
	}

	/**
	 * @param list<JsObject> $seen
	 */
	public function inspect(mixed $value, int $depth, array $seen = [], bool $nested = false) : string{
		$js = $this->js;
		if(is_string($value)){
			return $nested ? "'" . $value . "'" : $value;
		}
		if(!$value instanceof JsObject){
			if($value instanceof JsSymbol){
				return "Symbol(" . ($value->description ?? "") . ")";
			}
			return $js->toStringSafe($value);
		}
		if(in_array($value, $seen, true)){
			return "[Circular]";
		}
		if($value instanceof JsCallable){
			if($value instanceof JsFunction && $value->isClass){
				return "[class " . ($value->name === "" ? "(anonymous)" : $value->name) . "]";
			}
			return "[Function: " . ($value->name === "" ? "(anonymous)" : $value->name) . "]";
		}
		if($value->className === "Error"){
			return $js->describeError($value);
		}
		if($value instanceof JsPromise){
			return "Promise { " . match($value->state){
				JsPromise::PENDING => "<pending>",
				JsPromise::FULFILLED => $this->inspect($value->value, $depth - 1, $seen, true),
				default => "<rejected> " . $this->inspect($value->value, $depth - 1, $seen, true)
			} . " }";
		}
		$seen[] = $value;
		if($value instanceof JsArray){
			if($depth < 0){
				return "[Array]";
			}
			$items = [];
			foreach(array_slice($value->items, 0, 100) as $item){
				$items[] = $this->inspect($item, $depth - 1, $seen, true);
			}
			if(count($value->items) > 100){
				$items[] = "... " . (count($value->items) - 100) . " more items";
			}
			return count($items) === 0 ? "[]" : "[ " . implode(", ", $items) . " ]";
		}
		$prefix = "";
		$constructor = $js->describeObject($value);
		if($constructor !== "#<Object>" && $constructor !== "#<Module>"){
			$prefix = substr($constructor, 2, -1) . " ";
		}
		if(is_string($value->host) || is_int($value->host) || is_float($value->host) || is_bool($value->host)){
			return "[" . $value->className . ": " . $this->inspect($value->host, 0, $seen, true) . "]";
		}
		if($depth < 0){
			return "[" . ($prefix === "" ? "Object" : trim($prefix)) . "]";
		}
		$entries = [];
		foreach($js->ownKeys($value) as $key){
			$propertyValue = isset($value->accessors[$key]) ? "[Getter/Setter]" : $this->inspect($js->getFrom($value, $key, $value), $depth - 1, $seen, true);
			$entries[] = (preg_match('/^[A-Za-z_$][\w$]*$/', $key) === 1 ? $key : self::quote($key)) . ": " . $propertyValue;
		}
		if(count($entries) === 0){
			return $prefix . "{}";
		}
		return $prefix . "{ " . implode(", ", $entries) . " }";
	}

	private static function isInfinite(float|int $value) : bool{
		return is_float($value) && is_infinite($value);
	}

	/**
	 * @return array{0: string, 1: int}
	 */
	private static function splitExponent(string $exponential) : array{
		$position = (int) strpos($exponential, "e");
		return [substr($exponential, 0, $position), (int) substr($exponential, $position + 1)];
	}
}
