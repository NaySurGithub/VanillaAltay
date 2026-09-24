<?php

declare(strict_types=1);

namespace behaviorpack\script\js;

use function chr;
use function ctype_alnum;
use function ctype_digit;
use function ctype_xdigit;
use function hexdec;
use function in_array;
use function mb_chr;
use function octdec;
use function bindec;
use function ord;
use function str_replace;
use function strcspn;
use function strlen;
use function strpos;
use function substr;
use function substr_compare;
use function substr_count;

/**
 * Splits JavaScript source code into tokens. A token is an array with the
 * keys "t" (type: name, num, str, tpl, regex, punc, priv, eof), "v" (value),
 * "l" (line) and "n" (whether a line break precedes it).
 */
final class Lexer{

	private const PUNCTUATORS = [
		">>>=", "...", "===", "!==", "**=", "<<=", ">>=", ">>>", "&&=", "||=", "??=",
		"=>", "==", "!=", "<=", ">=", "&&", "||", "??", "?.", "++", "--", "+=", "-=", "*=", "/=", "%=",
		"&=", "|=", "^=", "<<", ">>", "**"
	];

	private const REGEX_KEYWORDS = ["return", "typeof", "instanceof", "in", "of", "new", "delete", "void", "throw", "case", "do", "else", "yield", "await"];

	private string $source;
	private int $length;
	private int $pos = 0;
	private int $line;

	public function __construct(string $source, private string $file, int $line = 1){
		$this->source = str_replace(["\r\n", "\r"], "\n", $source);
		$this->length = strlen($this->source);
		$this->line = $line;
	}

	/**
	 * @return list<array{t: string, v: mixed, l: int, n: bool}>
	 */
	public function tokenize() : array{
		$tokens = [];
		$previous = null;
		while(true){
			$newline = $this->skipTrivia();
			if($this->pos >= $this->length){
				$tokens[] = ["t" => "eof", "v" => null, "l" => $this->line, "n" => true];
				return $tokens;
			}
			$token = $this->next($previous);
			$token["n"] = $newline;
			$tokens[] = $token;
			$previous = $token;
		}
	}

	private function error(string $message) : SyntaxErrorException{
		return new SyntaxErrorException($message . " (" . $this->file . ":" . $this->line . ")");
	}

	private function skipTrivia() : bool{
		$newline = false;
		while($this->pos < $this->length){
			$char = $this->source[$this->pos];
			if($char === "\n"){
				$newline = true;
				$this->line++;
				$this->pos++;
			}elseif($char === " " || $char === "\t" || $char === "\f" || $char === "\v"){
				$this->pos++;
			}elseif($char === "\xEF" && substr($this->source, $this->pos, 3) === "\xEF\xBB\xBF"){
				$this->pos += 3;
			}elseif($char === "\xC2" && $this->pos + 1 < $this->length && $this->source[$this->pos + 1] === "\xA0"){
				$this->pos += 2;
			}elseif($char === "\xE2" && ($this->peekString(3) === "\xE2\x80\xA8" || $this->peekString(3) === "\xE2\x80\xA9")){
				$newline = true;
				$this->line++;
				$this->pos += 3;
			}elseif($char === "/" && $this->pos + 1 < $this->length && $this->source[$this->pos + 1] === "/"){
				$end = strpos($this->source, "\n", $this->pos);
				$this->pos = $end === false ? $this->length : $end;
			}elseif($char === "/" && $this->pos + 1 < $this->length && $this->source[$this->pos + 1] === "*"){
				$end = strpos($this->source, "*/", $this->pos + 2);
				if($end === false){
					throw $this->error("Unterminated comment");
				}
				$comment = substr($this->source, $this->pos, $end - $this->pos);
				$lines = substr_count($comment, "\n");
				if($lines > 0){
					$newline = true;
					$this->line += $lines;
				}
				$this->pos = $end + 2;
			}elseif($char === "#" && $this->pos === 0 && $this->pos + 1 < $this->length && $this->source[1] === "!"){
				$end = strpos($this->source, "\n");
				$this->pos = $end === false ? $this->length : $end;
			}else{
				break;
			}
		}
		return $newline;
	}

	private function peekString(int $length) : string{
		return substr($this->source, $this->pos, $length);
	}

	/**
	 * @param array<string, mixed>|null $previous
	 * @return array<string, mixed>
	 */
	private function next(?array $previous) : array{
		$char = $this->source[$this->pos];
		$line = $this->line;
		if(self::isIdentifierStart($char)){
			return ["t" => "name", "v" => $this->readIdentifier(), "l" => $line];
		}
		if($char === "#"){
			$this->pos++;
			return ["t" => "priv", "v" => "#" . $this->readIdentifier(), "l" => $line];
		}
		if(ctype_digit($char) || ($char === "." && $this->pos + 1 < $this->length && ctype_digit($this->source[$this->pos + 1]))){
			return ["t" => "num", "v" => $this->readNumber(), "l" => $line];
		}
		if($char === "\"" || $char === "'"){
			return ["t" => "str", "v" => $this->readString($char), "l" => $line];
		}
		if($char === "`"){
			return ["t" => "tpl", "v" => $this->readTemplate(), "l" => $line];
		}
		if($char === "/" && $this->regexAllowed($previous)){
			return ["t" => "regex", "v" => $this->readRegex(), "l" => $line];
		}
		foreach(self::PUNCTUATORS as $punctuator){
			if(substr_compare($this->source, $punctuator, $this->pos, strlen($punctuator)) === 0){
				if($punctuator === "?." && $this->pos + 2 < $this->length && ctype_digit($this->source[$this->pos + 2])){
					continue;
				}
				$this->pos += strlen($punctuator);
				return ["t" => "punc", "v" => $punctuator, "l" => $line];
			}
		}
		if(strpos("{}()[];,<>+-*/%&|^!~?:=.@", $char) !== false){
			$this->pos++;
			return ["t" => "punc", "v" => $char, "l" => $line];
		}
		throw $this->error("Unexpected character '" . $char . "'");
	}

	/**
	 * @param array<string, mixed>|null $previous
	 */
	private function regexAllowed(?array $previous) : bool{
		if($previous === null){
			return true;
		}
		if($previous["t"] === "punc"){
			return !in_array($previous["v"], [")", "]", "}"], true);
		}
		if($previous["t"] === "name"){
			return in_array($previous["v"], self::REGEX_KEYWORDS, true);
		}
		return false;
	}

	public static function isIdentifierStart(string $char) : bool{
		return ($char >= "a" && $char <= "z") || ($char >= "A" && $char <= "Z") || $char === "_" || $char === "$" || ord($char) >= 0x80 || $char === "\\";
	}

	private static function isIdentifierPart(string $char) : bool{
		return ctype_alnum($char) || $char === "_" || $char === "$" || ord($char) >= 0x80;
	}

	private function readIdentifier() : string{
		$start = $this->pos;
		while($this->pos < $this->length && self::isIdentifierPart($this->source[$this->pos])){
			$this->pos++;
		}
		if($this->pos < $this->length && $this->source[$this->pos] === "\\"){
			$name = substr($this->source, $start, $this->pos - $start);
			while($this->pos < $this->length && ($this->source[$this->pos] === "\\" || self::isIdentifierPart($this->source[$this->pos]))){
				if($this->source[$this->pos] === "\\"){
					$this->pos++;
					if(($this->source[$this->pos] ?? "") !== "u"){
						throw $this->error("Invalid escape in identifier");
					}
					$this->pos++;
					$name .= $this->readUnicodeEscape();
				}else{
					$name .= $this->source[$this->pos++];
				}
			}
			return $name;
		}
		return substr($this->source, $start, $this->pos - $start);
	}

	private function readNumber() : int|float{
		$start = $this->pos;
		$char = $this->source[$this->pos];
		if($char === "0" && $this->pos + 1 < $this->length){
			$prefix = $this->source[$this->pos + 1];
			$base = match($prefix){
				"x", "X" => 16,
				"o", "O" => 8,
				"b", "B" => 2,
				default => 0
			};
			if($base !== 0){
				$this->pos += 2;
				$digitsStart = $this->pos;
				while($this->pos < $this->length && (ctype_xdigit($this->source[$this->pos]) || $this->source[$this->pos] === "_")){
					$this->pos++;
				}
				$digits = str_replace("_", "", substr($this->source, $digitsStart, $this->pos - $digitsStart));
				if($this->pos < $this->length && $this->source[$this->pos] === "n"){
					$this->pos++;
				}
				$value = match($base){
					16 => hexdec($digits),
					8 => octdec($digits),
					default => bindec($digits)
				};
				return $value;
			}
		}
		while($this->pos < $this->length && (ctype_digit($this->source[$this->pos]) || $this->source[$this->pos] === "_")){
			$this->pos++;
		}
		$isFloat = false;
		if($this->pos < $this->length && $this->source[$this->pos] === "."){
			$isFloat = true;
			$this->pos++;
			while($this->pos < $this->length && (ctype_digit($this->source[$this->pos]) || $this->source[$this->pos] === "_")){
				$this->pos++;
			}
		}
		if($this->pos < $this->length && ($this->source[$this->pos] === "e" || $this->source[$this->pos] === "E")){
			$next = $this->source[$this->pos + 1] ?? "";
			$after = $this->source[$this->pos + 2] ?? "";
			if(ctype_digit($next) || (($next === "+" || $next === "-") && ctype_digit($after))){
				$isFloat = true;
				$this->pos += 2;
				while($this->pos < $this->length && ctype_digit($this->source[$this->pos])){
					$this->pos++;
				}
			}
		}
		$text = str_replace("_", "", substr($this->source, $start, $this->pos - $start));
		if($this->pos < $this->length && $this->source[$this->pos] === "n"){
			$this->pos++;
		}
		if($this->pos < $this->length && self::isIdentifierStart($this->source[$this->pos])){
			throw $this->error("Invalid number");
		}
		if(!$isFloat && strlen($text) > 1 && $text[0] === "0" && ctype_digit($text)){
			return octdec($text);
		}
		if(!$isFloat && strlen($text) < 19){
			return (int) $text;
		}
		return (float) $text;
	}

	private function readUnicodeEscape() : string{
		if(($this->source[$this->pos] ?? "") === "{"){
			$end = strpos($this->source, "}", $this->pos);
			if($end === false){
				throw $this->error("Invalid unicode escape");
			}
			$code = (int) hexdec(substr($this->source, $this->pos + 1, $end - $this->pos - 1));
			$this->pos = $end + 1;
			return self::codePoint($code);
		}
		$hex = substr($this->source, $this->pos, 4);
		if(strlen($hex) !== 4 || !ctype_xdigit($hex)){
			throw $this->error("Invalid unicode escape");
		}
		$this->pos += 4;
		$code = (int) hexdec($hex);
		if($code >= 0xD800 && $code <= 0xDBFF && substr($this->source, $this->pos, 2) === "\\u"){
			$low = substr($this->source, $this->pos + 2, 4);
			if(strlen($low) === 4 && ctype_xdigit($low)){
				$lowCode = (int) hexdec($low);
				if($lowCode >= 0xDC00 && $lowCode <= 0xDFFF){
					$this->pos += 6;
					return self::codePoint(0x10000 + (($code - 0xD800) << 10) + ($lowCode - 0xDC00));
				}
			}
		}
		return self::codePoint($code);
	}

	public static function codePoint(int $code) : string{
		if($code >= 0xD800 && $code <= 0xDFFF){
			return "\xEF\xBF\xBD";
		}
		$char = mb_chr($code, "UTF-8");
		return $char === false ? "\xEF\xBF\xBD" : $char;
	}

	/**
	 * Reads an escape sequence after a backslash and returns the cooked text,
	 * or null for an invalid escape in a template.
	 */
	private function readEscape(bool $template) : ?string{
		$char = $this->source[$this->pos] ?? "";
		$this->pos++;
		switch($char){
			case "n":
				return "\n";
			case "t":
				return "\t";
			case "r":
				return "\r";
			case "b":
				return "\x08";
			case "f":
				return "\f";
			case "v":
				return "\v";
			case "0":
				if(!ctype_digit($this->source[$this->pos] ?? "")){
					return "\0";
				}
				if($template){
					return null;
				}
				$start = $this->pos - 1;
				while($this->pos < $this->length && $this->pos - $start < 3 && $this->source[$this->pos] >= "0" && $this->source[$this->pos] <= "7"){
					$this->pos++;
				}
				return chr((int) octdec(substr($this->source, $start, $this->pos - $start)) & 0xff);
			case "x":
				$hex = substr($this->source, $this->pos, 2);
				if(strlen($hex) !== 2 || !ctype_xdigit($hex)){
					if($template){
						return null;
					}
					throw $this->error("Invalid hexadecimal escape");
				}
				$this->pos += 2;
				return self::codePoint((int) hexdec($hex));
			case "u":
				return $this->readUnicodeEscape();
			case "\n":
				$this->line++;
				return "";
			case "\xE2":
				$this->pos += 2;
				return "";
			default:
				if($char >= "1" && $char <= "7" && !$template){
					return chr((int) octdec($char));
				}
				return $char;
		}
	}

	private function readString(string $quote) : string{
		$this->pos++;
		$value = "";
		while(true){
			if($this->pos >= $this->length){
				throw $this->error("Unterminated string");
			}
			$char = $this->source[$this->pos];
			if($char === $quote){
				$this->pos++;
				return $value;
			}
			if($char === "\n"){
				throw $this->error("Unterminated string");
			}
			if($char === "\\"){
				$this->pos++;
				$value .= $this->readEscape(false);
				continue;
			}
			$next = strcspn($this->source, $quote . "\\\n", $this->pos);
			$value .= substr($this->source, $this->pos, $next);
			$this->pos += $next;
		}
	}

	/**
	 * @return array{cooked: list<string|null>, raw: list<string>, exprs: list<array{string, int}>}
	 */
	private function readTemplate() : array{
		$this->pos++;
		$cooked = [];
		$raw = [];
		$exprs = [];
		$chunk = "";
		$chunkRaw = "";
		$valid = true;
		while(true){
			if($this->pos >= $this->length){
				throw $this->error("Unterminated template literal");
			}
			$char = $this->source[$this->pos];
			if($char === "`"){
				$this->pos++;
				$cooked[] = $valid ? $chunk : null;
				$raw[] = $chunkRaw;
				return ["cooked" => $cooked, "raw" => $raw, "exprs" => $exprs];
			}
			if($char === "\\"){
				$start = $this->pos;
				$this->pos++;
				$escaped = $this->readEscape(true);
				if($escaped === null){
					$valid = false;
				}else{
					$chunk .= $escaped;
				}
				$chunkRaw .= substr($this->source, $start, $this->pos - $start);
				continue;
			}
			if($char === "$" && ($this->source[$this->pos + 1] ?? "") === "{"){
				$cooked[] = $valid ? $chunk : null;
				$raw[] = $chunkRaw;
				$chunk = "";
				$chunkRaw = "";
				$valid = true;
				$this->pos += 2;
				$line = $this->line;
				$start = $this->pos;
				$this->skipBalanced();
				$exprs[] = [substr($this->source, $start, $this->pos - $start), $line];
				$this->pos++;
				continue;
			}
			if($char === "\n"){
				$this->line++;
			}
			$chunk .= $char;
			$chunkRaw .= $char;
			$this->pos++;
		}
	}

	/**
	 * Moves to the brace closing a template substitution, skipping nested
	 * braces, strings, templates and comments.
	 */
	private function skipBalanced() : void{
		$depth = 0;
		while($this->pos < $this->length){
			$char = $this->source[$this->pos];
			if($char === "}"){
				if($depth === 0){
					return;
				}
				$depth--;
				$this->pos++;
			}elseif($char === "{"){
				$depth++;
				$this->pos++;
			}elseif($char === "\"" || $char === "'"){
				$this->readString($char);
			}elseif($char === "`"){
				$this->readTemplate();
			}elseif($char === "/" && ($this->source[$this->pos + 1] ?? "") === "/"){
				$end = strpos($this->source, "\n", $this->pos);
				$this->pos = $end === false ? $this->length : $end;
			}elseif($char === "/" && ($this->source[$this->pos + 1] ?? "") === "*"){
				$end = strpos($this->source, "*/", $this->pos + 2);
				if($end === false){
					throw $this->error("Unterminated comment");
				}
				$this->line += substr_count(substr($this->source, $this->pos, $end - $this->pos), "\n");
				$this->pos = $end + 2;
			}else{
				if($char === "\n"){
					$this->line++;
				}
				$this->pos++;
			}
		}
		throw $this->error("Unterminated template substitution");
	}

	/**
	 * @return array{pattern: string, flags: string}
	 */
	private function readRegex() : array{
		$this->pos++;
		$pattern = "";
		$inClass = false;
		while(true){
			if($this->pos >= $this->length || $this->source[$this->pos] === "\n"){
				throw $this->error("Unterminated regular expression");
			}
			$char = $this->source[$this->pos];
			if($char === "\\"){
				$pattern .= $char . ($this->source[$this->pos + 1] ?? "");
				$this->pos += 2;
				continue;
			}
			if($char === "["){
				$inClass = true;
			}elseif($char === "]"){
				$inClass = false;
			}elseif($char === "/" && !$inClass){
				$this->pos++;
				break;
			}
			$pattern .= $char;
			$this->pos++;
		}
		$flags = "";
		while($this->pos < $this->length && self::isIdentifierPart($this->source[$this->pos])){
			$flags .= $this->source[$this->pos++];
		}
		return ["pattern" => $pattern, "flags" => $flags];
	}
}
