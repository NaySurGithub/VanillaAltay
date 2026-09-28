<?php

declare(strict_types=1);

namespace behaviorpack\custom\connection;

use Closure;
use function array_key_exists;
use function count;
use function ctype_alpha;
use function ctype_digit;
use function in_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function preg_match;
use function strlen;
use function strtolower;
use function substr;

/**
 * Evaluates the Molang subset used by block permutation conditions:
 * q.block_state() / query.block_property() lookups, literals, comparisons,
 * !, && and || with parentheses. Anything else evaluates to false.
 */
final class StateCondition{

	/** @var array<string, list<array{string, bool|int|float|string}>|null> */
	private static array $tokenCache = [];

	/** @var list<array{string, bool|int|float|string}> */
	private array $tokens;
	private int $position = 0;

	/**
	 * @param list<array{string, bool|int|float|string}> $tokens
	 * @param Closure(string) : (bool|int|string|null) $lookup
	 */
	private function __construct(
		array $tokens,
		private Closure $lookup
	){
		$this->tokens = $tokens;
	}

	/**
	 * @param Closure(string) : (bool|int|string|null) $lookup returns the value of a state by name
	 */
	public static function evaluate(string $condition, Closure $lookup) : bool{
		if(!array_key_exists($condition, self::$tokenCache)){
			self::$tokenCache[$condition] = self::tokenize($condition);
		}
		$tokens = self::$tokenCache[$condition];
		if($tokens === null){
			return false;
		}
		$parser = new self($tokens, $lookup);
		$result = $parser->parseOr();
		if($result === null || $parser->position !== count($tokens)){
			return false;
		}
		return self::truthy($result);
	}

	/**
	 * @return list<array{string, bool|int|float|string}>|null
	 */
	private static function tokenize(string $input) : ?array{
		$tokens = [];
		$length = strlen($input);
		$i = 0;
		while($i < $length){
			$char = $input[$i];
			if($char === " " || $char === "\t" || $char === "\n" || $char === "\r"){
				$i++;
				continue;
			}
			$two = substr($input, $i, 2);
			if(in_array($two, ["&&", "||", "==", "!=", "<=", ">="], true)){
				$tokens[] = ["op", $two];
				$i += 2;
				continue;
			}
			if(in_array($char, ["!", "(", ")", "<", ">", ","], true)){
				$tokens[] = ["op", $char];
				$i++;
				continue;
			}
			if($char === "'" || $char === "\""){
				$end = $i + 1;
				while($end < $length && $input[$end] !== $char){
					$end++;
				}
				if($end >= $length){
					return null;
				}
				$tokens[] = ["string", substr($input, $i + 1, $end - $i - 1)];
				$i = $end + 1;
				continue;
			}
			if(ctype_digit($char) || ($char === "-" && $i + 1 < $length && ctype_digit($input[$i + 1]))){
				if(preg_match('/\G-?\d+(\.\d+)?/', $input, $matches, 0, $i) !== 1){
					return null;
				}
				$number = $matches[0];
				$tokens[] = ["number", isset($matches[1]) ? (float) $number : (int) $number];
				$i += strlen($number);
				continue;
			}
			if(ctype_alpha($char) || $char === "_"){
				if(preg_match('/\G[A-Za-z_][A-Za-z0-9_.]*/', $input, $matches, 0, $i) !== 1){
					return null;
				}
				$tokens[] = ["ident", strtolower($matches[0])];
				$i += strlen($matches[0]);
				continue;
			}
			return null;
		}
		return $tokens;
	}

	private function peek() : ?string{
		$token = $this->tokens[$this->position] ?? null;
		return $token !== null && $token[0] === "op" ? (string) $token[1] : null;
	}

	private function parseOr() : bool|int|float|string|null{
		$left = $this->parseAnd();
		while($left !== null && $this->peek() === "||"){
			$this->position++;
			$right = $this->parseAnd();
			if($right === null){
				return null;
			}
			$left = self::truthy($left) || self::truthy($right);
		}
		return $left;
	}

	private function parseAnd() : bool|int|float|string|null{
		$left = $this->parseComparison();
		while($left !== null && $this->peek() === "&&"){
			$this->position++;
			$right = $this->parseComparison();
			if($right === null){
				return null;
			}
			$left = self::truthy($left) && self::truthy($right);
		}
		return $left;
	}

	private function parseComparison() : bool|int|float|string|null{
		$left = $this->parseUnary();
		$operator = $this->peek();
		if($left === null || !in_array($operator, ["==", "!=", "<", ">", "<=", ">="], true)){
			return $left;
		}
		$this->position++;
		$right = $this->parseUnary();
		if($right === null){
			return null;
		}
		return self::compare($left, $operator, $right);
	}

	private function parseUnary() : bool|int|float|string|null{
		if($this->peek() === "!"){
			$this->position++;
			$value = $this->parseUnary();
			return $value === null ? null : !self::truthy($value);
		}
		return $this->parsePrimary();
	}

	private function parsePrimary() : bool|int|float|string|null{
		$token = $this->tokens[$this->position] ?? null;
		if($token === null){
			return null;
		}
		if($token[0] === "op" && $token[1] === "("){
			$this->position++;
			$value = $this->parseOr();
			if($this->peek() !== ")"){
				return null;
			}
			$this->position++;
			return $value;
		}
		$this->position++;
		if($token[0] === "string" || $token[0] === "number"){
			return $token[1];
		}
		if($token[0] !== "ident"){
			return null;
		}
		$name = (string) $token[1];
		if($name === "true" || $name === "false"){
			return $name === "true";
		}
		if(!in_array($name, ["q.block_state", "query.block_state", "q.block_property", "query.block_property"], true)){
			return null;
		}
		if($this->peek() !== "("){
			return null;
		}
		$this->position++;
		$argument = $this->tokens[$this->position] ?? null;
		if($argument === null || $argument[0] !== "string"){
			return null;
		}
		$this->position++;
		if($this->peek() !== ")"){
			return null;
		}
		$this->position++;
		$value = ($this->lookup)((string) $argument[1]);
		return $value ?? false;
	}

	private static function compare(bool|int|float|string $left, string $operator, bool|int|float|string $right) : bool{
		if(is_string($left) || is_string($right)){
			$equal = (string) self::stringify($left) === (string) self::stringify($right);
			return match($operator){
				"==" => $equal,
				"!=" => !$equal,
				default => false
			};
		}
		$a = self::number($left);
		$b = self::number($right);
		return match($operator){
			"==" => $a === $b,
			"!=" => $a !== $b,
			"<" => $a < $b,
			">" => $a > $b,
			"<=" => $a <= $b,
			">=" => $a >= $b,
			default => false
		};
	}

	private static function stringify(bool|int|float|string $value) : string{
		if(is_bool($value)){
			return $value ? "true" : "false";
		}
		return (string) $value;
	}

	private static function number(bool|int|float|string $value) : float{
		if(is_bool($value)){
			return $value ? 1.0 : 0.0;
		}
		if(is_int($value) || is_float($value)){
			return (float) $value;
		}
		return is_numeric($value) ? (float) $value : 0.0;
	}

	private static function truthy(bool|int|float|string $value) : bool{
		if(is_bool($value)){
			return $value;
		}
		if(is_int($value) || is_float($value)){
			return $value != 0;
		}
		return $value !== "";
	}
}
