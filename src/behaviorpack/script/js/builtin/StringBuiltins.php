<?php

declare(strict_types=1);

namespace behaviorpack\script\js\builtin;

use behaviorpack\script\js\Interpreter;
use behaviorpack\script\js\JsArray;
use behaviorpack\script\js\JsCallable;
use behaviorpack\script\js\JsNull;
use behaviorpack\script\js\JsObject;
use behaviorpack\script\js\JsSymbol;
use behaviorpack\script\js\Lexer;
use function array_slice;
use function count;
use function ctype_digit;
use function ctype_xdigit;
use function explode;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function max;
use function mb_ord;
use function mb_str_split;
use function mb_strlen;
use function mb_strpos;
use function mb_strrpos;
use function mb_strtolower;
use function mb_strtoupper;
use function mb_substr;
use function min;
use function preg_match;
use function preg_replace;
use function spl_object_id;
use function str_contains;
use function str_ends_with;
use function str_repeat;
use function str_starts_with;
use function strcmp;
use function strlen;
use function strpos;
use function substr;
use const PREG_OFFSET_CAPTURE;
use const PREG_UNMATCHED_AS_NULL;

/**
 * String, RegExp and their prototypes. Strings are UTF-8 and indexed by
 * code point. Regular expressions are translated to PCRE.
 */
final class StringBuiltins{

	private JsObject $regexpPrototype;

	private ?JsCallable $regexpConstructor = null;

	public function __construct(
		private Interpreter $js
	){}

	public function install() : void{
		$this->installString();
		$this->installRegExp();
	}

	private function thisString(mixed $thisValue) : string{
		if(is_string($thisValue)){
			return $thisValue;
		}
		if($thisValue instanceof JsObject && is_string($thisValue->host)){
			return $thisValue->host;
		}
		if($thisValue === null || $thisValue instanceof JsNull){
			$this->js->throwError("TypeError", "String.prototype method called on null or undefined");
		}
		return $this->js->toString($thisValue);
	}

	private function length(string $value) : int{
		return mb_strlen($value, "UTF-8");
	}

	private function substring(string $value, int $start, ?int $length = null) : string{
		return mb_substr($value, $start, $length, "UTF-8");
	}

	private function clampIndex(mixed $value, int $length, int $default) : int{
		if($value === null){
			return $default;
		}
		$index = $this->js->toIntegerOrInfinity($value);
		return (int) max(0, min($index, $length));
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

	private function installString() : void{
		$js = $this->js;
		$proto = $js->stringPrototype;
		$proto->host = "";
		$string = $js->makeClass("String", $proto, function(array $args, JsObject $newTarget) use ($js) : JsObject{
			$wrapper = new JsObject($js->prototypeFor($newTarget, $js->stringPrototype));
			$wrapper->className = "String";
			$wrapper->host = count($args) === 0 ? "" : $js->toString($args[0]);
			return $wrapper;
		}, function(mixed $thisValue, array $args) use ($js) : mixed{
			if(count($args) === 0){
				return "";
			}
			if($args[0] instanceof JsSymbol){
				return "Symbol(" . ($args[0]->description ?? "") . ")";
			}
			return $js->toString($args[0]);
		}, 1);
		$js->constructors["String"] = $string;
		$js->defineHidden($js->global, "String", $string);

		$js->defineMethod($string, "fromCharCode", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$result = "";
			foreach($args as $arg){
				$result .= Lexer::codePoint($js->toUint32($arg) & 0xFFFF);
			}
			return $result;
		});
		$js->defineMethod($string, "fromCodePoint", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$result = "";
			foreach($args as $arg){
				$code = $js->toNumber($arg);
				if(!is_int($code) || $code < 0 || $code > 0x10FFFF){
					$js->throwError("RangeError", "Invalid code point " . $js->toStringSafe($arg));
				}
				$result .= Lexer::codePoint($code);
			}
			return $result;
		});
		$js->defineMethod($string, "raw", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$strings = $js->get($args[0] ?? null, "raw");
			$parts = $strings instanceof JsArray ? $strings->items : [];
			$result = "";
			foreach($parts as $index => $part){
				$result .= $js->toString($part);
				if($index + 1 < count($parts) && isset($args[$index + 1])){
					$result .= $js->toString($args[$index + 1]);
				}
			}
			return $result;
		});

		$js->defineMethod($proto, "toString", 0, function(mixed $thisValue, array $args) : mixed{
			return $this->thisString($thisValue);
		});
		$js->defineMethod($proto, "valueOf", 0, function(mixed $thisValue, array $args) : mixed{
			return $this->thisString($thisValue);
		});
		$js->defineMethod($proto, "charAt", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$value = $this->thisString($thisValue);
			$index = (int) $js->toIntegerOrInfinity($args[0] ?? 0);
			return $index < 0 ? "" : $this->substring($value, $index, 1);
		});
		$js->defineMethod($proto, "charCodeAt", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$value = $this->thisString($thisValue);
			$index = (int) $js->toIntegerOrInfinity($args[0] ?? 0);
			$char = $index < 0 ? "" : $this->substring($value, $index, 1);
			if($char === ""){
				return \NAN;
			}
			$code = mb_ord($char, "UTF-8");
			return $code === false ? \NAN : ($code > 0xFFFF ? 0xD800 + (($code - 0x10000) >> 10) : $code);
		});
		$js->defineMethod($proto, "codePointAt", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$value = $this->thisString($thisValue);
			$index = (int) $js->toIntegerOrInfinity($args[0] ?? 0);
			$char = $index < 0 ? "" : $this->substring($value, $index, 1);
			if($char === ""){
				return null;
			}
			$code = mb_ord($char, "UTF-8");
			return $code === false ? null : $code;
		});
		$js->defineMethod($proto, "at", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$value = $this->thisString($thisValue);
			$index = (int) $js->toIntegerOrInfinity($args[0] ?? 0);
			if($index < 0){
				$index += $this->length($value);
			}
			$char = $index < 0 ? "" : $this->substring($value, $index, 1);
			return $char === "" ? null : $char;
		});
		$js->defineMethod($proto, "indexOf", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$value = $this->thisString($thisValue);
			$search = $js->toString($args[0] ?? null);
			$start = $this->clampIndex($args[1] ?? null, $this->length($value), 0);
			if($search === ""){
				return $start;
			}
			$position = mb_strpos($value, $search, $start, "UTF-8");
			return $position === false ? -1 : $position;
		});
		$js->defineMethod($proto, "lastIndexOf", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$value = $this->thisString($thisValue);
			$search = $js->toString($args[0] ?? null);
			$length = $this->length($value);
			$from = ($args[1] ?? null) === null ? $length : $this->clampIndex($args[1], $length, $length);
			$haystack = $this->substring($value, 0, min($length, $from + $this->length($search)));
			if($search === ""){
				return min($from, $length);
			}
			$position = mb_strrpos($haystack, $search, 0, "UTF-8");
			return $position === false ? -1 : $position;
		});
		$js->defineMethod($proto, "includes", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$value = $this->thisString($thisValue);
			if($this->isRegExp($args[0] ?? null)){
				$js->throwError("TypeError", "First argument to String.prototype.includes must not be a regular expression");
			}
			$search = $js->toString($args[0] ?? null);
			$start = $this->clampIndex($args[1] ?? null, $this->length($value), 0);
			return $search === "" || mb_strpos($value, $search, $start, "UTF-8") !== false;
		});
		$js->defineMethod($proto, "startsWith", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$value = $this->thisString($thisValue);
			$search = $js->toString($args[0] ?? null);
			$start = $this->clampIndex($args[1] ?? null, $this->length($value), 0);
			return str_starts_with($start === 0 ? $value : $this->substring($value, $start), $search);
		});
		$js->defineMethod($proto, "endsWith", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$value = $this->thisString($thisValue);
			$search = $js->toString($args[0] ?? null);
			$length = $this->length($value);
			$end = $this->clampIndex($args[1] ?? null, $length, $length);
			return str_ends_with($end === $length ? $value : $this->substring($value, 0, $end), $search);
		});
		$js->defineMethod($proto, "slice", 2, function(mixed $thisValue, array $args) : mixed{
			$value = $this->thisString($thisValue);
			$length = $this->length($value);
			$start = $this->relativeIndex($args[0] ?? null, $length, 0);
			$end = $this->relativeIndex($args[1] ?? null, $length, $length);
			return $end > $start ? $this->substring($value, $start, $end - $start) : "";
		});
		$js->defineMethod($proto, "substring", 2, function(mixed $thisValue, array $args) : mixed{
			$value = $this->thisString($thisValue);
			$length = $this->length($value);
			$start = $this->clampIndex($args[0] ?? null, $length, 0);
			$end = $this->clampIndex($args[1] ?? null, $length, $length);
			if($start > $end){
				[$start, $end] = [$end, $start];
			}
			return $this->substring($value, $start, $end - $start);
		});
		$js->defineMethod($proto, "substr", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			$value = $this->thisString($thisValue);
			$length = $this->length($value);
			$start = $this->relativeIndex($args[0] ?? null, $length, 0);
			$count = ($args[1] ?? null) === null ? $length - $start : (int) max(0, min($js->toIntegerOrInfinity($args[1]), $length - $start));
			return $this->substring($value, $start, $count);
		});
		foreach(["toUpperCase", "toLocaleUpperCase"] as $name){
			$js->defineMethod($proto, $name, 0, function(mixed $thisValue, array $args) : mixed{
				return mb_strtoupper($this->thisString($thisValue), "UTF-8");
			});
		}
		foreach(["toLowerCase", "toLocaleLowerCase"] as $name){
			$js->defineMethod($proto, $name, 0, function(mixed $thisValue, array $args) : mixed{
				return mb_strtolower($this->thisString($thisValue), "UTF-8");
			});
		}
		$js->defineMethod($proto, "trim", 0, function(mixed $thisValue, array $args) : mixed{
			return preg_replace('/^[\s\x{FEFF}\x{A0}]+|[\s\x{FEFF}\x{A0}]+$/u', "", $this->thisString($thisValue)) ?? "";
		});
		foreach(["trimStart", "trimLeft"] as $name){
			$js->defineMethod($proto, $name, 0, function(mixed $thisValue, array $args) : mixed{
				return preg_replace('/^[\s\x{FEFF}\x{A0}]+/u', "", $this->thisString($thisValue)) ?? "";
			});
		}
		foreach(["trimEnd", "trimRight"] as $name){
			$js->defineMethod($proto, $name, 0, function(mixed $thisValue, array $args) : mixed{
				return preg_replace('/[\s\x{FEFF}\x{A0}]+$/u', "", $this->thisString($thisValue)) ?? "";
			});
		}
		foreach(["padStart" => true, "padEnd" => false] as $name => $start){
			$js->defineMethod($proto, $name, 2, function(mixed $thisValue, array $args) use ($js, $start) : mixed{
				$value = $this->thisString($thisValue);
				$target = (int) $js->toIntegerOrInfinity($args[0] ?? 0);
				$filler = ($args[1] ?? null) === null ? " " : $js->toString($args[1]);
				$length = $this->length($value);
				if($target <= $length || $filler === ""){
					return $value;
				}
				$needed = $target - $length;
				$fillLength = $this->length($filler);
				$padding = $this->substring(str_repeat($filler, (int) (($needed / $fillLength) + 1)), 0, $needed);
				return $start ? $padding . $value : $value . $padding;
			});
		}
		$js->defineMethod($proto, "repeat", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$value = $this->thisString($thisValue);
			$count = $js->toIntegerOrInfinity($args[0] ?? 0);
			if($count < 0 || $count > 10_000_000 || strlen($value) * $count > 50_000_000){
				$js->throwError("RangeError", "Invalid count value: " . $js->toStringSafe($args[0] ?? null));
			}
			return str_repeat($value, (int) $count);
		});
		$js->defineMethod($proto, "concat", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$result = $this->thisString($thisValue);
			foreach($args as $arg){
				$result .= $js->toString($arg);
			}
			return $result;
		});
		$js->defineMethod($proto, "localeCompare", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$result = strcmp($this->thisString($thisValue), $js->toString($args[0] ?? null));
			return $result < 0 ? -1 : ($result > 0 ? 1 : 0);
		});
		$js->defineMethod($proto, "normalize", 0, function(mixed $thisValue, array $args) : mixed{
			return $this->thisString($thisValue);
		});
		$js->defineMethod($proto, "isWellFormed", 0, function(mixed $thisValue, array $args) : mixed{
			return true;
		});
		$js->defineMethod($proto, "split", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			$value = $this->thisString($thisValue);
			$separator = $args[0] ?? null;
			$limit = ($args[1] ?? null) === null ? 4294967295 : $js->toUint32($args[1]);
			if($limit === 0){
				return $js->newArray();
			}
			if($separator === null){
				return $js->newArray([$value]);
			}
			if($this->isRegExp($separator)){
				return $js->newArray(array_slice($this->splitRegExp($separator, $value), 0, $limit));
			}
			$separator = $js->toString($separator);
			if($separator === ""){
				$parts = $value === "" ? [] : mb_str_split($value, 1, "UTF-8");
			}else{
				$parts = explode($separator, $value);
			}
			return $js->newArray(array_slice($parts, 0, $limit));
		});
		$js->defineMethod($proto, "replace", 2, function(mixed $thisValue, array $args) : mixed{
			return $this->replace($this->thisString($thisValue), $args[0] ?? null, $args[1] ?? null, false);
		});
		$js->defineMethod($proto, "replaceAll", 2, function(mixed $thisValue, array $args) use ($js) : mixed{
			$pattern = $args[0] ?? null;
			if($this->isRegExp($pattern) && !str_contains($pattern->host["flags"], "g")){
				$js->throwError("TypeError", "replaceAll must be called with a global RegExp");
			}
			return $this->replace($this->thisString($thisValue), $pattern, $args[1] ?? null, true);
		});
		$js->defineMethod($proto, "match", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$value = $this->thisString($thisValue);
			$regexp = $this->toRegExp($args[0] ?? null);
			if(!str_contains($regexp->host["flags"], "g")){
				return $this->exec($regexp, $value);
			}
			$matches = [];
			foreach($this->allMatches($regexp, $value) as $match){
				$matches[] = $match["groups"][0][0];
			}
			$regexp->props["lastIndex"] = 0;
			return count($matches) === 0 ? JsNull::get() : $js->newArray($matches);
		});
		$js->defineMethod($proto, "matchAll", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			$value = $this->thisString($thisValue);
			$regexp = $this->toRegExp($args[0] ?? null, "g");
			if(!str_contains($regexp->host["flags"], "g")){
				$js->throwError("TypeError", "matchAll must be called with a global RegExp");
			}
			$results = [];
			foreach($this->allMatches($regexp, $value) as $match){
				$results[] = $this->matchArray($match, $value);
			}
			$array = $js->newArray($results);
			return $js->call($js->get($array, $js->symIterator), $array, []);
		});
		$js->defineMethod($proto, "search", 1, function(mixed $thisValue, array $args) : mixed{
			$value = $this->thisString($thisValue);
			$regexp = $this->toRegExp($args[0] ?? null);
			$match = $this->matchAt($regexp, $value, 0);
			return $match === null ? -1 : $match["index"];
		});
		$js->defineMethod($proto, $js->symIterator, 0, function(mixed $thisValue, array $args) use ($js) : mixed{
			$value = $this->thisString($thisValue);
			$array = $js->newArray($value === "" ? [] : mb_str_split($value, 1, "UTF-8"));
			return $js->call($js->get($array, "values"), $array, []);
		});
	}

	public function isRegExp(mixed $value) : bool{
		return $value instanceof JsObject && is_array($value->host) && isset($value->host["pcre"]);
	}

	private function toRegExp(mixed $value, string $flags = "") : JsObject{
		if($this->isRegExp($value)){
			return $value;
		}
		$source = $value === null ? "(?:)" : $this->quoteMeta($this->js->toString($value));
		return $this->createRegExp($source, $flags);
	}

	private function quoteMeta(string $value) : string{
		return preg_replace('/[.*+?^${}()|[\]\\\\\/]/', '\\\\$0', $value) ?? $value;
	}

	private function installRegExp() : void{
		$js = $this->js;
		$proto = new JsObject($js->objectPrototype);
		$proto->className = "RegExp";
		$this->regexpPrototype = $proto;
		$construct = function(array $args, JsObject $newTarget) use ($js) : JsObject{
			$pattern = $args[0] ?? null;
			$flags = ($args[1] ?? null) === null ? null : $js->toString($args[1]);
			if($this->isRegExp($pattern)){
				$regexp = $this->createRegExp($pattern->host["source"], $flags ?? $pattern->host["flags"]);
			}else{
				$regexp = $this->createRegExp($pattern === null ? "(?:)" : $js->toString($pattern), $flags ?? "");
			}
			$regexp->proto = $js->prototypeFor($newTarget, $this->regexpPrototype);
			return $regexp;
		};
		$constructor = $js->makeClass("RegExp", $proto, $construct, function(mixed $thisValue, array $args) use ($construct) : mixed{
			return $construct($args, $this->regexpConstructor);
		}, 2);
		$this->regexpConstructor = $constructor;
		$js->constructors["RegExp"] = $constructor;
		$js->defineHidden($js->global, "RegExp", $constructor);

		$flag = function(string $letter) : \Closure{
			return function(mixed $thisValue) use ($letter) : mixed{
				return $this->isRegExp($thisValue) && str_contains($thisValue->host["flags"], $letter);
			};
		};
		foreach(["global" => "g", "ignoreCase" => "i", "multiline" => "m", "sticky" => "y", "unicode" => "u", "dotAll" => "s", "hasIndices" => "d"] as $name => $letter){
			$js->defineGetter($proto, $name, $flag($letter));
		}
		$js->defineGetter($proto, "source", function(mixed $thisValue) : mixed{
			return $this->isRegExp($thisValue) ? $thisValue->host["source"] : "(?:)";
		});
		$js->defineGetter($proto, "flags", function(mixed $thisValue) : mixed{
			return $this->isRegExp($thisValue) ? $thisValue->host["flags"] : "";
		});
		$js->defineMethod($proto, "exec", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			if(!$this->isRegExp($thisValue)){
				$js->throwError("TypeError", "RegExp.prototype.exec called on incompatible receiver");
			}
			return $this->exec($thisValue, $js->toString($args[0] ?? null));
		});
		$js->defineMethod($proto, "test", 1, function(mixed $thisValue, array $args) use ($js) : mixed{
			if(!$this->isRegExp($thisValue)){
				$js->throwError("TypeError", "RegExp.prototype.test called on incompatible receiver");
			}
			return !$this->exec($thisValue, $js->toString($args[0] ?? null)) instanceof JsNull;
		});
		$js->defineMethod($proto, "toString", 0, function(mixed $thisValue, array $args) : mixed{
			if(!$this->isRegExp($thisValue)){
				return "/(?:)/";
			}
			return "/" . $thisValue->host["source"] . "/" . $thisValue->host["flags"];
		});
	}

	public function createRegExp(string $source, string $flags) : JsObject{
		$js = $this->js;
		foreach(mb_str_split($flags, 1, "UTF-8") as $letter){
			if(!str_contains("dgimsuyv", $letter)){
				$js->throwError("SyntaxError", "Invalid regular expression flags '" . $flags . "'");
			}
		}
		$modifiers = "uD";
		foreach(["i", "m", "s"] as $letter){
			if(str_contains($flags, $letter)){
				$modifiers .= $letter;
			}
		}
		$pcre = "/" . $this->translate($source) . "/" . $modifiers;
		if(@preg_match($pcre, "") === false){
			$js->throwError("SyntaxError", "Invalid regular expression: /" . $source . "/");
		}
		$regexp = new JsObject($this->regexpPrototype);
		$regexp->className = "RegExp";
		$regexp->host = ["source" => $source === "" ? "(?:)" : $source, "flags" => $flags, "pcre" => $pcre];
		$regexp->props["lastIndex"] = 0;
		$regexp->hidden["lastIndex"] = true;
		return $regexp;
	}

	/**
	 * Translates the syntax that differs between JavaScript and PCRE.
	 */
	private function translate(string $source) : string{
		$result = "";
		$length = strlen($source);
		$inClass = false;
		for($i = 0; $i < $length; ++$i){
			$char = $source[$i];
			if($char === "\\" && $i + 1 < $length){
				$next = $source[$i + 1];
				if($next === "u" && $i + 2 < $length && $source[$i + 2] === "{"){
					$end = strpos($source, "}", $i + 3);
					if($end !== false){
						$result .= "\\x{" . substr($source, $i + 3, $end - $i - 3) . "}";
						$i = $end;
						continue;
					}
				}
				$hex = substr($source, $i + 2, 4);
				if($next === "u" && strlen($hex) === 4 && ctype_xdigit($hex)){
					$result .= "\\x{" . $hex . "}";
					$i += 5;
					continue;
				}
				$classes = $inClass ? ["d" => "0-9", "w" => "A-Za-z0-9_"] : ["d" => "[0-9]", "D" => "[^0-9]", "w" => "[A-Za-z0-9_]", "W" => "[^A-Za-z0-9_]"];
				if(isset($classes[$next])){
					$result .= $classes[$next];
					$i++;
					continue;
				}
				$result .= $char . $next;
				$i++;
				continue;
			}
			if($char === "[" && !$inClass){
				if(substr($source, $i, 3) === "[^]"){
					$result .= "[\\s\\S]";
					$i += 2;
					continue;
				}
				$inClass = true;
			}elseif($char === "]" && $inClass){
				$inClass = false;
			}elseif($char === "/"){
				$result .= "\\/";
				continue;
			}
			$result .= $char;
		}
		return $result;
	}

	private function charIndex(string $value, int $byteOffset) : int{
		return mb_strlen(substr($value, 0, $byteOffset), "UTF-8");
	}

	private function byteOffset(string $value, int $charIndex) : int{
		return strlen(mb_substr($value, 0, $charIndex, "UTF-8"));
	}

	/**
	 * Matches at or after a byte offset. Returns the groups as returned by
	 * preg_match with offsets, the match index and end in characters.
	 *
	 * @return array{groups: array<int|string, array{0: ?string, 1: int}>, index: int, byteStart: int, byteEnd: int}|null
	 */
	private function matchAt(JsObject $regexp, string $value, int $byteOffset) : ?array{
		if($byteOffset > strlen($value)){
			return null;
		}
		$groups = [];
		$result = @preg_match($regexp->host["pcre"], $value, $groups, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL, $byteOffset);
		if($result !== 1){
			return null;
		}
		$start = $groups[0][1];
		if(str_contains($regexp->host["flags"], "y") && $start !== $byteOffset){
			return null;
		}
		return [
			"groups" => $groups,
			"index" => $this->charIndex($value, $start),
			"byteStart" => $start,
			"byteEnd" => $start + strlen($groups[0][0] ?? "")
		];
	}

	/**
	 * @return list<array{groups: array<int|string, array{0: ?string, 1: int}>, index: int, byteStart: int, byteEnd: int}>
	 */
	private function allMatches(JsObject $regexp, string $value) : array{
		$matches = [];
		$offset = 0;
		$length = strlen($value);
		while($offset <= $length){
			$this->js->tick();
			$match = $this->matchAt($regexp, $value, $offset);
			if($match === null){
				break;
			}
			$matches[] = $match;
			if($match["byteEnd"] === $match["byteStart"]){
				$offset = $match["byteEnd"] + $this->charLength($value, $match["byteEnd"]);
			}else{
				$offset = $match["byteEnd"];
			}
		}
		return $matches;
	}

	private function charLength(string $value, int $byteOffset) : int{
		if($byteOffset >= strlen($value)){
			return 1;
		}
		$byte = \ord($value[$byteOffset]);
		return match(true){
			$byte >= 0xF0 => 4,
			$byte >= 0xE0 => 3,
			$byte >= 0xC0 => 2,
			default => 1
		};
	}

	/**
	 * @param array{groups: array<int|string, array{0: ?string, 1: int}>, index: int, byteStart: int, byteEnd: int} $match
	 */
	private function matchArray(array $match, string $input) : JsArray{
		$js = $this->js;
		$items = [];
		$named = [];
		foreach($match["groups"] as $key => [$text, $unused]){
			if(is_int($key)){
				$items[] = $text;
			}else{
				$named[$key] = $text;
			}
		}
		$array = $js->newArray($items);
		$array->props["index"] = $match["index"];
		$array->props["input"] = $input;
		if(count($named) > 0){
			$groups = new JsObject(null);
			foreach($named as $name => $text){
				$groups->props[$name] = $text;
			}
			$array->props["groups"] = $groups;
		}else{
			$array->props["groups"] = null;
		}
		return $array;
	}

	private function exec(JsObject $regexp, string $value) : mixed{
		$js = $this->js;
		$flags = $regexp->host["flags"];
		$stateful = str_contains($flags, "g") || str_contains($flags, "y");
		$start = 0;
		if($stateful){
			$lastIndex = (int) $js->toIntegerOrInfinity($js->get($regexp, "lastIndex"));
			if($lastIndex > $this->length($value)){
				$js->set($regexp, "lastIndex", 0);
				return JsNull::get();
			}
			$start = $this->byteOffset($value, max(0, $lastIndex));
		}
		$match = $this->matchAt($regexp, $value, $start);
		if($match === null){
			if($stateful){
				$js->set($regexp, "lastIndex", 0);
			}
			return JsNull::get();
		}
		if($stateful){
			$js->set($regexp, "lastIndex", $this->charIndex($value, $match["byteEnd"]));
		}
		return $this->matchArray($match, $value);
	}

	/**
	 * @return list<mixed>
	 */
	private function splitRegExp(JsObject $regexp, string $value) : array{
		if($value === ""){
			return $this->matchAt($regexp, $value, 0) === null ? [""] : [];
		}
		$parts = [];
		$last = 0;
		$offset = 0;
		$length = strlen($value);
		while($offset < $length){
			$match = $this->matchAt($regexp, $value, $offset);
			if($match === null){
				break;
			}
			if($match["byteEnd"] === $match["byteStart"]){
				if($match["byteStart"] >= $length){
					break;
				}
				if($match["byteStart"] === $last){
					$offset = $match["byteStart"] + $this->charLength($value, $match["byteStart"]);
					continue;
				}
			}
			$parts[] = substr($value, $last, $match["byteStart"] - $last);
			foreach($match["groups"] as $key => [$text, $unused]){
				if(is_int($key) && $key > 0){
					$parts[] = $text;
				}
			}
			$last = $match["byteEnd"];
			$offset = $match["byteEnd"] === $match["byteStart"] ? $match["byteEnd"] + $this->charLength($value, $match["byteEnd"]) : $match["byteEnd"];
		}
		$parts[] = substr($value, $last);
		return $parts;
	}

	private function replace(string $value, mixed $pattern, mixed $replacement, bool $all) : string{
		$js = $this->js;
		$matches = [];
		if($this->isRegExp($pattern)){
			$global = str_contains($pattern->host["flags"], "g");
			if($global){
				$matches = $this->allMatches($pattern, $value);
				$pattern->props["lastIndex"] = 0;
			}else{
				$match = $this->matchAt($pattern, $value, 0);
				if($match !== null){
					$matches[] = $match;
				}
			}
		}else{
			$search = $js->toString($pattern);
			$offset = 0;
			while(true){
				$position = $search === "" ? ($offset <= strlen($value) ? $offset : false) : strpos($value, $search, $offset);
				if($position === false){
					break;
				}
				$matches[] = ["groups" => [0 => [$search, $position]], "index" => $this->charIndex($value, $position), "byteStart" => $position, "byteEnd" => $position + strlen($search)];
				if(!$all){
					break;
				}
				$offset = $search === "" ? $position + $this->charLength($value, $position) : $position + strlen($search);
				if($search === "" && $offset > strlen($value)){
					break;
				}
			}
		}
		if(count($matches) === 0){
			return $value;
		}
		$result = "";
		$last = 0;
		foreach($matches as $match){
			$result .= substr($value, $last, $match["byteStart"] - $last);
			$result .= $this->replacement($match, $value, $replacement);
			$last = $match["byteEnd"];
		}
		return $result . substr($value, $last);
	}

	/**
	 * @param array{groups: array<int|string, array{0: ?string, 1: int}>, index: int, byteStart: int, byteEnd: int} $match
	 */
	private function replacement(array $match, string $value, mixed $replacement) : string{
		$js = $this->js;
		$captures = [];
		$named = [];
		foreach($match["groups"] as $key => [$text, $unused]){
			if(is_int($key)){
				$captures[] = $text;
			}else{
				$named[$key] = $text;
			}
		}
		if($replacement instanceof JsCallable){
			$args = $captures;
			$args[] = $match["index"];
			$args[] = $value;
			if(count($named) > 0){
				$groups = new JsObject(null);
				foreach($named as $name => $text){
					$groups->props[$name] = $text;
				}
				$args[] = $groups;
			}
			return $js->toString($js->call($replacement, null, $args));
		}
		$template = $js->toString($replacement);
		if(!str_contains($template, "$")){
			return $template;
		}
		$output = "";
		$length = strlen($template);
		for($i = 0; $i < $length; ++$i){
			$char = $template[$i];
			if($char !== "$" || $i + 1 >= $length){
				$output .= $char;
				continue;
			}
			$next = $template[$i + 1];
			if($next === "$"){
				$output .= "$";
				$i++;
			}elseif($next === "&"){
				$output .= $captures[0] ?? "";
				$i++;
			}elseif($next === "`"){
				$output .= substr($value, 0, $match["byteStart"]);
				$i++;
			}elseif($next === "'"){
				$output .= substr($value, $match["byteEnd"]);
				$i++;
			}elseif(ctype_digit($next)){
				$digits = $next;
				if($i + 2 < $length && ctype_digit($template[$i + 2]) && (int) ($next . $template[$i + 2]) < count($captures)){
					$digits .= $template[$i + 2];
				}
				$index = (int) $digits;
				if($index > 0 && $index < count($captures)){
					$output .= $captures[$index] ?? "";
					$i += strlen($digits);
				}else{
					$output .= $char;
				}
			}elseif($next === "<" && count($named) > 0){
				$end = strpos($template, ">", $i + 2);
				if($end === false){
					$output .= $char;
					continue;
				}
				$output .= $named[substr($template, $i + 2, $end - $i - 2)] ?? "";
				$i = $end;
			}else{
				$output .= $char;
			}
		}
		return $output;
	}
}
