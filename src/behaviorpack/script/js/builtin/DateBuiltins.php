<?php

declare(strict_types=1);

namespace behaviorpack\script\js\builtin;

use behaviorpack\script\js\Interpreter;
use behaviorpack\script\js\JsObject;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use function count;
use function floor;
use function fmod;
use function gmdate;
use function is_float;
use function is_int;
use function is_nan;
use function is_string;
use function microtime;
use function preg_match;
use function sprintf;
use function str_pad;
use function strtotime;
use const NAN;

/**
 * A subset of Date: construction, the current time, the UTC and local
 * getters and the ISO string form.
 */
final class DateBuiltins{

	private JsObject $prototype;

	public function __construct(
		private Interpreter $js
	){}

	public static function now() : float{
		return floor(microtime(true) * 1000);
	}

	public function install() : void{
		$js = $this->js;
		$proto = new JsObject($js->objectPrototype);
		$proto->className = "Date";
		$this->prototype = $proto;
		$constructor = $js->makeClass("Date", $proto, function(array $args, JsObject $newTarget) use ($js) : JsObject{
			$date = new JsObject($js->prototypeFor($newTarget, $this->prototype));
			$date->className = "Date";
			$date->host = $this->timeFromArguments($args);
			return $date;
		}, function(mixed $thisValue, array $args) : mixed{
			return $this->format(self::now(), false);
		}, 7);
		$js->constructors["Date"] = $constructor;
		$js->defineHidden($js->global, "Date", $constructor);
		$js->defineMethod($constructor, "now", 0, function(mixed $thisValue, array $args) : mixed{
			return Interpreter::intOrFloat(self::now());
		});
		$js->defineMethod($constructor, "parse", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			return $this->parse($js->toString($args[0] ?? null));
		});
		$js->defineMethod($constructor, "UTC", 7, function(mixed $thisValue, array $args) : mixed{
			return $this->fromComponents($args);
		});

		$getters = [
			"getTime" => fn(float $t) : float => $t,
			"valueOf" => fn(float $t) : float => $t,
			"getFullYear" => fn(float $t) : float => (float) gmdate("Y", (int) floor($t / 1000)),
			"getUTCFullYear" => fn(float $t) : float => (float) gmdate("Y", (int) floor($t / 1000)),
			"getMonth" => fn(float $t) : float => (float) gmdate("n", (int) floor($t / 1000)) - 1,
			"getUTCMonth" => fn(float $t) : float => (float) gmdate("n", (int) floor($t / 1000)) - 1,
			"getDate" => fn(float $t) : float => (float) gmdate("j", (int) floor($t / 1000)),
			"getUTCDate" => fn(float $t) : float => (float) gmdate("j", (int) floor($t / 1000)),
			"getDay" => fn(float $t) : float => (float) gmdate("w", (int) floor($t / 1000)),
			"getUTCDay" => fn(float $t) : float => (float) gmdate("w", (int) floor($t / 1000)),
			"getHours" => fn(float $t) : float => (float) gmdate("G", (int) floor($t / 1000)),
			"getUTCHours" => fn(float $t) : float => (float) gmdate("G", (int) floor($t / 1000)),
			"getMinutes" => fn(float $t) : float => (float) gmdate("i", (int) floor($t / 1000)),
			"getUTCMinutes" => fn(float $t) : float => (float) gmdate("i", (int) floor($t / 1000)),
			"getSeconds" => fn(float $t) : float => (float) gmdate("s", (int) floor($t / 1000)),
			"getUTCSeconds" => fn(float $t) : float => (float) gmdate("s", (int) floor($t / 1000)),
			"getMilliseconds" => fn(float $t) : float => fmod($t, 1000),
			"getUTCMilliseconds" => fn(float $t) : float => fmod($t, 1000),
			"getTimezoneOffset" => fn(float $t) : float => 0.0
		];
		foreach($getters as $name => $getter){
			$js->defineMethod($proto, $name, 0, function(mixed $thisValue, array $args) use ($getter) : mixed{
				$time = $this->thisTime($thisValue);
				if(is_nan($time)){
					return NAN;
				}
				return Interpreter::intOrFloat($getter($time));
			});
		}
		$js->defineMethod($proto, "setTime", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$this->thisTime($thisValue);
			$thisValue->host = (float) $js->toNumber($args[0] ?? null);
			return Interpreter::intOrFloat($thisValue->host);
		});
		foreach(["toISOString", "toJSON"] as $name){
			$js->defineMethod($proto, $name, 0, function(mixed $thisValue, array $args) use ($js) : mixed{
				$time = $this->thisTime($thisValue);
				if(is_nan($time)){
					$js->throwError("RangeError", "Invalid time value");
				}
				return $this->format($time, true);
			});
		}
		foreach(["toString", "toUTCString", "toLocaleString", "toDateString", "toTimeString", "toLocaleDateString", "toLocaleTimeString"] as $name){
			$js->defineMethod($proto, $name, 0, function(mixed $thisValue, array $args) : mixed{
				$time = $this->thisTime($thisValue);
				return is_nan($time) ? "Invalid Date" : $this->format($time, false);
			});
		}
		$js->defineMethod($proto, $js->symToPrimitive, 1, function(mixed $thisValue, array $args) : mixed{
			$time = $this->thisTime($thisValue);
			if(($args[0] ?? null) === "number"){
				return Interpreter::intOrFloat($time);
			}
			return is_nan($time) ? "Invalid Date" : $this->format($time, false);
		});
	}

	private function thisTime(mixed $thisValue) : float{
		if(!$thisValue instanceof JsObject || $thisValue->className !== "Date" || !is_float($thisValue->host)){
			$this->js->throwError("TypeError", "this is not a Date object.");
		}
		return $thisValue->host;
	}

	/**
	 * @param list<mixed> $args
	 */
	private function timeFromArguments(array $args) : float{
		if(count($args) === 0){
			return self::now();
		}
		if(count($args) === 1){
			$value = $args[0];
			if($value instanceof JsObject && $value->className === "Date" && is_float($value->host)){
				return $value->host;
			}
			$primitive = $this->js->toPrimitive($value);
			if(is_string($primitive)){
				return $this->parse($primitive);
			}
			return (float) $this->js->toNumber($primitive);
		}
		return $this->fromComponents($args);
	}

	/**
	 * @param list<mixed> $args
	 */
	private function fromComponents(array $args) : float{
		$values = [];
		foreach([0, 0, 1, 0, 0, 0, 0] as $index => $default){
			$values[] = isset($args[$index]) ? (int) $this->js->toNumber($args[$index]) : $default;
		}
		[$year, $month, $day, $hour, $minute, $second, $millisecond] = $values;
		if($year >= 0 && $year <= 99){
			$year += 1900;
		}
		try{
			$date = (new DateTimeImmutable("now", new DateTimeZone("UTC")))->setDate($year, $month + 1, $day)->setTime($hour, $minute, $second);
			return (float) $date->getTimestamp() * 1000 + $millisecond;
		}catch(Throwable){
			return NAN;
		}
	}

	private function parse(string $text) : float{
		$timestamp = strtotime($text . (self::lacksTimeZone($text) ? " UTC" : ""));
		if($timestamp === false){
			return NAN;
		}
		$milliseconds = 0.0;
		if(preg_match('/\.(\d{1,3})/', $text, $match) === 1){
			$milliseconds = (float) str_pad($match[1], 3, "0");
		}
		return (float) $timestamp * 1000 + $milliseconds;
	}

	private function format(float $time, bool $iso) : string{
		$seconds = (int) floor($time / 1000);
		$milliseconds = (int) fmod($time, 1000);
		if($milliseconds < 0){
			$milliseconds += 1000;
		}
		if($iso){
			return gmdate("Y-m-d\\TH:i:s", $seconds) . sprintf(".%03dZ", $milliseconds);
		}
		return gmdate("D M d Y H:i:s", $seconds) . " GMT+0000 (Coordinated Universal Time)";
	}

	private static function lacksTimeZone(string $text) : bool{
		return preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1 || preg_match('/Z$|[+-]\d{2}:?\d{2}$/', $text) !== 1;
	}
}
