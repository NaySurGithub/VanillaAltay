<?php

declare(strict_types=1);

namespace behaviorpack;

use Closure;
use pocketmine\utils\Utils;
use function abs;
use function atan2;
use function ceil;
use function cos;
use function count;
use function ctype_alpha;
use function ctype_digit;
use function deg2rad;
use function floor;
use function fmod;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function max;
use function min;
use function mt_rand;
use function pow;
use function rad2deg;
use function round;
use function sin;
use function sqrt;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function trim;
use const M_PI;

/**
 * Evaluates the Molang expressions found in behavior pack files: numbers,
 * arithmetic, comparisons, logic, the ternary operator, the math functions,
 * variables and queries. Strings are only kept as function arguments.
 */
final class Molang{

	/** @var list<array{0: string, 1: string}> */
	private array $tokens = [];

	private int $pos = 0;

	/**
	 * @param array<string, float|int|bool> $variables
	 * @param (Closure(string, list<float|string>) : (float|int|bool|string|null))|null $query
	 */
	private function __construct(
		private array $variables,
		private ?Closure $query
	){}

	/**
	 * Evaluates a value that is either a literal or a Molang expression.
	 *
	 * @param array<string, float|int|bool> $variables
	 * @param (Closure(string, list<float|string>) : (float|int|bool|string|null))|null $query
	 */
	public static function evaluate(mixed $value, array $variables = [], ?Closure $query = null) : float{
		if(is_int($value) || is_float($value)){
			return (float) $value;
		}
		if(is_bool($value)){
			return $value ? 1.0 : 0.0;
		}
		if(!is_string($value)){
			return 0.0;
		}
		$trimmed = trim($value);
		if(is_numeric($trimmed)){
			return (float) $trimmed;
		}
		$molang = new self($variables, $query);
		try{
			$molang->tokenize($trimmed);
			$result = $molang->parseStatements();
		}catch(\RuntimeException){
			return 0.0;
		}
		return $result;
	}

	private function tokenize(string $source) : void{
		$length = strlen($source);
		$i = 0;
		while($i < $length){
			$char = $source[$i];
			if($char === " " || $char === "\t" || $char === "\n" || $char === "\r"){
				$i++;
				continue;
			}
			if(ctype_digit($char) || ($char === "." && $i + 1 < $length && ctype_digit($source[$i + 1]))){
				$start = $i;
				while($i < $length && (ctype_digit($source[$i]) || $source[$i] === ".")){
					$i++;
				}
				if($i < $length && ($source[$i] === "f" || $source[$i] === "F")){
					$i++;
					$this->tokens[] = ["num", substr($source, $start, $i - $start - 1)];
				}else{
					$this->tokens[] = ["num", substr($source, $start, $i - $start)];
				}
				continue;
			}
			if(ctype_alpha($char) || $char === "_"){
				$start = $i;
				while($i < $length && (ctype_alpha($source[$i]) || ctype_digit($source[$i]) || $source[$i] === "_" || $source[$i] === "." || $source[$i] === ":")){
					$i++;
				}
				$this->tokens[] = ["id", strtolower(substr($source, $start, $i - $start))];
				continue;
			}
			if($char === "'" || $char === "\""){
				$end = $i + 1;
				while($end < $length && $source[$end] !== $char){
					$end++;
				}
				$this->tokens[] = ["str", substr($source, $i + 1, $end - $i - 1)];
				$i = $end + 1;
				continue;
			}
			$two = substr($source, $i, 2);
			if($two === "==" || $two === "!=" || $two === "<=" || $two === ">=" || $two === "&&" || $two === "||" || $two === "??"){
				$this->tokens[] = ["op", $two];
				$i += 2;
				continue;
			}
			$this->tokens[] = ["op", $char];
			$i++;
		}
	}

	private function peek() : ?string{
		return $this->tokens[$this->pos][1] ?? null;
	}

	private function peekType() : ?string{
		return $this->tokens[$this->pos][0] ?? null;
	}

	private function expect(string $value) : void{
		if($this->peek() !== $value){
			throw new \RuntimeException("Expected " . $value);
		}
		$this->pos++;
	}

	private function parseStatements() : float{
		$result = 0.0;
		while($this->pos < count($this->tokens)){
			if($this->peek() === "return"){
				$this->pos++;
				return $this->parseExpression();
			}
			$result = $this->parseStatement();
			if($this->peek() === ";"){
				$this->pos++;
			}
		}
		return $result;
	}

	private function parseStatement() : float{
		if($this->peekType() === "id" && ($this->tokens[$this->pos + 1][1] ?? null) === "="){
			$name = $this->variableName($this->tokens[$this->pos][1]);
			$this->pos += 2;
			$value = $this->parseExpression();
			if($name !== null){
				$this->variables[$name] = $value;
			}
			return $value;
		}
		return $this->parseExpression();
	}

	private function variableName(string $identifier) : ?string{
		foreach(["variable.", "v.", "temp.", "t."] as $prefix){
			if(str_starts_with($identifier, $prefix)){
				return substr($identifier, strlen($prefix));
			}
		}
		return null;
	}

	private function parseExpression() : float{
		$condition = $this->parseCoalesce();
		if($this->peek() === "?"){
			$this->pos++;
			$yes = $this->parseExpression();
			if($this->peek() === ":"){
				$this->pos++;
				$no = $this->parseExpression();
				return $condition != 0 ? $yes : $no;
			}
			return $condition != 0 ? $yes : 0.0;
		}
		return $condition;
	}

	private function parseCoalesce() : float{
		$left = $this->parseOr();
		while($this->peek() === "??"){
			$this->pos++;
			$right = $this->parseOr();
			$left = $left != 0 ? $left : $right;
		}
		return $left;
	}

	private function parseOr() : float{
		$left = $this->parseAnd();
		while($this->peek() === "||"){
			$this->pos++;
			$right = $this->parseAnd();
			$left = ($left != 0 || $right != 0) ? 1.0 : 0.0;
		}
		return $left;
	}

	private function parseAnd() : float{
		$left = $this->parseComparison();
		while($this->peek() === "&&"){
			$this->pos++;
			$right = $this->parseComparison();
			$left = ($left != 0 && $right != 0) ? 1.0 : 0.0;
		}
		return $left;
	}

	private function parseComparison() : float{
		$left = $this->parseAdditive();
		while(true){
			$op = $this->peek();
			if($op !== "==" && $op !== "!=" && $op !== "<" && $op !== ">" && $op !== "<=" && $op !== ">="){
				return $left;
			}
			$this->pos++;
			$right = $this->parseAdditive();
			$left = match($op){
				"==" => abs($left - $right) < 1e-9,
				"!=" => abs($left - $right) >= 1e-9,
				"<" => $left < $right,
				">" => $left > $right,
				"<=" => $left <= $right,
				">=" => $left >= $right
			} ? 1.0 : 0.0;
		}
	}

	private function parseAdditive() : float{
		$left = $this->parseMultiplicative();
		while($this->peek() === "+" || $this->peek() === "-"){
			$op = $this->peek();
			$this->pos++;
			$right = $this->parseMultiplicative();
			$left = $op === "+" ? $left + $right : $left - $right;
		}
		return $left;
	}

	private function parseMultiplicative() : float{
		$left = $this->parseUnary();
		while($this->peek() === "*" || $this->peek() === "/" || $this->peek() === "%"){
			$op = $this->peek();
			$this->pos++;
			$right = $this->parseUnary();
			if($op === "*"){
				$left *= $right;
			}elseif($op === "/"){
				$left = $right == 0 ? 0.0 : $left / $right;
			}else{
				$left = $right == 0 ? 0.0 : fmod($left, $right);
			}
		}
		return $left;
	}

	private function parseUnary() : float{
		$op = $this->peek();
		if($op === "-"){
			$this->pos++;
			return -$this->parseUnary();
		}
		if($op === "+"){
			$this->pos++;
			return $this->parseUnary();
		}
		if($op === "!"){
			$this->pos++;
			return $this->parseUnary() == 0 ? 1.0 : 0.0;
		}
		return $this->parsePrimary();
	}

	private function parsePrimary() : float{
		$token = $this->tokens[$this->pos] ?? null;
		if($token === null){
			throw new \RuntimeException("Unexpected end");
		}
		$this->pos++;
		[$type, $value] = $token;
		if($type === "num"){
			return (float) $value;
		}
		if($type === "str"){
			return 0.0;
		}
		if($type === "op"){
			if($value === "("){
				$result = $this->parseExpression();
				$this->expect(")");
				return $result;
			}
			if($value === "{"){
				$result = 0.0;
				while($this->peek() !== "}" && $this->peek() !== null){
					$result = $this->parseStatement();
					if($this->peek() === ";"){
						$this->pos++;
					}
				}
				$this->expect("}");
				return $result;
			}
			throw new \RuntimeException("Unexpected " . $value);
		}
		if($value === "true"){
			return 1.0;
		}
		if($value === "false"){
			return 0.0;
		}
		$args = [];
		if($this->peek() === "("){
			$this->pos++;
			while($this->peek() !== ")" && $this->peek() !== null){
				if($this->peekType() === "str"){
					$args[] = (string) $this->tokens[$this->pos][1];
					$this->pos++;
				}else{
					$args[] = $this->parseExpression();
				}
				if($this->peek() === ","){
					$this->pos++;
				}
			}
			$this->expect(")");
		}
		return $this->call($value, $args);
	}

	/**
	 * @param list<float|string> $args
	 */
	private function call(string $name, array $args) : float{
		$n = function(int $index, float $default = 0.0) use ($args) : float{
			$value = $args[$index] ?? $default;
			return is_string($value) ? $default : $value;
		};
		if(str_starts_with($name, "math.")){
			$function = substr($name, 5);
			return match($function){
				"abs" => abs($n(0)),
				"acos" => rad2deg(\acos(max(-1.0, min(1.0, $n(0))))),
				"asin" => rad2deg(\asin(max(-1.0, min(1.0, $n(0))))),
				"atan" => rad2deg(\atan($n(0))),
				"atan2" => rad2deg(atan2($n(0), $n(1))),
				"ceil" => ceil($n(0)),
				"clamp" => max($n(1), min($n(2), $n(0))),
				"cos" => cos(deg2rad($n(0))),
				"sin" => sin(deg2rad($n(0))),
				"exp" => \exp($n(0)),
				"floor" => floor($n(0)),
				"trunc" => $n(0) < 0 ? ceil($n(0)) : floor($n(0)),
				"round" => round($n(0)),
				"ln" => $n(0) > 0 ? \log($n(0)) : 0.0,
				"lerp" => $n(0) + ($n(1) - $n(0)) * $n(2),
				"max" => max($n(0), $n(1)),
				"min" => min($n(0), $n(1)),
				"mod" => $n(1) == 0 ? 0.0 : fmod($n(0), $n(1)),
				"pi" => M_PI,
				"pow" => pow($n(0), $n(1)),
				"sqrt" => $n(0) >= 0 ? sqrt($n(0)) : 0.0,
				"random" => $n(0) + Utils::getRandomFloat() * ($n(1) - $n(0)),
				"random_integer" => (float) mt_rand((int) min($n(0), $n(1)), (int) max($n(0), $n(1))),
				"die_roll" => $this->dieRoll((int) $n(0), $n(1), $n(2), false),
				"die_roll_integer" => $this->dieRoll((int) $n(0), $n(1), $n(2), true),
				"hermite_blend" => 3 * $n(0) ** 2 - 2 * $n(0) ** 3,
				"sign" => $n(0) > 0 ? 1.0 : ($n(0) < 0 ? -1.0 : 0.0),
				default => 0.0
			};
		}
		$variable = $this->variableName($name);
		if($variable !== null){
			$value = $this->variables[$variable] ?? 0.0;
			return is_bool($value) ? ($value ? 1.0 : 0.0) : (float) $value;
		}
		foreach(["query.", "q."] as $prefix){
			if(str_starts_with($name, $prefix) && $this->query !== null){
				$result = ($this->query)(substr($name, strlen($prefix)), $args);
				if(is_bool($result)){
					return $result ? 1.0 : 0.0;
				}
				return is_int($result) || is_float($result) ? (float) $result : 0.0;
			}
		}
		return 0.0;
	}

	private function dieRoll(int $count, float $low, float $high, bool $integer) : float{
		$total = 0.0;
		for($i = 0; $i < max(0, $count); $i++){
			$total += $integer ? mt_rand((int) $low, (int) $high) : $low + Utils::getRandomFloat() * ($high - $low);
		}
		return $total;
	}
}
