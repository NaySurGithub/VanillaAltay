<?php

declare(strict_types=1);

namespace behaviorpack\script\js;

use behaviorpack\script\js\builtin\ArrayBuiltins;
use behaviorpack\script\js\builtin\CollectionBuiltins;
use behaviorpack\script\js\builtin\CoreBuiltins;
use behaviorpack\script\js\builtin\DateBuiltins;
use behaviorpack\script\js\builtin\PromiseBuiltins;
use behaviorpack\script\js\builtin\StringBuiltins;
use Closure;
use Error;
use Fiber;
use Generator;
use RuntimeException;
use SplQueue;
use stdClass;
use Throwable;
use function array_key_exists;
use function array_merge;
use function array_pop;
use function array_slice;
use function bindec;
use function count;
use function ctype_digit;
use function ctype_xdigit;
use function fdiv;
use function floor;
use function fmod;
use function hrtime;
use function implode;
use function hexdec;
use function in_array;
use function intdiv;
use function is_bool;
use function is_finite;
use function is_float;
use function is_infinite;
use function is_int;
use function is_nan;
use function is_string;
use function ltrim;
use function mb_str_split;
use function mb_strlen;
use function mb_substr;
use function octdec;
use function pow;
use function preg_match;
use function rtrim;
use function sort;
use function spl_object_id;
use function sprintf;
use function str_repeat;
use function str_replace;
use function str_starts_with;
use function strcmp;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;
use const INF;
use const NAN;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * A tree-walking JavaScript interpreter. It evaluates the syntax trees built
 * by Parser, runs async functions and generators in fibers, keeps a
 * microtask queue for promises, and aborts code that runs past the watchdog
 * deadline with ScriptTimeoutException.
 */
final class Interpreter{

	public const MAX_DEPTH = 300;

	private const STEP_CHECK = 1024;

	public JsObject $objectPrototype;
	public NativeFunction $functionPrototype;
	public JsArray $arrayPrototype;
	public JsObject $stringPrototype;
	public JsObject $numberPrototype;
	public JsObject $booleanPrototype;
	public JsObject $symbolPrototype;
	public JsObject $errorPrototype;
	public JsObject $iteratorPrototype;
	public JsObject $generatorPrototype;
	public JsObject $promisePrototype;
	public JsObject $global;

	/** @var array<string, JsObject> */
	public array $errorPrototypes = [];

	/** @var array<string, JsCallable> */
	public array $constructors = [];

	public JsSymbol $symIterator;
	public JsSymbol $symAsyncIterator;
	public JsSymbol $symHasInstance;
	public JsSymbol $symToPrimitive;
	public JsSymbol $symToStringTag;

	public StringBuiltins $strings;

	public ?string $context = null;
	public string $file = "";
	public int $line = 0;
	public int $deadline = PHP_INT_MAX;

	/** @var (Closure(string, string, ?string) : void)|null */
	public ?Closure $printer = null;

	/** @var (Closure(string, string) : JsPromise)|null */
	public ?Closure $dynamicImport = null;

	/** @var list<array{0: string, 1: string, 2: int}> */
	private array $frames = [];
	private int $depth = 0;
	private int $steps = 0;

	/** @var SplQueue<array{0: Closure, 1: ?string}> */
	private SplQueue $jobs;

	/** @var array<int, JsPromise> */
	private array $unhandled = [];

	/** @var array<int, true> */
	private array $asyncFibers = [];

	private static ?stdClass $short = null;

	public function __construct(){
		self::$short ??= new stdClass();
		$this->jobs = new SplQueue();
		$this->objectPrototype = new JsObject(null);
		$this->functionPrototype = new NativeFunction($this->objectPrototype, "", 0, function(mixed $thisValue, array $args) : mixed{
			return null;
		});
		$this->arrayPrototype = new JsArray($this->objectPrototype);
		$this->stringPrototype = new JsObject($this->objectPrototype);
		$this->stringPrototype->className = "String";
		$this->numberPrototype = new JsObject($this->objectPrototype);
		$this->numberPrototype->className = "Number";
		$this->booleanPrototype = new JsObject($this->objectPrototype);
		$this->booleanPrototype->className = "Boolean";
		$this->symbolPrototype = new JsObject($this->objectPrototype);
		$this->symbolPrototype->className = "Symbol";
		$this->errorPrototype = new JsObject($this->objectPrototype);
		$this->errorPrototype->className = "Error";
		$this->iteratorPrototype = new JsObject($this->objectPrototype);
		$this->generatorPrototype = new JsObject($this->iteratorPrototype);
		$this->generatorPrototype->className = "Generator";
		$this->promisePrototype = new JsObject($this->objectPrototype);
		$this->global = new JsObject($this->objectPrototype);
		$this->global->className = "global";
		$this->symIterator = new JsSymbol("Symbol.iterator");
		$this->symAsyncIterator = new JsSymbol("Symbol.asyncIterator");
		$this->symHasInstance = new JsSymbol("Symbol.hasInstance");
		$this->symToPrimitive = new JsSymbol("Symbol.toPrimitive");
		$this->symToStringTag = new JsSymbol("Symbol.toStringTag");

		(new CoreBuiltins($this))->install();
		(new ArrayBuiltins($this))->install();
		$this->strings = new StringBuiltins($this);
		$this->strings->install();
		(new CollectionBuiltins($this))->install();
		(new PromiseBuiltins($this))->install();
		(new DateBuiltins($this))->install();
	}

	public static function short() : stdClass{
		return self::$short ??= new stdClass();
	}

	/**
	 * Starts the watchdog for a new entry from the server.
	 */
	public function startWatchdog(int $milliseconds) : void{
		$this->deadline = hrtime(true) + $milliseconds * 1_000_000;
		$this->steps = 0;
	}

	public function stopWatchdog() : void{
		$this->deadline = PHP_INT_MAX;
	}

	public function tick() : void{
		if(++$this->steps >= self::STEP_CHECK){
			$this->steps = 0;
			if(hrtime(true) > $this->deadline){
				throw new ScriptTimeoutException("Script watchdog deadline exceeded");
			}
		}
	}

	public function resetStack() : void{
		$this->frames = [];
		$this->depth = 0;
	}

	/**
	 * @return array{0: list<array{0: string, 1: string, 2: int}>, 1: int, 2: string, 3: int, 4: ?string}
	 */
	private function saveState() : array{
		return [$this->frames, $this->depth, $this->file, $this->line, $this->context];
	}

	/**
	 * @param array{0: list<array{0: string, 1: string, 2: int}>, 1: int, 2: string, 3: int, 4: ?string} $state
	 */
	private function restoreState(array $state) : void{
		[$this->frames, $this->depth, $this->file, $this->line, $this->context] = $state;
	}

	public function newObject(?JsObject $proto = null) : JsObject{
		return new JsObject($proto ?? $this->objectPrototype);
	}

	/**
	 * @param list<mixed> $items
	 */
	public function newArray(array $items = []) : JsArray{
		return new JsArray($this->arrayPrototype, $items);
	}

	/**
	 * @param Closure(mixed, list<mixed>) : mixed             $call
	 * @param (Closure(list<mixed>, JsObject) : JsObject)|null $construct
	 */
	public function native(string $name, int $length, Closure $call, ?Closure $construct = null) : NativeFunction{
		return new NativeFunction($this->functionPrototype, $name, $length, $call, $construct);
	}

	public function defineHidden(JsObject $object, string|JsSymbol $key, mixed $value) : void{
		if($key instanceof JsSymbol){
			$object->symbols[spl_object_id($key)] = [$key, $value];
			return;
		}
		$object->props[$key] = $value;
		$object->hidden[$key] = true;
	}

	/**
	 * @param Closure(mixed, list<mixed>) : mixed $call
	 */
	public function defineMethod(JsObject $target, string|JsSymbol $key, int $length, Closure $call) : NativeFunction{
		$name = $key instanceof JsSymbol ? "[" . $key->description . "]" : $key;
		$function = $this->native($name, $length, $call);
		$this->defineHidden($target, $key, $function);
		return $function;
	}

	/**
	 * @param Closure(mixed) : mixed              $get
	 * @param (Closure(mixed, mixed) : void)|null $set
	 */
	public function defineGetter(JsObject $target, string $name, Closure $get, ?Closure $set = null, bool $enumerable = false) : void{
		$getter = $this->native("get " . $name, 0, function(mixed $thisValue, array $args) use ($get) : mixed{
			return $get($thisValue);
		});
		$setter = null;
		if($set !== null){
			$setter = $this->native("set " . $name, 1, function(mixed $thisValue, array $args) use ($set) : mixed{
				$set($thisValue, $args[0] ?? null);
				return null;
			});
		}
		unset($target->props[$name]);
		$target->accessors[$name] = [$getter, $setter];
		if($enumerable){
			unset($target->hidden[$name]);
		}else{
			$target->hidden[$name] = true;
		}
	}

	/**
	 * Creates a native class: a constructor whose "prototype" is $prototype.
	 *
	 * @param (Closure(list<mixed>, JsObject) : JsObject)|null $construct
	 * @param (Closure(mixed, list<mixed>) : mixed)|null        $call
	 */
	public function makeClass(string $name, JsObject $prototype, ?Closure $construct, ?Closure $call = null, int $length = 0, ?JsObject $parent = null) : NativeFunction{
		$call ??= function(mixed $thisValue, array $args) use ($name) : mixed{
			$this->throwError("TypeError", "Class constructor " . $name . " cannot be invoked without 'new'");
		};
		$constructor = new NativeFunction($parent ?? $this->functionPrototype, $name, $length, $call, $construct);
		$this->defineHidden($constructor, "prototype", $prototype);
		$constructor->locked["prototype"] = true;
		$this->defineHidden($prototype, "constructor", $constructor);
		return $constructor;
	}

	public function iterResult(mixed $value, bool $done) : JsObject{
		$result = new JsObject($this->objectPrototype);
		$result->props["value"] = $value;
		$result->props["done"] = $done;
		return $result;
	}

	/**
	 * Returns the prototype to use for an object built by a native
	 * constructor called through new.target.
	 */
	public function prototypeFor(?JsObject $newTarget, JsObject $fallback) : JsObject{
		if($newTarget === null){
			return $fallback;
		}
		$proto = $this->get($newTarget, "prototype");
		return $proto instanceof JsObject ? $proto : $fallback;
	}

	public function makeError(string $type, string $message) : JsObject{
		$error = new JsObject($this->errorPrototypes[$type] ?? $this->errorPrototype);
		$error->className = "Error";
		$this->defineHidden($error, "message", $message);
		$this->defineHidden($error, "stack", $type . ": " . $message . $this->stackTrace());
		return $error;
	}

	public function throwError(string $type, string $message) : never{
		throw new JsThrow($this->makeError($type, $message), $type . ": " . $message);
	}

	public function stackTrace() : string{
		$lines = "";
		$line = $this->line;
		for($i = count($this->frames) - 1; $i >= 0 && $i >= count($this->frames) - 10; --$i){
			[$name, $file, $callerLine] = $this->frames[$i];
			$lines .= "\n    at " . ($name === "" ? "<anonymous>" : $name) . " (" . $file . ":" . $line . ")";
			$line = $callerLine;
		}
		$lines .= "\n    at " . $this->file . ":" . $line;
		return $lines;
	}

	/**
	 * Describes a thrown value for the console, with its stack when it is
	 * an error.
	 */
	public function describeError(mixed $value) : string{
		if($value instanceof JsObject){
			try{
				$stack = $this->getFrom($value, "stack", $value);
				if(is_string($stack) && $stack !== ""){
					$name = $this->getFrom($value, "name", $value);
					$message = $this->getFrom($value, "message", $value);
					$head = $this->toStringSafe($name) . ": " . $this->toStringSafe($message);
					$position = strpos($stack, "\n");
					return $head . ($position === false ? "" : substr($stack, $position));
				}
			}catch(JsThrow){
				return "[object]";
			}
		}
		return $this->toStringSafe($value);
	}

	public function toStringSafe(mixed $value) : string{
		try{
			return $this->toString($value);
		}catch(JsThrow){
			return "[object]";
		}
	}

	public function print(string $level, string $message) : void{
		if($this->printer !== null){
			($this->printer)($level, $message, $this->context);
		}
	}

	public function reportError(mixed $value) : void{
		$this->print("error", $this->describeError($value));
	}

	public function typeOf(mixed $value) : string{
		if($value === null){
			return "undefined";
		}
		if(is_bool($value)){
			return "boolean";
		}
		if(is_int($value) || is_float($value)){
			return "number";
		}
		if(is_string($value)){
			return "string";
		}
		if($value instanceof JsCallable){
			return "function";
		}
		if($value instanceof JsSymbol){
			return "symbol";
		}
		return "object";
	}

	public function toBoolean(mixed $value) : bool{
		if($value === null || $value instanceof JsNull){
			return false;
		}
		if(is_bool($value)){
			return $value;
		}
		if(is_int($value)){
			return $value !== 0;
		}
		if(is_float($value)){
			return !($value == 0.0 || is_nan($value));
		}
		if(is_string($value)){
			return $value !== "";
		}
		return true;
	}

	public function toNumber(mixed $value) : int|float{
		if(is_int($value) || is_float($value)){
			return $value;
		}
		if($value === null){
			return NAN;
		}
		if($value instanceof JsNull){
			return 0;
		}
		if(is_bool($value)){
			return $value ? 1 : 0;
		}
		if(is_string($value)){
			return self::stringToNumber($value);
		}
		if($value instanceof JsSymbol){
			$this->throwError("TypeError", "Cannot convert a Symbol value to a number");
		}
		return $this->toNumber($this->toPrimitive($value, "number"));
	}

	public static function stringToNumber(string $value) : int|float{
		$text = trim($value, " \t\n\r\v\f\xC2\xA0");
		if($text === ""){
			return 0;
		}
		$lower = strtolower($text);
		if(str_starts_with($lower, "0x") && strlen($text) > 2 && ctype_xdigit(substr($text, 2))){
			return self::intOrFloat((float) \hexdec(substr($text, 2)));
		}
		if(str_starts_with($lower, "0b") && \preg_match('/^[01]+$/', substr($text, 2)) === 1){
			return self::intOrFloat((float) \bindec(substr($text, 2)));
		}
		if(str_starts_with($lower, "0o") && \preg_match('/^[0-7]+$/', substr($text, 2)) === 1){
			return self::intOrFloat((float) \octdec(substr($text, 2)));
		}
		if($text === "Infinity" || $text === "+Infinity"){
			return INF;
		}
		if($text === "-Infinity"){
			return -INF;
		}
		if(\preg_match('/^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$/', $text) !== 1){
			return NAN;
		}
		if(\preg_match('/^[+-]?\d+$/', $text) === 1 && strlen(ltrim($text, "+-")) < 16){
			return (int) $text;
		}
		return (float) $text;
	}

	public static function intOrFloat(float $value) : int|float{
		if($value === floor($value) && $value >= -9007199254740991.0 && $value <= 9007199254740991.0 && !($value == 0.0 && fdiv(1.0, $value) < 0)){
			return (int) $value;
		}
		return $value;
	}

	public function toNumeric(mixed $value) : int|float{
		return $this->toNumber($value);
	}

	public function toIntegerOrInfinity(mixed $value) : int|float{
		$number = $this->toNumber($value);
		if(is_int($number)){
			return $number;
		}
		if(is_nan($number)){
			return 0;
		}
		if(is_infinite($number)){
			return $number;
		}
		$truncated = $number < 0 ? -floor(-$number) : floor($number);
		return self::intOrFloat($truncated);
	}

	public function toInt32(mixed $value) : int{
		$number = is_int($value) ? $value : $this->toNumber($value);
		if(is_int($number) && $number >= -2147483648 && $number <= 2147483647){
			return $number;
		}
		if(is_float($number) && (is_nan($number) || is_infinite($number))){
			return 0;
		}
		$number = (float) $number;
		$number = $number < 0 ? -floor(-$number) : floor($number);
		$number = fmod($number, 4294967296.0);
		if($number < 0){
			$number += 4294967296.0;
		}
		$int = (int) $number;
		return $int >= 2147483648 ? $int - 4294967296 : $int;
	}

	public function toUint32(mixed $value) : int{
		$int = $this->toInt32($value);
		return $int < 0 ? $int + 4294967296 : $int;
	}

	public static function wrapInt32(int $value) : int{
		$value &= 0xFFFFFFFF;
		return $value >= 2147483648 ? $value - 4294967296 : $value;
	}

	public function toString(mixed $value) : string{
		if(is_string($value)){
			return $value;
		}
		if(is_int($value)){
			return (string) $value;
		}
		if(is_float($value)){
			return self::numberToString($value);
		}
		if($value === null){
			return "undefined";
		}
		if($value instanceof JsNull){
			return "null";
		}
		if(is_bool($value)){
			return $value ? "true" : "false";
		}
		if($value instanceof JsSymbol){
			$this->throwError("TypeError", "Cannot convert a Symbol value to a string");
		}
		return $this->toString($this->toPrimitive($value, "string"));
	}

	public static function numberToString(int|float $number) : string{
		if(is_int($number)){
			return (string) $number;
		}
		if(is_nan($number)){
			return "NaN";
		}
		if($number == 0.0){
			return "0";
		}
		if(is_infinite($number)){
			return $number > 0 ? "Infinity" : "-Infinity";
		}
		if($number < 0){
			return "-" . self::numberToString(-$number);
		}
		$repr = "";
		for($precision = 1; $precision <= 17; ++$precision){
			$repr = sprintf("%." . ($precision - 1) . "e", $number);
			if((float) $repr === $number){
				break;
			}
		}
		$ePosition = strpos($repr, "e");
		$mantissa = substr($repr, 0, $ePosition);
		$exponent = (int) substr($repr, $ePosition + 1);
		$digits = rtrim(str_replace(".", "", $mantissa), "0");
		if($digits === ""){
			$digits = "0";
		}
		$k = strlen($digits);
		$n = $exponent + 1;
		if($k <= $n && $n <= 21){
			return $digits . str_repeat("0", $n - $k);
		}
		if(0 < $n && $n <= 21){
			return substr($digits, 0, $n) . "." . substr($digits, $n);
		}
		if(-6 < $n && $n <= 0){
			return "0." . str_repeat("0", -$n) . $digits;
		}
		$e = $n - 1;
		$sign = $e < 0 ? "-" : "+";
		$e = $e < 0 ? -$e : $e;
		if($k === 1){
			return $digits . "e" . $sign . $e;
		}
		return $digits[0] . "." . substr($digits, 1) . "e" . $sign . $e;
	}

	public function toPrimitive(mixed $value, string $hint = "default") : mixed{
		if(!$value instanceof JsObject){
			return $value;
		}
		$exotic = $this->getSymbol($value, $this->symToPrimitive);
		if($exotic instanceof JsCallable){
			$result = $this->call($exotic, $value, [$hint]);
			if(!$result instanceof JsObject){
				return $result;
			}
			$this->throwError("TypeError", "Cannot convert object to primitive value");
		}
		$order = $hint === "string" ? ["toString", "valueOf"] : ["valueOf", "toString"];
		foreach($order as $method){
			$function = $this->get($value, $method);
			if($function instanceof JsCallable){
				$result = $this->call($function, $value, []);
				if(!$result instanceof JsObject){
					return $result;
				}
			}
		}
		$this->throwError("TypeError", "Cannot convert object to primitive value");
	}

	public function toObject(mixed $value) : JsObject{
		if($value instanceof JsObject){
			return $value;
		}
		if($value === null || $value instanceof JsNull){
			$this->throwError("TypeError", "Cannot convert undefined or null to object");
		}
		$wrapper = new JsObject(match(true){
			is_string($value) => $this->stringPrototype,
			is_bool($value) => $this->booleanPrototype,
			$value instanceof JsSymbol => $this->symbolPrototype,
			default => $this->numberPrototype
		});
		$wrapper->className = match(true){
			is_string($value) => "String",
			is_bool($value) => "Boolean",
			$value instanceof JsSymbol => "Symbol",
			default => "Number"
		};
		$wrapper->host = $value;
		return $wrapper;
	}

	public function toPropertyKey(mixed $value) : string|JsSymbol{
		if(is_string($value)){
			return $value;
		}
		if(is_int($value)){
			return (string) $value;
		}
		if($value instanceof JsSymbol){
			return $value;
		}
		$primitive = $this->toPrimitive($value, "string");
		if($primitive instanceof JsSymbol){
			return $primitive;
		}
		return $this->toString($primitive);
	}

	public static function isIndex(string|int $key) : bool{
		if(is_int($key)){
			return $key >= 0;
		}
		return $key !== "" && ctype_digit($key) && ($key === "0" || $key[0] !== "0") && strlen($key) < 16;
	}

	public function strictEquals(mixed $a, mixed $b) : bool{
		if((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))){
			return $a == $b;
		}
		return $a === $b;
	}

	public function sameValueZero(mixed $a, mixed $b) : bool{
		if(is_float($a) && is_float($b) && is_nan($a) && is_nan($b)){
			return true;
		}
		if((is_int($a) || is_float($a)) && is_float($b) && is_nan($b)){
			return is_float($a) && is_nan($a);
		}
		return $this->strictEquals($a, $b);
	}

	public function looseEquals(mixed $a, mixed $b) : bool{
		$aNullish = $a === null || $a instanceof JsNull;
		$bNullish = $b === null || $b instanceof JsNull;
		if($aNullish || $bNullish){
			return $aNullish && $bNullish;
		}
		$aNumber = is_int($a) || is_float($a);
		$bNumber = is_int($b) || is_float($b);
		if(($aNumber && $bNumber) || (is_string($a) && is_string($b)) || (is_bool($a) && is_bool($b))){
			return $this->strictEquals($a, $b);
		}
		if($a instanceof JsObject && $b instanceof JsObject){
			return $a === $b;
		}
		if($a instanceof JsSymbol || $b instanceof JsSymbol){
			return $a === $b;
		}
		if(is_bool($a)){
			return $this->looseEquals($a ? 1 : 0, $b);
		}
		if(is_bool($b)){
			return $this->looseEquals($a, $b ? 1 : 0);
		}
		if($aNumber && is_string($b)){
			return $this->strictEquals($a, self::stringToNumber($b));
		}
		if(is_string($a) && $bNumber){
			return $this->strictEquals(self::stringToNumber($a), $b);
		}
		if($a instanceof JsObject){
			return $this->looseEquals($this->toPrimitive($a), $b);
		}
		if($b instanceof JsObject){
			return $this->looseEquals($a, $this->toPrimitive($b));
		}
		return false;
	}

	public function get(mixed $base, string|int|JsSymbol $key) : mixed{
		if($base instanceof JsObject){
			if($key instanceof JsSymbol){
				return $this->getSymbol($base, $key);
			}
			return $this->getFrom($base, $key, $base);
		}
		if(is_string($base)){
			if($key === "length"){
				return mb_strlen($base, "UTF-8");
			}
			if(!$key instanceof JsSymbol && self::isIndex($key)){
				$char = mb_substr($base, (int) $key, 1, "UTF-8");
				return $char === "" ? null : $char;
			}
			return $key instanceof JsSymbol ? $this->getSymbol($this->stringPrototype, $key, $base) : $this->getFrom($this->stringPrototype, $key, $base);
		}
		if($base === null || $base instanceof JsNull){
			$this->throwError("TypeError", "Cannot read properties of " . ($base === null ? "undefined" : "null") . " (reading '" . ($key instanceof JsSymbol ? "Symbol(" . $key->description . ")" : $key) . "')");
		}
		$proto = match(true){
			is_bool($base) => $this->booleanPrototype,
			$base instanceof JsSymbol => $this->symbolPrototype,
			default => $this->numberPrototype
		};
		return $key instanceof JsSymbol ? $this->getSymbol($proto, $key, $base) : $this->getFrom($proto, $key, $base);
	}

	public function getSymbol(JsObject $object, JsSymbol $symbol, mixed $receiver = null) : mixed{
		$id = spl_object_id($symbol);
		for($current = $object; $current !== null; $current = $current->proto){
			if(isset($current->symbols[$id])){
				return $current->symbols[$id][1];
			}
		}
		return null;
	}

	public function getFrom(JsObject $object, string|int $key, mixed $receiver) : mixed{
		$current = $object;
		do{
			if($current instanceof JsArray){
				if($key === "length"){
					return count($current->items);
				}
				if(is_int($key) || self::isIndex($key)){
					$index = (int) $key;
					if($index < count($current->items)){
						return $current->items[$index];
					}
				}
			}
			if(array_key_exists($key, $current->props)){
				return $current->props[$key];
			}
			if(isset($current->accessors[$key])){
				$getter = $current->accessors[$key][0];
				return $getter === null ? null : $this->call($getter, $receiver, []);
			}
			if($current instanceof JsCallable){
				if($key === "name"){
					return $current->name;
				}
				if($key === "length"){
					return $current->length;
				}
				if($key === "prototype" && $current instanceof JsFunction && $current->needsPrototype){
					return $this->createPrototypeProperty($current);
				}
			}
			$current = $current->proto;
		}while($current !== null);
		for($current = $object; $current !== null; $current = $current->proto){
			if($current->miss !== null){
				return ($current->miss)($receiver instanceof JsObject ? $receiver : $object, (string) $key);
			}
		}
		return null;
	}

	private function createPrototypeProperty(JsFunction $function) : JsObject{
		$function->needsPrototype = false;
		$generator = $function->node !== null && $function->node["generator"];
		$prototype = new JsObject($generator ? $this->generatorPrototype : $this->objectPrototype);
		if(!$generator){
			$this->defineHidden($prototype, "constructor", $function);
		}
		$this->defineHidden($function, "prototype", $prototype);
		return $prototype;
	}

	public function set(mixed $base, string|int|JsSymbol $key, mixed $value) : void{
		if(!$base instanceof JsObject){
			if($base === null || $base instanceof JsNull){
				$this->throwError("TypeError", "Cannot set properties of " . ($base === null ? "undefined" : "null") . " (setting '" . ($key instanceof JsSymbol ? "Symbol()" : $key) . "')");
			}
			return;
		}
		if($key instanceof JsSymbol){
			if(!$base->extensible && !isset($base->symbols[spl_object_id($key)])){
				$this->throwError("TypeError", "Cannot add property, object is not extensible");
			}
			$base->symbols[spl_object_id($key)] = [$key, $value];
			return;
		}
		$this->setOn($base, $key, $value, $base);
	}

	public function setOn(JsObject $object, string|int $key, mixed $value, mixed $receiver) : void{
		if($object instanceof JsArray){
			if(is_int($key) || self::isIndex($key)){
				if($object->frozen){
					$this->throwError("TypeError", "Cannot assign to read only property '" . $key . "' of object");
				}
				$index = (int) $key;
				$count = count($object->items);
				if($index < $count){
					$object->items[$index] = $value;
				}elseif($index === $count){
					$object->items[] = $value;
				}else{
					if($index > 50_000_000){
						$this->throwError("RangeError", "Invalid array length");
					}
					for($i = $count; $i < $index; ++$i){
						$object->items[] = null;
					}
					$object->items[] = $value;
				}
				return;
			}
			if($key === "length"){
				if($object->frozen){
					$this->throwError("TypeError", "Cannot assign to read only property 'length' of object");
				}
				$length = $this->toNumber($value);
				if(!is_int($length) || $length < 0 || $length > 50_000_000){
					$this->throwError("RangeError", "Invalid array length");
				}
				$count = count($object->items);
				if($length < $count){
					$object->items = array_slice($object->items, 0, $length);
				}else{
					for($i = $count; $i < $length; ++$i){
						$object->items[] = null;
					}
				}
				return;
			}
		}
		for($current = $object; $current !== null; $current = $current->proto){
			if(isset($current->accessors[$key])){
				$setter = $current->accessors[$key][1];
				if($setter === null){
					$this->throwError("TypeError", "Cannot set property " . $key . " of " . $this->describeObject($object) . " which has only a getter");
				}
				$this->call($setter, $receiver, [$value]);
				return;
			}
			if(array_key_exists($key, $current->props)){
				if(isset($current->locked[$key])){
					$this->throwError("TypeError", "Cannot assign to read only property '" . $key . "' of " . $this->describeObject($object));
				}
				if($current === $object){
					$object->props[$key] = $value;
					return;
				}
				break;
			}
		}
		if(!$object->extensible){
			$this->throwError("TypeError", "Cannot add property " . $key . ", object is not extensible");
		}
		$object->props[$key] = $value;
	}

	public function describeObject(JsObject $object) : string{
		if($object instanceof JsCallable){
			return "function " . $object->name;
		}
		$constructor = null;
		for($current = $object; $current !== null; $current = $current->proto){
			if(array_key_exists("constructor", $current->props)){
				$constructor = $current->props["constructor"];
				break;
			}
		}
		if($constructor instanceof JsCallable && $constructor->name !== ""){
			return "#<" . $constructor->name . ">";
		}
		return "#<" . $object->className . ">";
	}

	public function hasProperty(JsObject $object, string|int|JsSymbol $key) : bool{
		if($key instanceof JsSymbol){
			$id = spl_object_id($key);
			for($current = $object; $current !== null; $current = $current->proto){
				if(isset($current->symbols[$id])){
					return true;
				}
			}
			return false;
		}
		for($current = $object; $current !== null; $current = $current->proto){
			if($this->hasOwnProperty($current, $key)){
				return true;
			}
		}
		return false;
	}

	public function hasOwnProperty(JsObject $object, string|int|JsSymbol $key) : bool{
		if($key instanceof JsSymbol){
			return isset($object->symbols[spl_object_id($key)]);
		}
		if($object instanceof JsArray){
			if($key === "length"){
				return true;
			}
			if(self::isIndex($key)){
				return (int) $key < count($object->items);
			}
		}
		if(array_key_exists($key, $object->props) || isset($object->accessors[$key])){
			return true;
		}
		if($object instanceof JsCallable && ($key === "name" || $key === "length")){
			return true;
		}
		return $object instanceof JsFunction && $key === "prototype" && $object->needsPrototype;
	}

	public function deleteProperty(JsObject $object, string|int|JsSymbol $key) : bool{
		if($key instanceof JsSymbol){
			unset($object->symbols[spl_object_id($key)]);
			return true;
		}
		if($object instanceof JsArray && self::isIndex($key)){
			if($object->frozen){
				$this->throwError("TypeError", "Cannot delete property '" . $key . "' of " . $this->describeObject($object));
			}
			$index = (int) $key;
			if($index < count($object->items)){
				$object->items[$index] = null;
			}
			return true;
		}
		if(isset($object->locked[$key])){
			$this->throwError("TypeError", "Cannot delete property '" . $key . "' of " . $this->describeObject($object));
		}
		unset($object->props[$key], $object->accessors[$key], $object->hidden[$key]);
		return true;
	}

	/**
	 * Returns the own string keys of an object: integer keys first, in
	 * ascending order, then the other keys in insertion order.
	 *
	 * @return list<string>
	 */
	public function ownKeys(JsObject $object, bool $includeHidden = false) : array{
		$integers = [];
		$strings = [];
		if($object instanceof JsArray){
			for($i = 0, $count = count($object->items); $i < $count; ++$i){
				$integers[] = $i;
			}
		}
		foreach([$object->props, $object->accessors] as $map){
			foreach($map as $key => $unused){
				if(!$includeHidden && isset($object->hidden[$key])){
					continue;
				}
				if(is_int($key)){
					$integers[] = $key;
				}else{
					$strings[] = $key;
				}
			}
		}
		if(!$object instanceof JsArray && count($integers) > 1){
			sort($integers);
		}
		$keys = [];
		foreach($integers as $integer){
			$keys[] = (string) $integer;
		}
		return count($strings) === 0 ? $keys : array_merge($keys, $strings);
	}

	/**
	 * @return list<JsSymbol>
	 */
	public function ownSymbols(JsObject $object) : array{
		$symbols = [];
		foreach($object->symbols as [$symbol, $unused]){
			$symbols[] = $symbol;
		}
		return $symbols;
	}

	public function isEnumerable(JsObject $object, string $key) : bool{
		if($object instanceof JsArray && self::isIndex($key)){
			return true;
		}
		return !isset($object->hidden[$key]);
	}

	/**
	 * Copies the own enumerable properties of $source onto $target.
	 *
	 * @param array<string, true> $excluded
	 */
	public function copyDataProperties(JsObject $target, mixed $source, array $excluded = []) : void{
		if($source === null || $source instanceof JsNull){
			return;
		}
		if(is_string($source)){
			foreach(mb_str_split($source, 1, "UTF-8") as $index => $char){
				if(!isset($excluded[(string) $index])){
					$this->set($target, (string) $index, $char);
				}
			}
			return;
		}
		if(!$source instanceof JsObject){
			return;
		}
		foreach($this->ownKeys($source) as $key){
			if(!isset($excluded[$key])){
				$this->createDataProperty($target, $key, $this->getFrom($source, $key, $source));
			}
		}
		foreach($source->symbols as [$symbol, $value]){
			$target->symbols[spl_object_id($symbol)] = [$symbol, $value];
		}
	}

	public function createDataProperty(JsObject $object, string|int $key, mixed $value) : void{
		if($object instanceof JsArray && self::isIndex($key)){
			$this->setOn($object, $key, $value, $object);
			return;
		}
		unset($object->accessors[$key], $object->hidden[$key]);
		$object->props[$key] = $value;
	}

	public function isCallable(mixed $value) : bool{
		return $value instanceof JsCallable;
	}

	public function isConstructor(mixed $value) : bool{
		if($value instanceof NativeFunction){
			return $value->construct !== null;
		}
		if($value instanceof JsFunction){
			$node = $value->node;
			return $node === null || (!$node["arrow"] && !$node["method"] && !$node["async"] && !$node["generator"]) || $value->isClass;
		}
		return false;
	}

	/**
	 * @param list<mixed> $args
	 */
	public function call(mixed $function, mixed $thisValue, array $args) : mixed{
		if($function instanceof NativeFunction){
			return $this->callNative($function, $thisValue, $args);
		}
		if($function instanceof JsFunction){
			if($function->isClass){
				$this->throwError("TypeError", "Class constructor " . $function->name . " cannot be invoked without 'new'");
			}
			return $this->callFunction($function, $thisValue, $args, null);
		}
		$this->throwError("TypeError", $this->describeValue($function) . " is not a function");
	}

	/**
	 * @param list<mixed> $args
	 */
	private function callNative(NativeFunction $function, mixed $thisValue, array $args) : mixed{
		try{
			return ($function->call)($thisValue, $args);
		}catch(JsThrow|ScriptTimeoutException|GeneratorReturn $e){
			throw $e;
		}catch(SyntaxErrorException $e){
			throw new JsThrow($this->makeError("SyntaxError", $e->getMessage()));
		}catch(RuntimeException $e){
			throw new JsThrow($this->makeError("Error", $e->getMessage()));
		}catch(Error $e){
			if($e instanceof \FiberError){
				throw $e;
			}
			throw new JsThrow($this->makeError("Error", "Internal error in " . $function->name . ": " . $e->getMessage()));
		}
	}

	public function describeValue(mixed $value) : string{
		if($value === null){
			return "undefined";
		}
		if($value instanceof JsNull){
			return "null";
		}
		if(is_string($value)){
			return "\"" . $value . "\"";
		}
		if($value instanceof JsObject){
			return $this->describeObject($value);
		}
		return $this->toStringSafe($value);
	}

	/**
	 * @param list<mixed> $args
	 */
	public function construct(mixed $function, array $args, ?JsObject $newTarget = null) : JsObject{
		if(!$this->isConstructor($function)){
			$this->throwError("TypeError", $this->describeValue($function) . " is not a constructor");
		}
		$newTarget ??= $function;
		if($function instanceof NativeFunction){
			try{
				return ($function->construct)($args, $newTarget);
			}catch(JsThrow|ScriptTimeoutException|GeneratorReturn $e){
				throw $e;
			}catch(RuntimeException $e){
				throw new JsThrow($this->makeError("Error", $e->getMessage()));
			}
		}
		if(!$function instanceof JsFunction){
			$this->throwError("TypeError", "Not a constructor");
		}
		if(!$function->derived){
			$proto = $this->get($newTarget, "prototype");
			$object = new JsObject($proto instanceof JsObject ? $proto : $this->objectPrototype);
			$this->initializeFields($function, $object);
			if($function->node === null){
				return $object;
			}
			$result = $this->invoke($function, $object, $args, $newTarget)[0];
			return $result instanceof JsObject ? $result : $object;
		}
		$parent = $function->proto;
		if($function->node === null){
			$object = $this->construct($parent, $args, $newTarget);
			$this->initializeFields($function, $object);
			return $object;
		}
		[$result, $scope] = $this->invoke($function, Tdz::get(), $args, $newTarget);
		if($result instanceof JsObject){
			return $result;
		}
		$thisValue = $scope->vars["this"] ?? null;
		if(!$thisValue instanceof JsObject){
			$this->throwError("ReferenceError", "Must call super constructor in derived class before accessing 'this' or returning from derived constructor");
		}
		return $thisValue;
	}

	private function initializeFields(JsFunction $class, JsObject $object) : void{
		foreach($class->fields as [$key, $valueNode, $private]){
			$value = null;
			if($valueNode !== null){
				$scope = new Scope($class->scope, true);
				$scope->vars["this"] = $object;
				$scope->vars["%fn"] = $class;
				$scope->vars["%nt"] = null;
				$value = $this->evaluateNamed($valueNode, $scope, is_string($key) ? $key : "");
			}
			if($key instanceof JsSymbol){
				$object->symbols[spl_object_id($key)] = [$key, $value];
			}elseif($private){
				$this->defineHidden($object, $key, $value);
			}else{
				$this->createDataProperty($object, $key, $value);
			}
		}
	}

	/**
	 * @param list<mixed> $args
	 */
	private function callFunction(JsFunction $function, mixed $thisValue, array $args, ?JsObject $newTarget) : mixed{
		$node = $function->node;
		if($node === null){
			return null;
		}
		if($node["generator"]){
			if($node["async"]){
				$this->throwError("TypeError", "Async generators are not supported");
			}
			return $this->makeGenerator($function, $thisValue, $args);
		}
		if($node["async"]){
			return $this->runAsync($function, $thisValue, $args);
		}
		return $this->invoke($function, $thisValue, $args, $newTarget)[0];
	}

	/**
	 * Runs the body of a function and returns its result and scope.
	 *
	 * @param list<mixed> $args
	 * @return array{0: mixed, 1: Scope}
	 */
	private function invoke(JsFunction $function, mixed $thisValue, array $args, ?JsObject $newTarget) : array{
		if($this->depth >= self::MAX_DEPTH){
			$this->throwError("RangeError", "Maximum call stack size exceeded");
		}
		$this->tick();
		$node = $function->node;
		$scope = new Scope($function->scope, true);
		if(!$node["arrow"]){
			$scope->vars["this"] = $thisValue;
			$scope->vars["%fn"] = $function;
			$scope->vars["%nt"] = $newTarget;
			if($node["args"]){
				$arguments = new JsArray($this->arrayPrototype, $args);
				$arguments->className = "Arguments";
				$scope->vars["arguments"] = $arguments;
			}
		}
		$savedFile = $this->file;
		$savedLine = $this->line;
		$savedDepth = $this->depth;
		$frameCount = count($this->frames);
		$this->frames[] = [$function->name, $function->file, $savedLine];
		$this->depth++;
		$this->file = $function->file;
		try{
			$this->bindParameters($node["params"], $args, $scope);
			foreach($node["vars"] as $name){
				if(!array_key_exists($name, $scope->vars)){
					$scope->vars[$name] = null;
				}
			}
			$value = null;
			if($node["expression"]){
				$value = $this->evaluate($node["body"], $scope);
			}else{
				$body = $node["body"];
				$this->hoistLexical($body, $scope);
				foreach($body["body"] as $statement){
					$completion = $this->execute($statement, $scope);
					if($completion !== null){
						if($completion->type === Completion::RETURN){
							$value = $completion->value;
						}
						break;
					}
				}
			}
		}catch(Throwable $e){
			$this->frames = array_slice($this->frames, 0, $frameCount);
			$this->depth = $savedDepth;
			$this->file = $savedFile;
			throw $e;
		}
		$this->frames = array_slice($this->frames, 0, $frameCount);
		$this->depth = $savedDepth;
		$this->file = $savedFile;
		$this->line = $savedLine;
		return [$value, $scope];
	}

	/**
	 * @param list<array<string, mixed>> $params
	 * @param list<mixed>                $args
	 */
	private function bindParameters(array $params, array $args, Scope $scope) : void{
		foreach($params as $index => $param){
			if($param["type"] === "Identifier"){
				$scope->vars[$param["name"]] = $args[$index] ?? null;
				continue;
			}
			if($param["type"] === "RestElement"){
				$this->bindPattern($param["argument"], $this->newArray(array_slice($args, $index)), $scope, "let");
				break;
			}
			$this->bindPattern($param, $args[$index] ?? null, $scope, "let");
		}
	}

	/**
	 * @param list<mixed> $args
	 */
	private function runAsync(JsFunction $function, mixed $thisValue, array $args) : JsPromise{
		$promise = $this->newPromise();
		$fiber = new Fiber(function() use ($function, $thisValue, $args) : mixed{
			return $this->invoke($function, $thisValue, $args, null)[0];
		});
		$this->asyncFibers[spl_object_id($fiber)] = true;
		$this->stepAsync($fiber, $promise, 0, null);
		return $promise;
	}

	private function stepAsync(Fiber $fiber, JsPromise $promise, int $mode, mixed $value) : void{
		$state = $this->saveState();
		try{
			if($mode === 0){
				$request = $fiber->start();
			}elseif($mode === 1){
				$request = $fiber->resume($value);
			}else{
				$request = $fiber->throw(new JsThrow($value));
			}
		}catch(JsThrow $e){
			$this->restoreState($state);
			unset($this->asyncFibers[spl_object_id($fiber)]);
			$this->rejectPromise($promise, $e->value);
			return;
		}catch(Throwable $e){
			$this->restoreState($state);
			unset($this->asyncFibers[spl_object_id($fiber)]);
			throw $e;
		}
		$this->restoreState($state);
		if($fiber->isTerminated()){
			unset($this->asyncFibers[spl_object_id($fiber)]);
			$this->resolvePromise($promise, $fiber->getReturn());
			return;
		}
		$awaited = $this->promiseResolve($request[1] ?? null);
		$this->onSettled($awaited, function(int $settledState, mixed $settledValue) use ($fiber, $promise) : void{
			$this->stepAsync($fiber, $promise, $settledState === JsPromise::FULFILLED ? 1 : 2, $settledValue);
		});
	}

	public function await(mixed $value) : mixed{
		$fiber = Fiber::getCurrent();
		if($fiber === null || !isset($this->asyncFibers[spl_object_id($fiber)])){
			$promise = $this->promiseResolve($value);
			$promise->handled = true;
			unset($this->unhandled[spl_object_id($promise)]);
			$this->drainJobs();
			if($promise->state === JsPromise::PENDING){
				$this->throwError("Error", "Top-level await on a promise that does not settle during loading");
			}
			if($promise->state === JsPromise::REJECTED){
				throw new JsThrow($promise->value);
			}
			return $promise->value;
		}
		$state = $this->saveState();
		try{
			$result = Fiber::suspend(["await", $value]);
		}catch(Throwable $e){
			$this->restoreState($state);
			throw $e;
		}
		$this->restoreState($state);
		return $result;
	}

	/**
	 * @param list<mixed> $args
	 */
	private function makeGenerator(JsFunction $function, mixed $thisValue, array $args) : JsObject{
		$proto = $this->get($function, "prototype");
		$generator = new JsObject($proto instanceof JsObject ? $proto : $this->generatorPrototype);
		$generator->className = "Generator";
		$generator->host = new GeneratorState(new Fiber(function() use ($function, $thisValue, $args) : mixed{
			return $this->invoke($function, $thisValue, $args, null)[0];
		}));
		return $generator;
	}

	/**
	 * Resumes a generator: $mode is 0 for next, 1 for return, 2 for throw.
	 */
	public function generatorResume(mixed $generator, int $mode, mixed $value) : JsObject{
		if(!$generator instanceof JsObject || !$generator->host instanceof GeneratorState){
			$this->throwError("TypeError", "next method called on incompatible receiver");
		}
		$state = $generator->host;
		if($state->running){
			$this->throwError("TypeError", "Generator is already running");
		}
		if(!$state->started && $mode !== 0){
			$state->done = true;
		}
		if($state->done){
			if($mode === 2){
				throw new JsThrow($value);
			}
			return $this->iterResult($mode === 1 ? $value : null, true);
		}
		$saved = $this->saveState();
		$state->running = true;
		try{
			if(!$state->started){
				$state->started = true;
				$request = $state->fiber->start();
			}elseif($mode === 0){
				$request = $state->fiber->resume($value);
			}elseif($mode === 1){
				$request = $state->fiber->throw(new GeneratorReturn($value));
			}else{
				$request = $state->fiber->throw(new JsThrow($value));
			}
		}catch(GeneratorReturn $e){
			$state->running = false;
			$state->done = true;
			$this->restoreState($saved);
			return $this->iterResult($e->value, true);
		}catch(Throwable $e){
			$state->running = false;
			$state->done = true;
			$this->restoreState($saved);
			throw $e;
		}
		$state->running = false;
		$this->restoreState($saved);
		if($state->fiber->isTerminated()){
			$state->done = true;
			return $this->iterResult($state->fiber->getReturn(), true);
		}
		return $this->iterResult($request[1] ?? null, false);
	}

	private function yieldValue(mixed $value) : mixed{
		if(Fiber::getCurrent() === null){
			$this->throwError("SyntaxError", "yield outside of a generator");
		}
		$state = $this->saveState();
		try{
			$sent = Fiber::suspend(["yield", $value]);
		}catch(Throwable $e){
			$this->restoreState($state);
			throw $e;
		}
		$this->restoreState($state);
		return $sent;
	}

	private function yieldDelegate(mixed $iterable) : mixed{
		$method = $iterable instanceof JsObject || is_string($iterable) ? $this->get($iterable, $this->symIterator) : null;
		if(!$method instanceof JsCallable){
			$this->throwError("TypeError", $this->describeValue($iterable) . " is not iterable");
		}
		$iterator = $this->call($method, $iterable, []);
		$next = $this->get($iterator, "next");
		$sent = null;
		while(true){
			$result = $this->call($next, $iterator, [$sent]);
			if(!$result instanceof JsObject){
				$this->throwError("TypeError", "Iterator result is not an object");
			}
			if($this->toBoolean($this->get($result, "done"))){
				return $this->get($result, "value");
			}
			$sent = $this->yieldValue($this->get($result, "value"));
		}
	}

	public function newPromise() : JsPromise{
		return new JsPromise($this->promisePrototype);
	}

	public function promiseResolve(mixed $value) : JsPromise{
		if($value instanceof JsPromise){
			return $value;
		}
		$promise = $this->newPromise();
		$this->resolvePromise($promise, $value);
		return $promise;
	}

	public function resolvePromise(JsPromise $promise, mixed $value) : void{
		if($promise->resolving){
			return;
		}
		$promise->resolving = true;
		$this->resolveInner($promise, $value);
	}

	public function rejectPromise(JsPromise $promise, mixed $reason) : void{
		if($promise->resolving){
			return;
		}
		$promise->resolving = true;
		$this->settle($promise, JsPromise::REJECTED, $reason);
	}

	private function resolveInner(JsPromise $promise, mixed $value) : void{
		if($value === $promise){
			$this->settle($promise, JsPromise::REJECTED, $this->makeError("TypeError", "Chaining cycle detected for promise"));
			return;
		}
		if($value instanceof JsPromise){
			$this->onSettled($value, function(int $state, mixed $settled) use ($promise) : void{
				$this->settle($promise, $state, $settled);
			});
			return;
		}
		if($value instanceof JsObject){
			try{
				$then = $this->get($value, "then");
			}catch(JsThrow $e){
				$this->settle($promise, JsPromise::REJECTED, $e->value);
				return;
			}
			if($then instanceof JsCallable){
				$this->enqueueJob(function() use ($promise, $value, $then) : void{
					$called = false;
					$resolve = $this->native("", 1, function(mixed $thisValue, array $args) use ($promise, &$called) : mixed{
						if(!$called){
							$called = true;
							$this->resolveInner($promise, $args[0] ?? null);
						}
						return null;
					});
					$reject = $this->native("", 1, function(mixed $thisValue, array $args) use ($promise, &$called) : mixed{
						if(!$called){
							$called = true;
							$this->settle($promise, JsPromise::REJECTED, $args[0] ?? null);
						}
						return null;
					});
					try{
						$this->call($then, $value, [$resolve, $reject]);
					}catch(JsThrow $e){
						if(!$called){
							$called = true;
							$this->settle($promise, JsPromise::REJECTED, $e->value);
						}
					}
				});
				return;
			}
		}
		$this->settle($promise, JsPromise::FULFILLED, $value);
	}

	private function settle(JsPromise $promise, int $state, mixed $value) : void{
		if($promise->state !== JsPromise::PENDING){
			return;
		}
		$promise->state = $state;
		$promise->value = $value;
		$reactions = $promise->reactions;
		$promise->reactions = [];
		foreach($reactions as $reaction){
			$this->enqueueJob(function() use ($reaction, $state, $value) : void{
				$reaction($state, $value);
			});
		}
		if($state === JsPromise::REJECTED && !$promise->handled){
			$this->unhandled[spl_object_id($promise)] = $promise;
		}
	}

	/**
	 * Calls $callback with the state and value of a promise once it is
	 * settled, as a microtask.
	 *
	 * @param Closure(int, mixed) : void $callback
	 */
	public function onSettled(JsPromise $promise, Closure $callback) : void{
		$promise->handled = true;
		unset($this->unhandled[spl_object_id($promise)]);
		if($promise->state === JsPromise::PENDING){
			$promise->reactions[] = $callback;
			return;
		}
		$state = $promise->state;
		$value = $promise->value;
		$this->enqueueJob(function() use ($callback, $state, $value) : void{
			$callback($state, $value);
		});
	}

	public function promiseThen(JsPromise $promise, mixed $onFulfilled, mixed $onRejected) : JsPromise{
		$derived = $this->newPromise();
		$this->onSettled($promise, function(int $state, mixed $value) use ($derived, $onFulfilled, $onRejected) : void{
			$handler = $state === JsPromise::FULFILLED ? $onFulfilled : $onRejected;
			if(!$handler instanceof JsCallable){
				if($state === JsPromise::FULFILLED){
					$this->resolvePromise($derived, $value);
				}else{
					$this->rejectPromise($derived, $value);
				}
				return;
			}
			try{
				$this->resolvePromise($derived, $this->call($handler, null, [$value]));
			}catch(JsThrow $e){
				$this->rejectPromise($derived, $e->value);
			}
		});
		return $derived;
	}

	public function enqueueJob(Closure $job) : void{
		$this->jobs->enqueue([$job, $this->context]);
	}

	public function hasJobs() : bool{
		return !$this->jobs->isEmpty();
	}

	/**
	 * Runs every pending microtask. Uncaught errors are reported.
	 */
	public function drainJobs() : void{
		$context = $this->context;
		while(!$this->jobs->isEmpty()){
			[$job, $jobContext] = $this->jobs->dequeue();
			$this->context = $jobContext;
			$this->tick();
			try{
				$job();
			}catch(JsThrow $e){
				$this->reportError($e->value);
			}
		}
		$this->context = $context;
	}

	/**
	 * Reports the promises rejected without a handler since the last call.
	 */
	public function reportUnhandledRejections() : void{
		$unhandled = $this->unhandled;
		$this->unhandled = [];
		foreach($unhandled as $promise){
			if(!$promise->handled){
				$this->print("error", "Uncaught (in promise) " . $this->describeError($promise->value));
			}
		}
	}

	/**
	 * Removes the queued microtasks of a context, used when a pack is
	 * disabled.
	 */
	public function removeJobs(?string $context) : void{
		$kept = new SplQueue();
		while(!$this->jobs->isEmpty()){
			$job = $this->jobs->dequeue();
			if($job[1] !== $context){
				$kept->enqueue($job);
			}
		}
		$this->jobs = $kept;
	}

	/**
	 * Iterates a value with the iteration protocol. Breaking out of the loop
	 * closes the iterator.
	 */
	public function iterate(mixed $iterable) : Generator{
		if($iterable instanceof JsArray && $iterable->className === "Array" && !isset($iterable->symbols[spl_object_id($this->symIterator)]) && $this->arrayIteratorIsNative()){
			for($i = 0; $i < count($iterable->items); ++$i){
				yield $iterable->items[$i];
			}
			return;
		}
		if(is_string($iterable)){
			foreach(mb_str_split($iterable, 1, "UTF-8") as $char){
				yield $char;
			}
			return;
		}
		$method = $iterable instanceof JsObject ? $this->getSymbol($iterable, $this->symIterator) : null;
		if(!$method instanceof JsCallable){
			$this->throwError("TypeError", $this->describeValue($iterable) . " is not iterable");
		}
		$iterator = $this->call($method, $iterable, []);
		if(!$iterator instanceof JsObject){
			$this->throwError("TypeError", "Result of the Symbol.iterator method is not an object");
		}
		$next = $this->get($iterator, "next");
		$done = false;
		try{
			while(true){
				$result = $this->call($next, $iterator, []);
				if(!$result instanceof JsObject){
					$this->throwError("TypeError", "Iterator result is not an object");
				}
				if($this->toBoolean($this->get($result, "done"))){
					$done = true;
					return;
				}
				yield $this->get($result, "value");
			}
		}finally{
			if(!$done){
				$return = $this->get($iterator, "return");
				if($return instanceof JsCallable){
					$this->call($return, $iterator, []);
				}
			}
		}
	}

	private ?NativeFunction $nativeArrayIterator = null;

	public function setNativeArrayIterator(NativeFunction $iterator) : void{
		$this->nativeArrayIterator = $iterator;
	}

	private function arrayIteratorIsNative() : bool{
		$current = $this->arrayPrototype->symbols[spl_object_id($this->symIterator)][1] ?? null;
		return $current === $this->nativeArrayIterator;
	}

	/**
	 * @return list<mixed>
	 */
	public function iterableToList(mixed $iterable) : array{
		if($iterable instanceof JsArray && $iterable->className === "Array" && $this->arrayIteratorIsNative()){
			return $iterable->items;
		}
		$list = [];
		foreach($this->iterate($iterable) as $value){
			$list[] = $value;
		}
		return $list;
	}

	public function lookup(string $name, Scope $scope) : mixed{
		for($current = $scope; $current !== null; $current = $current->parent){
			if(array_key_exists($name, $current->vars)){
				$value = $current->vars[$name];
				if($value instanceof Tdz){
					$this->throwError("ReferenceError", "Cannot access '" . $name . "' before initialization");
				}
				return $value;
			}
			if(isset($current->imports[$name])){
				return $this->readExport($current->imports[$name][0], $current->imports[$name][1]);
			}
		}
		if(array_key_exists($name, $this->global->props)){
			return $this->global->props[$name];
		}
		if(isset($this->global->accessors[$name])){
			return $this->getFrom($this->global, $name, $this->global);
		}
		$this->throwError("ReferenceError", $name . " is not defined");
	}

	private function hasBinding(string $name, Scope $scope) : bool{
		for($current = $scope; $current !== null; $current = $current->parent){
			if(array_key_exists($name, $current->vars) || isset($current->imports[$name])){
				return true;
			}
		}
		return array_key_exists($name, $this->global->props) || isset($this->global->accessors[$name]);
	}

	public function assignVariable(string $name, mixed $value, Scope $scope) : void{
		for($current = $scope; $current !== null; $current = $current->parent){
			if(array_key_exists($name, $current->vars)){
				if(isset($current->consts[$name])){
					$this->throwError("TypeError", "Assignment to constant variable.");
				}
				if($current->vars[$name] instanceof Tdz){
					$this->throwError("ReferenceError", "Cannot access '" . $name . "' before initialization");
				}
				$current->vars[$name] = $value;
				return;
			}
			if(isset($current->imports[$name])){
				$this->throwError("TypeError", "Assignment to constant variable.");
			}
		}
		if(array_key_exists($name, $this->global->props) || isset($this->global->accessors[$name])){
			$this->setOn($this->global, $name, $value, $this->global);
			return;
		}
		$this->throwError("ReferenceError", $name . " is not defined");
	}

	public function readExport(Module $module, string $name) : mixed{
		$binding = $module->resolve($name);
		if($binding === null){
			if($module->missing !== null){
				return ($module->missing)($name);
			}
			$this->throwError("SyntaxError", "The requested module '" . $module->path . "' does not provide an export named '" . $name . "'");
		}
		switch($binding[0]){
			case "local":
				$value = $binding[1]->vars[$binding[2]] ?? null;
				if($value instanceof Tdz){
					$this->throwError("ReferenceError", "Cannot access '" . $name . "' before initialization");
				}
				return $value;
			case "value":
				return $binding[1];
			case "namespace":
				return $this->moduleNamespace($binding[1]);
		}
		return null;
	}

	public function moduleNamespace(Module $module) : JsObject{
		if($module->namespace !== null){
			return $module->namespace;
		}
		$namespace = new JsObject(null);
		$namespace->className = "Module";
		foreach($module->exportNames() as $name){
			$getter = $this->native($name, 0, function(mixed $thisValue, array $args) use ($module, $name) : mixed{
				return $this->readExport($module, $name);
			});
			$namespace->accessors[$name] = [$getter, null];
		}
		$namespace->extensible = false;
		if($module->missing !== null){
			$missing = $module->missing;
			$namespace->miss = function(JsObject $receiver, string $key) use ($missing) : mixed{
				return $missing($key);
			};
		}
		$module->namespace = $namespace;
		return $namespace;
	}

	/**
	 * Declares the lexical bindings and function declarations of a block.
	 *
	 * @param array<string, mixed> $node
	 */
	public function hoistLexical(array $node, Scope $scope) : void{
		foreach($node["lex"] as [$name, $kind]){
			$scope->vars[$name] = Tdz::get();
			if($kind === "const"){
				$scope->consts[$name] = true;
			}
		}
		foreach($node["funcs"] as $declaration){
			$fn = $declaration["fn"];
			$scope->vars[$fn["id"]] = $this->createFunction($fn, $scope, $fn["id"]);
		}
	}

	/**
	 * @param array<string, mixed> $node
	 */
	public function createFunction(array $node, Scope $scope, ?string $name = null, ?JsObject $home = null) : JsFunction{
		$function = new JsFunction($this->functionPrototype, $node, $scope);
		$function->name = $name ?? ($node["id"] ?? "");
		$function->home = $home;
		$function->file = $this->file;
		$length = 0;
		foreach($node["params"] as $param){
			if($param["type"] === "AssignmentPattern" || $param["type"] === "RestElement"){
				break;
			}
			$length++;
		}
		$function->length = $length;
		$function->needsPrototype = !$node["arrow"] && !$node["method"] && (!$node["async"] || $node["generator"]);
		return $function;
	}

	/**
	 * @param array<string, mixed> $node
	 * @param list<string>         $labels
	 */
	public function execute(array $node, Scope $scope, array $labels = []) : ?Completion{
		$this->line = $node["line"] ?? $this->line;
		switch($node["type"]){
			case "ExpressionStatement":
				$this->evaluate($node["expression"], $scope);
				return null;
			case "VariableDeclaration":
				$this->declareVariables($node, $scope);
				return null;
			case "ReturnStatement":
				return new Completion(Completion::RETURN, $node["argument"] === null ? null : $this->evaluate($node["argument"], $scope));
			case "IfStatement":
				if($this->toBoolean($this->evaluate($node["test"], $scope))){
					return $this->execute($node["consequent"], $scope);
				}
				return $node["alternate"] === null ? null : $this->execute($node["alternate"], $scope);
			case "BlockStatement":
				return $this->executeBlock($node, $scope);
			case "ForStatement":
				return $this->executeFor($node, $scope, $labels);
			case "ForOfStatement":
				return $this->executeForOf($node, $scope, $labels);
			case "ForInStatement":
				return $this->executeForIn($node, $scope, $labels);
			case "WhileStatement":
				while($this->toBoolean($this->evaluate($node["test"], $scope))){
					$this->tick();
					$completion = $this->execute($node["body"], $scope);
					if($completion !== null){
						if($completion->type === Completion::BREAK && ($completion->label === null || in_array($completion->label, $labels, true))){
							break;
						}
						if($completion->type === Completion::CONTINUE && ($completion->label === null || in_array($completion->label, $labels, true))){
							continue;
						}
						return $completion;
					}
				}
				return null;
			case "DoWhileStatement":
				do{
					$this->tick();
					$completion = $this->execute($node["body"], $scope);
					if($completion !== null){
						if($completion->type === Completion::BREAK && ($completion->label === null || in_array($completion->label, $labels, true))){
							break;
						}
						if(!($completion->type === Completion::CONTINUE && ($completion->label === null || in_array($completion->label, $labels, true)))){
							return $completion;
						}
					}
				}while($this->toBoolean($this->evaluate($node["test"], $scope)));
				return null;
			case "BreakStatement":
				return new Completion(Completion::BREAK, null, $node["label"]);
			case "ContinueStatement":
				return new Completion(Completion::CONTINUE, null, $node["label"]);
			case "ThrowStatement":
				$value = $this->evaluate($node["argument"], $scope);
				throw new JsThrow($value, "Uncaught " . $this->describeValue($value));
			case "TryStatement":
				return $this->executeTry($node, $scope);
			case "SwitchStatement":
				return $this->executeSwitch($node, $scope, $labels);
			case "LabeledStatement":
				$labels[] = $node["label"];
				$completion = $this->execute($node["body"], $scope, $labels);
				if($completion !== null && $completion->type === Completion::BREAK && $completion->label === $node["label"]){
					return null;
				}
				return $completion;
			case "FunctionDeclaration":
			case "EmptyStatement":
			case "ImportDeclaration":
				return null;
			case "ClassDeclaration":
				$class = $this->evaluateClass($node["class"], $scope, null);
				$scope->vars[$node["class"]["id"]] = $class;
				return null;
			case "ExportNamedDeclaration":
				if($node["declaration"] !== null){
					return $this->execute($node["declaration"], $scope);
				}
				return null;
			case "ExportDefaultDeclaration":
				if($node["declaration"] !== null){
					return $this->execute($node["declaration"], $scope);
				}
				$scope->vars["*default*"] = $this->evaluateNamed($node["expression"], $scope, "default");
				return null;
			case "ExportAllDeclaration":
				return null;
		}
		$this->throwError("SyntaxError", "Unsupported statement " . $node["type"]);
	}

	/**
	 * @param array<string, mixed> $node
	 */
	private function declareVariables(array $node, Scope $scope) : void{
		$kind = $node["kind"];
		foreach($node["declarations"] as $declarator){
			$id = $declarator["id"];
			if($declarator["init"] === null){
				if($kind === "var"){
					continue;
				}
				$this->bindPattern($id, null, $scope, $kind);
				continue;
			}
			$name = $id["type"] === "Identifier" ? $id["name"] : "";
			$value = $this->evaluateNamed($declarator["init"], $scope, $name);
			$this->bindPattern($id, $value, $scope, $kind);
		}
	}

	/**
	 * @param array<string, mixed> $node
	 */
	private function executeBlock(array $node, Scope $scope) : ?Completion{
		if(count($node["lex"]) > 0 || count($node["funcs"]) > 0){
			$scope = new Scope($scope);
			$this->hoistLexical($node, $scope);
		}
		foreach($node["body"] as $statement){
			$completion = $this->execute($statement, $scope);
			if($completion !== null){
				return $completion;
			}
		}
		return null;
	}

	private static function copyScope(Scope $scope) : Scope{
		$copy = new Scope($scope->parent);
		$copy->vars = $scope->vars;
		$copy->consts = $scope->consts;
		return $copy;
	}

	/**
	 * @param array<string, mixed> $node
	 * @param list<string>         $labels
	 */
	private function executeFor(array $node, Scope $scope, array $labels) : ?Completion{
		$init = $node["init"];
		$loopScope = $scope;
		$perIteration = false;
		if($init !== null){
			if($init["type"] === "VariableDeclaration"){
				if($init["kind"] !== "var"){
					$loopScope = new Scope($scope);
					foreach($init["declarations"] as $declarator){
						foreach(Parser::patternNames($declarator["id"]) as $name){
							$loopScope->vars[$name] = Tdz::get();
						}
					}
					$perIteration = true;
				}
				$this->declareVariables($init, $loopScope);
			}else{
				$this->evaluate($init, $scope);
			}
		}
		$iterationScope = $perIteration ? self::copyScope($loopScope) : $loopScope;
		while(true){
			$this->tick();
			if($node["test"] !== null && !$this->toBoolean($this->evaluate($node["test"], $iterationScope))){
				break;
			}
			$completion = $this->execute($node["body"], $iterationScope);
			if($completion !== null){
				if($completion->type === Completion::BREAK && ($completion->label === null || in_array($completion->label, $labels, true))){
					break;
				}
				if(!($completion->type === Completion::CONTINUE && ($completion->label === null || in_array($completion->label, $labels, true)))){
					return $completion;
				}
			}
			if($perIteration){
				$iterationScope = self::copyScope($iterationScope);
			}
			if($node["update"] !== null){
				$this->evaluate($node["update"], $iterationScope);
			}
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $left
	 */
	private function bindForTarget(array $left, mixed $value, Scope $scope, Scope $iterationScope) : void{
		if($left["type"] === "VariableDeclaration"){
			$id = $left["declarations"][0]["id"];
			if($left["kind"] === "var"){
				$this->bindPattern($id, $value, $scope, "var");
			}else{
				$this->bindPattern($id, $value, $iterationScope, $left["kind"]);
			}
			return;
		}
		$this->bindPattern($left, $value, $scope, null);
	}

	/**
	 * @param array<string, mixed> $node
	 * @param list<string>         $labels
	 */
	private function executeForOf(array $node, Scope $scope, array $labels) : ?Completion{
		$iterable = $this->evaluate($node["right"], $scope);
		foreach($this->iterate($iterable) as $value){
			$this->tick();
			if($node["await"]){
				$value = $this->await($value);
			}
			$iterationScope = new Scope($scope);
			$this->bindForTarget($node["left"], $value, $scope, $iterationScope);
			$completion = $this->execute($node["body"], $iterationScope);
			if($completion !== null){
				if($completion->type === Completion::BREAK && ($completion->label === null || in_array($completion->label, $labels, true))){
					break;
				}
				if(!($completion->type === Completion::CONTINUE && ($completion->label === null || in_array($completion->label, $labels, true)))){
					return $completion;
				}
			}
		}
		return null;
	}

	/**
	 * @return list<string>
	 */
	public function forInKeys(mixed $value) : array{
		if($value === null || $value instanceof JsNull){
			return [];
		}
		if(is_string($value)){
			$keys = [];
			for($i = 0, $length = mb_strlen($value, "UTF-8"); $i < $length; ++$i){
				$keys[] = (string) $i;
			}
			return $keys;
		}
		if(!$value instanceof JsObject){
			return [];
		}
		$keys = [];
		$seen = [];
		for($current = $value; $current !== null; $current = $current->proto){
			foreach($this->ownKeys($current, true) as $key){
				if(isset($seen[$key])){
					continue;
				}
				$seen[$key] = true;
				if($this->isEnumerable($current, $key)){
					$keys[] = $key;
				}
			}
		}
		return $keys;
	}

	/**
	 * @param array<string, mixed> $node
	 * @param list<string>         $labels
	 */
	private function executeForIn(array $node, Scope $scope, array $labels) : ?Completion{
		$object = $this->evaluate($node["right"], $scope);
		foreach($this->forInKeys($object) as $key){
			$this->tick();
			if($object instanceof JsObject && !$this->hasProperty($object, $key)){
				continue;
			}
			$iterationScope = new Scope($scope);
			$this->bindForTarget($node["left"], $key, $scope, $iterationScope);
			$completion = $this->execute($node["body"], $iterationScope);
			if($completion !== null){
				if($completion->type === Completion::BREAK && ($completion->label === null || in_array($completion->label, $labels, true))){
					break;
				}
				if(!($completion->type === Completion::CONTINUE && ($completion->label === null || in_array($completion->label, $labels, true)))){
					return $completion;
				}
			}
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $node
	 */
	private function executeTry(array $node, Scope $scope) : ?Completion{
		$finalizer = $node["finalizer"];
		try{
			try{
				$completion = $this->executeBlock($node["block"], $scope);
			}catch(JsThrow $e){
				if($node["handler"] === null){
					throw $e;
				}
				$catchScope = new Scope($scope);
				if($node["param"] !== null){
					$this->bindPattern($node["param"], $e->value, $catchScope, "let");
				}
				$completion = $this->executeBlock($node["handler"], $catchScope);
			}
		}catch(JsThrow|GeneratorReturn $e){
			if($finalizer !== null){
				$override = $this->executeBlock($finalizer, $scope);
				if($override !== null){
					return $override;
				}
			}
			throw $e;
		}
		if($finalizer !== null){
			$override = $this->executeBlock($finalizer, $scope);
			if($override !== null){
				return $override;
			}
		}
		return $completion;
	}

	/**
	 * @param array<string, mixed> $node
	 * @param list<string>         $labels
	 */
	private function executeSwitch(array $node, Scope $scope, array $labels) : ?Completion{
		$discriminant = $this->evaluate($node["discriminant"], $scope);
		if(count($node["lex"]) > 0 || count($node["funcs"]) > 0){
			$scope = new Scope($scope);
			$this->hoistLexical($node, $scope);
		}
		$start = null;
		$default = null;
		foreach($node["cases"] as $index => $case){
			if($case["test"] === null){
				$default = $index;
				continue;
			}
			if($this->strictEquals($discriminant, $this->evaluate($case["test"], $scope))){
				$start = $index;
				break;
			}
		}
		$start ??= $default;
		if($start === null){
			return null;
		}
		$cases = $node["cases"];
		for($i = $start, $count = count($cases); $i < $count; ++$i){
			foreach($cases[$i]["consequent"] as $statement){
				$completion = $this->execute($statement, $scope);
				if($completion !== null){
					if($completion->type === Completion::BREAK && ($completion->label === null || in_array($completion->label, $labels, true))){
						return null;
					}
					return $completion;
				}
			}
		}
		return null;
	}

	/**
	 * Binds a pattern: $kind is "var", "let" or "const" for declarations and
	 * null for assignments.
	 *
	 * @param array<string, mixed> $pattern
	 */
	public function bindPattern(array $pattern, mixed $value, Scope $scope, ?string $kind) : void{
		switch($pattern["type"]){
			case "Identifier":
				$name = $pattern["name"];
				if($kind === null || $kind === "var"){
					$this->assignVariable($name, $value, $scope);
				}else{
					$scope->vars[$name] = $value;
					if($kind === "const"){
						$scope->consts[$name] = true;
					}
				}
				return;
			case "MemberExpression":
				$object = $this->evaluate($pattern["object"], $scope);
				$this->set($object, $this->memberKey($pattern, $scope), $value);
				return;
			case "AssignmentPattern":
				if($value === null){
					$left = $pattern["left"];
					$value = $this->evaluateNamed($pattern["right"], $scope, $left["type"] === "Identifier" ? $left["name"] : "");
				}
				$this->bindPattern($pattern["left"], $value, $scope, $kind);
				return;
			case "ArrayPattern":
				if($value === null || $value instanceof JsNull){
					$this->throwError("TypeError", $this->describeValue($value) . " is not iterable");
				}
				$iterator = $this->iterate($value);
				$first = true;
				foreach($pattern["elements"] as $element){
					if(!$first){
						$iterator->next();
					}
					$first = false;
					if($element !== null && $element["type"] === "RestElement"){
						$rest = [];
						while($iterator->valid()){
							$rest[] = $iterator->current();
							$iterator->next();
						}
						$this->bindPattern($element["argument"], $this->newArray($rest), $scope, $kind);
						return;
					}
					$item = $iterator->valid() ? $iterator->current() : null;
					if($element !== null){
						$this->bindPattern($element, $item, $scope, $kind);
					}
				}
				return;
			case "ObjectPattern":
				if($value === null || $value instanceof JsNull){
					$this->throwError("TypeError", "Cannot destructure '" . $this->describeValue($value) . "' as it is " . ($value === null ? "undefined" : "null") . ".");
				}
				$used = [];
				foreach($pattern["properties"] as $property){
					if($property["type"] === "RestElement"){
						$rest = $this->newObject();
						if($value instanceof JsObject){
							$this->copyDataProperties($rest, $value, $used);
						}
						$this->bindPattern($property["argument"], $rest, $scope, $kind);
						continue;
					}
					$key = $property["computed"] ? $this->toPropertyKey($this->evaluate($property["key"], $scope)) : $this->literalKey($property["key"]);
					if(is_string($key)){
						$used[$key] = true;
					}
					$this->bindPattern($property["value"], $this->get($value, $key), $scope, $kind);
				}
				return;
		}
		$this->throwError("SyntaxError", "Invalid destructuring target");
	}

	/**
	 * @param array<string, mixed> $key
	 */
	private function literalKey(array $key) : string{
		$value = $key["value"];
		if(is_string($value)){
			return $value;
		}
		return $this->toString($value);
	}

	/**
	 * @param array<string, mixed> $node
	 */
	private static function isAnonymousFunction(array $node) : bool{
		return match($node["type"]){
			"ArrowFunctionExpression" => true,
			"FunctionExpression" => $node["fn"]["id"] === null,
			"ClassExpression" => $node["class"]["id"] === null,
			default => false
		};
	}

	/**
	 * Evaluates an expression, naming it when it is an anonymous function or
	 * class.
	 *
	 * @param array<string, mixed> $node
	 */
	public function evaluateNamed(array $node, Scope $scope, string $name) : mixed{
		if($name !== "" && self::isAnonymousFunction($node)){
			if($node["type"] === "ClassExpression"){
				return $this->evaluateClass($node["class"], $scope, $name);
			}
			return $this->createFunction($node["fn"], $scope, $name);
		}
		return $this->evaluate($node, $scope);
	}

	/**
	 * @param array<string, mixed> $member
	 */
	private function memberKey(array $member, Scope $scope) : string|JsSymbol{
		if(!$member["computed"]){
			return $member["property"]["value"];
		}
		return $this->toPropertyKey($this->evaluate($member["property"], $scope));
	}

	/**
	 * @param list<array<string, mixed>> $nodes
	 * @return list<mixed>
	 */
	private function evaluateArguments(array $nodes, Scope $scope) : array{
		$args = [];
		foreach($nodes as $node){
			if($node["type"] === "SpreadElement"){
				foreach($this->iterableToList($this->evaluate($node["argument"], $scope)) as $value){
					$args[] = $value;
				}
			}else{
				$args[] = $this->evaluate($node, $scope);
			}
		}
		return $args;
	}

	/**
	 * @param array<string, mixed> $node
	 */
	private function describeNode(array $node) : string{
		return match($node["type"]){
			"Identifier" => $node["name"],
			"MemberExpression" => $this->describeNode($node["object"]) . ($node["computed"] ? "[...]" : "." . $node["property"]["value"]),
			"ThisExpression" => "this",
			"Super" => "super",
			"CallExpression" => $this->describeNode($node["callee"]) . "(...)",
			"ChainExpression" => $this->describeNode($node["expression"]),
			default => "expression"
		};
	}

	private function lookupThis(Scope $scope) : mixed{
		for($current = $scope; $current !== null; $current = $current->parent){
			if(array_key_exists("this", $current->vars)){
				$value = $current->vars["this"];
				if($value instanceof Tdz){
					$this->throwError("ReferenceError", "Must call super constructor in derived class before accessing 'this' or returning from derived constructor");
				}
				return $value;
			}
		}
		return null;
	}

	private function functionScope(Scope $scope) : ?Scope{
		for($current = $scope; $current !== null; $current = $current->parent){
			if(array_key_exists("%fn", $current->vars)){
				return $current;
			}
		}
		return null;
	}

	private function superGet(string|JsSymbol $key, Scope $scope) : mixed{
		$functionScope = $this->functionScope($scope);
		$function = $functionScope?->vars["%fn"] ?? null;
		if(!$function instanceof JsFunction || $function->home === null){
			$this->throwError("SyntaxError", "'super' keyword unexpected here");
		}
		$parent = $function->home->proto;
		if($parent === null){
			return null;
		}
		$thisValue = $this->lookupThis($scope);
		if($key instanceof JsSymbol){
			return $this->getSymbol($parent, $key);
		}
		return $this->getFrom($parent, $key, $thisValue);
	}

	/**
	 * @param array<string, mixed> $node
	 */
	private function superCall(array $node, Scope $scope) : mixed{
		$functionScope = $this->functionScope($scope);
		$function = $functionScope?->vars["%fn"] ?? null;
		if(!$function instanceof JsFunction || !$function->derived){
			$this->throwError("SyntaxError", "'super' keyword unexpected here");
		}
		$parent = $function->proto;
		if(!$this->isConstructor($parent)){
			$this->throwError("TypeError", "Super constructor is not a constructor");
		}
		$args = $this->evaluateArguments($node["arguments"], $scope);
		$newTarget = $functionScope->vars["%nt"] ?? $function;
		$object = $this->construct($parent, $args, $newTarget instanceof JsObject ? $newTarget : $function);
		if(!($functionScope->vars["this"] ?? null) instanceof Tdz){
			$this->throwError("ReferenceError", "Super constructor may only be called once");
		}
		$functionScope->vars["this"] = $object;
		$this->initializeFields($function, $object);
		return null;
	}

	/**
	 * @param array<string, mixed> $node
	 */
	public function evaluate(array $node, Scope $scope) : mixed{
		switch($node["type"]){
			case "Identifier":
				$name = $node["name"];
				for($current = $scope; $current !== null; $current = $current->parent){
					if(array_key_exists($name, $current->vars)){
						$value = $current->vars[$name];
						if($value instanceof Tdz){
							$this->throwError("ReferenceError", "Cannot access '" . $name . "' before initialization");
						}
						return $value;
					}
					if(isset($current->imports[$name])){
						return $this->readExport($current->imports[$name][0], $current->imports[$name][1]);
					}
				}
				return $this->lookup($name, $scope);
			case "Literal":
				return isset($node["null"]) ? JsNull::get() : $node["value"];
			case "MemberExpression":
				return $this->evaluateMember($node, $scope);
			case "CallExpression":
				return $this->evaluateCall($node, $scope);
			case "ThisExpression":
				return $this->lookupThis($scope);
			case "TemplateLiteral":
				$result = "";
				foreach($node["cooked"] as $index => $chunk){
					$result .= $chunk ?? "";
					if(isset($node["expressions"][$index])){
						$value = $this->evaluate($node["expressions"][$index], $scope);
						$result .= is_string($value) ? $value : $this->toString($value);
					}
				}
				return $result;
			case "BinaryExpression":
				return $this->binary($node["operator"], $this->evaluate($node["left"], $scope), $this->evaluate($node["right"], $scope));
			case "LogicalExpression":
				$left = $this->evaluate($node["left"], $scope);
				switch($node["operator"]){
					case "&&":
						return $this->toBoolean($left) ? $this->evaluate($node["right"], $scope) : $left;
					case "||":
						return $this->toBoolean($left) ? $left : $this->evaluate($node["right"], $scope);
					default:
						return ($left === null || $left instanceof JsNull) ? $this->evaluate($node["right"], $scope) : $left;
				}
			case "AssignmentExpression":
				return $this->evaluateAssignment($node, $scope);
			case "UnaryExpression":
				return $this->evaluateUnary($node, $scope);
			case "UpdateExpression":
				return $this->evaluateUpdate($node, $scope);
			case "ConditionalExpression":
				return $this->toBoolean($this->evaluate($node["test"], $scope)) ? $this->evaluate($node["consequent"], $scope) : $this->evaluate($node["alternate"], $scope);
			case "ArrayExpression":
				$items = [];
				foreach($node["elements"] as $element){
					if($element === null){
						$items[] = null;
					}elseif($element["type"] === "SpreadElement"){
						foreach($this->iterableToList($this->evaluate($element["argument"], $scope)) as $value){
							$items[] = $value;
						}
					}else{
						$items[] = $this->evaluate($element, $scope);
					}
				}
				return $this->newArray($items);
			case "ObjectExpression":
				return $this->evaluateObject($node, $scope);
			case "FunctionExpression":
				$fn = $node["fn"];
				if($fn["id"] !== null){
					$functionScope = new Scope($scope);
					$function = $this->createFunction($fn, $functionScope);
					$functionScope->vars[$fn["id"]] = $function;
					$functionScope->consts[$fn["id"]] = true;
					return $function;
				}
				return $this->createFunction($fn, $scope, "");
			case "ArrowFunctionExpression":
				return $this->createFunction($node["fn"], $scope, "");
			case "ClassExpression":
				return $this->evaluateClass($node["class"], $scope, null);
			case "NewExpression":
				$callee = $this->evaluate($node["callee"], $scope);
				$args = $this->evaluateArguments($node["arguments"], $scope);
				$this->line = $node["line"];
				if(!$this->isConstructor($callee)){
					$this->throwError("TypeError", $this->describeNode($node["callee"]) . " is not a constructor");
				}
				return $this->construct($callee, $args);
			case "SequenceExpression":
				$value = null;
				foreach($node["expressions"] as $expression){
					$value = $this->evaluate($expression, $scope);
				}
				return $value;
			case "ChainExpression":
				$value = $this->evaluate($node["expression"], $scope);
				return $value === self::$short ? null : $value;
			case "AwaitExpression":
				return $this->await($this->evaluate($node["argument"], $scope));
			case "YieldExpression":
				if($node["delegate"]){
					return $this->yieldDelegate($this->evaluate($node["argument"], $scope));
				}
				return $this->yieldValue($node["argument"] === null ? null : $this->evaluate($node["argument"], $scope));
			case "TaggedTemplateExpression":
				return $this->evaluateTaggedTemplate($node, $scope);
			case "RegExpLiteral":
				return $this->strings->createRegExp($node["pattern"], $node["flags"]);
			case "NewTarget":
				$functionScope = $this->functionScope($scope);
				return $functionScope?->vars["%nt"] ?? null;
			case "ImportExpression":
				$specifier = $this->toString($this->evaluate($node["source"], $scope));
				if($this->dynamicImport === null){
					$this->throwError("Error", "Dynamic import is not available");
				}
				return ($this->dynamicImport)($this->file, $specifier);
			case "ImportMeta":
				$meta = $this->newObject();
				$meta->props["url"] = "file://" . $this->file;
				return $meta;
			case "PrivateIn":
				$object = $this->evaluate($node["right"], $scope);
				if(!$object instanceof JsObject){
					$this->throwError("TypeError", "Cannot use 'in' operator to search for a private field in a non-object");
				}
				return $this->hasOwnProperty($object, $node["name"]);
			case "Super":
				$this->throwError("SyntaxError", "'super' keyword unexpected here");
		}
		$this->throwError("SyntaxError", "Unsupported expression " . $node["type"]);
	}

	/**
	 * @param array<string, mixed> $node
	 */
	private function evaluateMember(array $node, Scope $scope) : mixed{
		if($node["object"]["type"] === "Super"){
			return $this->superGet($this->memberKey($node, $scope), $scope);
		}
		$object = $this->evaluate($node["object"], $scope);
		if($object === self::$short){
			return $object;
		}
		if($node["optional"] && ($object === null || $object instanceof JsNull)){
			return self::$short;
		}
		if($node["computed"]){
			$keyValue = $this->evaluate($node["property"], $scope);
			if($object instanceof JsArray && is_int($keyValue) && $keyValue >= 0){
				if($keyValue < count($object->items)){
					return $object->items[$keyValue];
				}
				return $this->getFrom($object, $keyValue, $object);
			}
			$key = $this->toPropertyKey($keyValue);
		}else{
			$key = $node["property"]["value"];
		}
		if($object instanceof JsObject && is_string($key)){
			return $this->getFrom($object, $key, $object);
		}
		if($object === null || $object instanceof JsNull){
			$this->line = $node["line"] ?? $this->line;
			$this->throwError("TypeError", "Cannot read properties of " . ($object === null ? "undefined" : "null") . " (reading '" . ($key instanceof JsSymbol ? "Symbol(" . $key->description . ")" : $key) . "')");
		}
		return $this->get($object, $key);
	}

	/**
	 * @param array<string, mixed> $node
	 */
	private function evaluateCall(array $node, Scope $scope) : mixed{
		$callee = $node["callee"];
		if($callee["type"] === "Super"){
			return $this->superCall($node, $scope);
		}
		if($callee["type"] === "MemberExpression"){
			if($callee["object"]["type"] === "Super"){
				$thisValue = $this->lookupThis($scope);
				$function = $this->superGet($this->memberKey($callee, $scope), $scope);
			}else{
				$thisValue = $this->evaluate($callee["object"], $scope);
				if($thisValue === self::$short){
					return $thisValue;
				}
				if($callee["optional"] && ($thisValue === null || $thisValue instanceof JsNull)){
					return self::$short;
				}
				$key = $this->memberKey($callee, $scope);
				if($thisValue === null || $thisValue instanceof JsNull){
					$this->line = $node["line"];
					$this->throwError("TypeError", "Cannot read properties of " . ($thisValue === null ? "undefined" : "null") . " (reading '" . ($key instanceof JsSymbol ? "Symbol(" . $key->description . ")" : $key) . "')");
				}
				$function = $this->get($thisValue, $key);
			}
		}else{
			$thisValue = null;
			$function = $this->evaluate($callee, $scope);
			if($function === self::$short){
				return $function;
			}
		}
		if($node["optional"] && ($function === null || $function instanceof JsNull)){
			return self::$short;
		}
		$args = $this->evaluateArguments($node["arguments"], $scope);
		$this->line = $node["line"];
		if(!$function instanceof JsCallable){
			$this->throwError("TypeError", $this->describeNode($callee) . " is not a function");
		}
		return $this->call($function, $thisValue, $args);
	}

	/**
	 * @param array<string, mixed> $node
	 */
	private function evaluateObject(array $node, Scope $scope) : JsObject{
		$object = new JsObject($this->objectPrototype);
		foreach($node["properties"] as $property){
			if($property["kind"] === "spread"){
				$this->copyDataProperties($object, $this->evaluate($property["value"], $scope));
				continue;
			}
			$key = $property["computed"] ? $this->toPropertyKey($this->evaluate($property["key"], $scope)) : $this->literalKey($property["key"]);
			$name = $key instanceof JsSymbol ? "[" . $key->description . "]" : $key;
			if($property["kind"] === "get" || $property["kind"] === "set"){
				$function = $this->createFunction($property["value"]["fn"], $scope, $property["kind"] . " " . $name, $object);
				if($key instanceof JsSymbol){
					$this->throwError("SyntaxError", "Symbol accessors are not supported");
				}
				unset($object->props[$key]);
				$pair = $object->accessors[$key] ?? [null, null];
				$pair[$property["kind"] === "get" ? 0 : 1] = $function;
				$object->accessors[$key] = $pair;
				continue;
			}
			if(!empty($property["method"])){
				$value = $this->createFunction($property["value"]["fn"], $scope, $name, $object);
			}else{
				$value = $this->evaluateNamed($property["value"], $scope, $name);
			}
			if($key === "__proto__" && !$property["computed"] && empty($property["shorthand"]) && empty($property["method"])){
				if($value instanceof JsObject){
					$object->proto = $value;
				}elseif($value instanceof JsNull){
					$object->proto = null;
				}
				continue;
			}
			if($key instanceof JsSymbol){
				$object->symbols[spl_object_id($key)] = [$key, $value];
			}else{
				unset($object->accessors[$key]);
				$object->props[$key] = $value;
			}
		}
		return $object;
	}

	/**
	 * @param array<string, mixed> $node
	 */
	private function evaluateTaggedTemplate(array $node, Scope $scope) : mixed{
		$quasi = $node["quasi"];
		$strings = $this->newArray($quasi["cooked"]);
		$this->defineHidden($strings, "raw", $this->newArray($quasi["raw"]));
		$args = [$strings];
		foreach($quasi["expressions"] as $expression){
			$args[] = $this->evaluate($expression, $scope);
		}
		$tag = $node["tag"];
		$thisValue = null;
		if($tag["type"] === "MemberExpression" && $tag["object"]["type"] !== "Super"){
			$thisValue = $this->evaluate($tag["object"], $scope);
			$function = $this->get($thisValue, $this->memberKey($tag, $scope));
		}else{
			$function = $this->evaluate($tag, $scope);
		}
		return $this->call($function, $thisValue, $args);
	}

	/**
	 * @param array<string, mixed> $node
	 */
	private function evaluateAssignment(array $node, Scope $scope) : mixed{
		$operator = $node["operator"];
		$left = $node["left"];
		if($operator === "="){
			if($left["type"] === "Identifier"){
				$value = $this->evaluateNamed($node["right"], $scope, $left["name"]);
				$this->assignVariable($left["name"], $value, $scope);
				return $value;
			}
			if($left["type"] === "MemberExpression"){
				if($left["object"]["type"] === "Super"){
					$key = $this->memberKey($left, $scope);
					$value = $this->evaluate($node["right"], $scope);
					$this->set($this->lookupThis($scope), $key, $value);
					return $value;
				}
				$object = $this->evaluate($left["object"], $scope);
				if($left["computed"]){
					$keyValue = $this->evaluate($left["property"], $scope);
					$value = $this->evaluate($node["right"], $scope);
					if($object instanceof JsArray && is_int($keyValue) && $keyValue >= 0 && !$object->frozen && $keyValue < count($object->items)){
						$object->items[$keyValue] = $value;
						return $value;
					}
					$this->set($object, $this->toPropertyKey($keyValue), $value);
					return $value;
				}
				$value = $this->evaluate($node["right"], $scope);
				$this->line = $node["line"];
				$this->set($object, $left["property"]["value"], $value);
				return $value;
			}
			$value = $this->evaluate($node["right"], $scope);
			$this->bindPattern($left, $value, $scope, null);
			return $value;
		}
		if($left["type"] === "Identifier"){
			$current = $this->lookup($left["name"], $scope);
			$value = $this->compoundValue($operator, $current, $node["right"], $scope, $left["name"]);
			if($value === self::$short){
				return $current;
			}
			$this->assignVariable($left["name"], $value, $scope);
			return $value;
		}
		if($left["object"]["type"] === "Super"){
			$this->throwError("SyntaxError", "Compound assignment to super properties is not supported");
		}
		$object = $this->evaluate($left["object"], $scope);
		$key = $this->memberKey($left, $scope);
		$current = $this->get($object, $key);
		$value = $this->compoundValue($operator, $current, $node["right"], $scope, "");
		if($value === self::$short){
			return $current;
		}
		$this->set($object, $key, $value);
		return $value;
	}

	/**
	 * Computes the value of a compound assignment, or returns the short
	 * marker when a logical assignment does not assign.
	 *
	 * @param array<string, mixed> $right
	 */
	private function compoundValue(string $operator, mixed $current, array $right, Scope $scope, string $name) : mixed{
		switch($operator){
			case "&&=":
				return $this->toBoolean($current) ? $this->evaluateNamed($right, $scope, $name) : self::$short;
			case "||=":
				return $this->toBoolean($current) ? self::$short : $this->evaluateNamed($right, $scope, $name);
			case "??=":
				return ($current === null || $current instanceof JsNull) ? $this->evaluateNamed($right, $scope, $name) : self::$short;
		}
		return $this->binary(substr($operator, 0, -1), $current, $this->evaluate($right, $scope));
	}

	/**
	 * @param array<string, mixed> $node
	 */
	private function evaluateUnary(array $node, Scope $scope) : mixed{
		$operator = $node["operator"];
		$argument = $node["argument"];
		switch($operator){
			case "typeof":
				if($argument["type"] === "Identifier" && !$this->hasBinding($argument["name"], $scope)){
					return "undefined";
				}
				return $this->typeOf($this->evaluate($argument, $scope));
			case "delete":
				if($argument["type"] === "MemberExpression"){
					$object = $this->evaluate($argument["object"], $scope);
					$key = $this->memberKey($argument, $scope);
					if($object instanceof JsObject){
						return $this->deleteProperty($object, $key);
					}
					return true;
				}
				if($argument["type"] === "ChainExpression"){
					return true;
				}
				$this->evaluate($argument, $scope);
				return true;
			case "void":
				$this->evaluate($argument, $scope);
				return null;
			case "!":
				return !$this->toBoolean($this->evaluate($argument, $scope));
			case "-":
				$value = $this->evaluate($argument, $scope);
				if(is_int($value) && $value !== 0){
					return -$value;
				}
				if($value === 0){
					return -0.0;
				}
				return -$this->toNumber($value);
			case "+":
				return $this->toNumber($this->evaluate($argument, $scope));
			case "~":
				return self::wrapInt32(~$this->toInt32($this->evaluate($argument, $scope)));
		}
		$this->throwError("SyntaxError", "Unknown unary operator " . $operator);
	}

	/**
	 * @param array<string, mixed> $node
	 */
	private function evaluateUpdate(array $node, Scope $scope) : mixed{
		$argument = $node["argument"];
		$delta = $node["operator"] === "++" ? 1 : -1;
		if($argument["type"] === "Identifier"){
			$old = $this->toNumber($this->lookup($argument["name"], $scope));
			$new = $old + $delta;
			$this->assignVariable($argument["name"], $new, $scope);
			return $node["prefix"] ? $new : $old;
		}
		$object = $this->evaluate($argument["object"], $scope);
		$key = $this->memberKey($argument, $scope);
		$old = $this->toNumber($this->get($object, $key));
		$new = $old + $delta;
		$this->set($object, $key, $new);
		return $node["prefix"] ? $new : $old;
	}

	public function binary(string $operator, mixed $a, mixed $b) : mixed{
		switch($operator){
			case "+":
				if(is_int($a) && is_int($b)){
					return $a + $b;
				}
				if((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))){
					return $a + $b;
				}
				if(is_string($a) && is_string($b)){
					return $a . $b;
				}
				$a = $this->toPrimitive($a);
				$b = $this->toPrimitive($b);
				if(is_string($a) || is_string($b)){
					return $this->toString($a) . $this->toString($b);
				}
				return $this->toNumber($a) + $this->toNumber($b);
			case "-":
				return $this->toNumber($a) - $this->toNumber($b);
			case "*":
				return $this->toNumber($a) * $this->toNumber($b);
			case "/":
				$x = $this->toNumber($a);
				$y = $this->toNumber($b);
				if(is_int($x) && is_int($y) && $y !== 0){
					return $x % $y === 0 ? intdiv($x, $y) : $x / $y;
				}
				return fdiv((float) $x, (float) $y);
			case "%":
				$x = $this->toNumber($a);
				$y = $this->toNumber($b);
				if(is_int($x) && is_int($y) && $y !== 0 && !($x === PHP_INT_MIN && $y === -1)){
					return $x % $y;
				}
				if(is_float($y) && is_infinite($y) && (is_int($x) || is_finite($x))){
					return $x;
				}
				return fmod((float) $x, (float) $y);
			case "**":
				$x = $this->toNumber($a);
				$y = $this->toNumber($b);
				if(is_float($y) && is_nan($y)){
					return NAN;
				}
				if(is_int($x) && is_int($y) && $x === 0 && $y < 0){
					return INF;
				}
				return pow($x, $y);
			case "==":
				return $this->looseEquals($a, $b);
			case "!=":
				return !$this->looseEquals($a, $b);
			case "===":
				return $this->strictEquals($a, $b);
			case "!==":
				return !$this->strictEquals($a, $b);
			case "<":
				return $this->compare($a, $b, false) === 1;
			case ">":
				return $this->compare($b, $a, true) === 1;
			case "<=":
				return $this->compare($b, $a, true) === 0;
			case ">=":
				return $this->compare($a, $b, false) === 0;
			case "&":
				return $this->toInt32($a) & $this->toInt32($b);
			case "|":
				return $this->toInt32($a) | $this->toInt32($b);
			case "^":
				return $this->toInt32($a) ^ $this->toInt32($b);
			case "<<":
				return self::wrapInt32($this->toInt32($a) << ($this->toUint32($b) & 31));
			case ">>":
				return $this->toInt32($a) >> ($this->toUint32($b) & 31);
			case ">>>":
				return $this->toUint32($a) >> ($this->toUint32($b) & 31);
			case "instanceof":
				return $this->instanceOf($a, $b);
			case "in":
				if(!$b instanceof JsObject){
					$this->throwError("TypeError", "Cannot use 'in' operator to search for '" . $this->toStringSafe($a) . "' in " . $this->describeValue($b));
				}
				return $this->hasProperty($b, $this->toPropertyKey($a));
		}
		$this->throwError("SyntaxError", "Unknown operator " . $operator);
	}

	/**
	 * Abstract relational comparison: returns 1 when $a < $b, 0 when not,
	 * and -1 when undefined (NaN). $leftFirst tells which operand is
	 * converted first.
	 */
	private function compare(mixed $a, mixed $b, bool $leftFirst) : int{
		if(is_int($a) && is_int($b)){
			return $a < $b ? 1 : 0;
		}
		if($leftFirst){
			$y = $this->toPrimitive($b, "number");
			$x = $this->toPrimitive($a, "number");
		}else{
			$x = $this->toPrimitive($a, "number");
			$y = $this->toPrimitive($b, "number");
		}
		if(is_string($x) && is_string($y)){
			return strcmp($x, $y) < 0 ? 1 : 0;
		}
		$x = $this->toNumber($x);
		$y = $this->toNumber($y);
		if((is_float($x) && is_nan($x)) || (is_float($y) && is_nan($y))){
			return -1;
		}
		return $x < $y ? 1 : 0;
	}

	public function instanceOf(mixed $value, mixed $constructor) : bool{
		if(!$constructor instanceof JsObject){
			$this->throwError("TypeError", "Right-hand side of 'instanceof' is not callable");
		}
		$hasInstance = $this->getSymbol($constructor, $this->symHasInstance);
		if($hasInstance instanceof JsCallable){
			return $this->toBoolean($this->call($hasInstance, $constructor, [$value]));
		}
		if(!$constructor instanceof JsCallable){
			$this->throwError("TypeError", "Right-hand side of 'instanceof' is not callable");
		}
		if(!$value instanceof JsObject){
			return false;
		}
		$prototype = $this->get($constructor, "prototype");
		if(!$prototype instanceof JsObject){
			return false;
		}
		for($current = $value->proto; $current !== null; $current = $current->proto){
			if($current === $prototype){
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $class
	 */
	public function evaluateClass(array $class, Scope $scope, ?string $name) : JsFunction{
		$name = $class["id"] ?? $name ?? "";
		$parent = null;
		$protoParent = $this->objectPrototype;
		$constructorParent = $this->functionPrototype;
		if($class["superClass"] !== null){
			$parent = $this->evaluate($class["superClass"], $scope);
			if($parent instanceof JsNull){
				$protoParent = null;
			}elseif(!$this->isConstructor($parent)){
				$this->throwError("TypeError", "Class extends value " . $this->describeValue($parent) . " is not a constructor or null");
			}else{
				$parentPrototype = $this->get($parent, "prototype");
				if(!$parentPrototype instanceof JsObject && !$parentPrototype instanceof JsNull){
					$this->throwError("TypeError", "Class extends value does not have valid prototype property");
				}
				$protoParent = $parentPrototype instanceof JsObject ? $parentPrototype : null;
				$constructorParent = $parent;
			}
		}
		$classScope = new Scope($scope);
		if($class["id"] !== null){
			$classScope->vars[$class["id"]] = Tdz::get();
			$classScope->consts[$class["id"]] = true;
		}
		$prototype = new JsObject($protoParent);
		$constructorNode = null;
		foreach($class["members"] as $member){
			if($member["kind"] === "constructor"){
				$constructorNode = $member["value"];
			}
		}
		$constructor = new JsFunction($constructorParent, $constructorNode, $classScope);
		$constructor->isClass = true;
		$constructor->derived = $parent instanceof JsCallable;
		$constructor->home = $prototype;
		$constructor->name = $name;
		$constructor->file = $this->file;
		$constructor->length = $constructorNode === null ? 0 : count($constructorNode["params"]);
		$this->defineHidden($constructor, "prototype", $prototype);
		$constructor->locked["prototype"] = true;
		$this->defineHidden($prototype, "constructor", $constructor);

		$staticInitializers = [];
		foreach($class["members"] as $member){
			$kind = $member["kind"];
			if($kind === "constructor"){
				continue;
			}
			$target = $member["static"] ? $constructor : $prototype;
			if($kind === "block"){
				$staticInitializers[] = ["block", $member["value"]];
				continue;
			}
			$key = $member["computed"] ? $this->toPropertyKey($this->evaluate($member["key"], $classScope)) : $this->literalKey($member["key"]);
			$private = !empty($member["key"]["private"]);
			if($kind === "field"){
				if($member["static"]){
					$staticInitializers[] = ["field", $key, $member["value"], $private];
				}else{
					$constructor->fields[] = [$key, $member["value"], $private];
				}
				continue;
			}
			$label = $key instanceof JsSymbol ? "[" . $key->description . "]" : $key;
			if($kind === "get" || $kind === "set"){
				if($key instanceof JsSymbol){
					$this->throwError("SyntaxError", "Symbol accessors are not supported");
				}
				$function = $this->createFunction($member["value"], $classScope, $kind . " " . $label, $target);
				$pair = $target->accessors[$key] ?? [null, null];
				$pair[$kind === "get" ? 0 : 1] = $function;
				unset($target->props[$key]);
				$target->accessors[$key] = $pair;
				$target->hidden[$key] = true;
				continue;
			}
			$function = $this->createFunction($member["value"], $classScope, $label, $target);
			$this->defineHidden($target, $key, $function);
		}
		if($class["id"] !== null){
			$classScope->vars[$class["id"]] = $constructor;
		}
		foreach($staticInitializers as $initializer){
			$initScope = new Scope($classScope, true);
			$initScope->vars["this"] = $constructor;
			$initScope->vars["%fn"] = $this->staticHome($constructor, $classScope);
			$initScope->vars["%nt"] = null;
			if($initializer[0] === "block"){
				$block = $initializer[1];
				foreach($block["vars"] as $varName){
					$initScope->vars[$varName] = null;
				}
				$this->hoistLexical($block["body"], $initScope);
				foreach($block["body"]["body"] as $statement){
					$this->execute($statement, $initScope);
				}
				continue;
			}
			[, $key, $valueNode, $private] = $initializer;
			$value = $valueNode === null ? null : $this->evaluateNamed($valueNode, $initScope, is_string($key) ? $key : "");
			if($key instanceof JsSymbol){
				$constructor->symbols[spl_object_id($key)] = [$key, $value];
			}elseif($private){
				$this->defineHidden($constructor, $key, $value);
			}else{
				$this->createDataProperty($constructor, $key, $value);
			}
		}
		return $constructor;
	}

	private function staticHome(JsFunction $constructor, Scope $scope) : JsFunction{
		$holder = new JsFunction($this->functionPrototype, null, $scope);
		$holder->home = $constructor;
		return $holder;
	}
}
