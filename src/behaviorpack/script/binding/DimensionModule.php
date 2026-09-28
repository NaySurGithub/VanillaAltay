<?php

declare(strict_types=1);

namespace behaviorpack\script\binding;

use behaviorpack\script\js\Interpreter;
use behaviorpack\script\js\JsArray;
use behaviorpack\script\js\JsNull;
use behaviorpack\script\js\JsObject;
use behaviorpack\script\ScriptException;
use behaviorpack\script\ScriptRuntime;
use stdClass;
use function abs;
use function array_key_exists;
use function array_values;
use function count;
use function floor;
use function get_object_vars;
use function in_array;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_string;
use function json_encode;
use function max;
use function min;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Dimension block volume queries and fills, weather and the nearest block
 * above or below a location, with the ListBlockVolume class.
 */
final class DimensionModule{

	private const UNLOADED = "UnloadedChunksError: ";

	private Interpreter $js;

	private HostClass $listVolume;

	/** @var array<string, list<string>> */
	private array $tagCache = [];

	public function __construct(
		private ScriptRuntime $runtime,
		private ClassFactory $f,
		private ServerModule $s
	){
		$this->js = $runtime->js;
	}

	public function define() : void{
		$this->defineListVolume();
		$this->defineDimension();
		if(!array_key_exists("WeatherType", $this->s->exports())){
			$this->s->export("WeatherType", $this->f->enum("WeatherType", ["Clear" => "Clear", "Rain" => "Rain", "Thunder" => "Thunder"]));
		}
	}

	private function key(int $x, int $y, int $z) : string{
		return $x . ":" . $y . ":" . $z;
	}

	/**
	 * @return array{0: int, 1: int, 2: int}
	 */
	private function location(mixed $value) : array{
		$vector = $this->s->vector($value);
		return [(int) floor($vector["x"]), (int) floor($vector["y"]), (int) floor($vector["z"])];
	}

	/**
	 * @return array<string, array{0: int, 1: int, 2: int}>
	 */
	private function locationList(mixed $value) : array{
		$result = [];
		if($value instanceof JsArray){
			foreach($value->items as $item){
				$location = $this->location($item);
				$result[$this->key(...$location)] = $location;
			}
		}elseif($value instanceof JsObject){
			$location = $this->location($value);
			$result[$this->key(...$location)] = $location;
		}
		return $result;
	}

	/**
	 * @param list<mixed> $locations
	 */
	private function listObject(array $locations) : JsObject{
		$stored = [];
		foreach($locations as $location){
			if(is_array($location) && isset($location[0], $location[1], $location[2])){
				$stored[$this->key((int) $location[0], (int) $location[1], (int) $location[2])] = [(int) $location[0], (int) $location[1], (int) $location[2]];
			}
		}
		return $this->f->instance($this->listVolume, ["kind" => "listvolume", "locations" => $stored]);
	}

	/**
	 * @return array{0: array{x: int, y: int, z: int}, 1: array{x: int, y: int, z: int}}|null
	 */
	private function listBounds(array $locations) : ?array{
		if(count($locations) === 0){
			return null;
		}
		$min = null;
		$max = null;
		foreach($locations as [$x, $y, $z]){
			if($min === null){
				$min = ["x" => $x, "y" => $y, "z" => $z];
				$max = $min;
				continue;
			}
			$min = ["x" => min($min["x"], $x), "y" => min($min["y"], $y), "z" => min($min["z"], $z)];
			$max = ["x" => max($max["x"], $x), "y" => max($max["y"], $y), "z" => max($max["z"], $z)];
		}
		return [$min, $max];
	}

	private function defineListVolume() : void{
		$f = $this->f;
		$js = $this->js;
		$this->listVolume = $f->define("ListBlockVolume", null, function(array $args, JsObject $newTarget) use ($js) : JsObject{
			$volume = $this->f->instance($this->listVolume, ["kind" => "listvolume", "locations" => $this->locationList($args[0] ?? null)]);
			$volume->proto = $js->prototypeFor($newTarget, $this->listVolume->prototype);
			return $volume;
		}, 1);
		$volume = $this->listVolume;
		$locations = function(mixed $thisValue) : array{
			return $this->f->host($thisValue, "listvolume")["locations"];
		};
		$f->method($volume, "add", function(mixed $thisValue, array $args) use ($locations) : mixed{
			$current = $locations($thisValue);
			foreach($this->locationList($args[0] ?? null) as $key => $location){
				$current[$key] = $location;
			}
			$thisValue->host["locations"] = $current;
			return null;
		}, 1);
		$f->method($volume, "remove", function(mixed $thisValue, array $args) use ($locations) : mixed{
			$current = $locations($thisValue);
			foreach($this->locationList($args[0] ?? null) as $key => $location){
				unset($current[$key]);
			}
			$thisValue->host["locations"] = $current;
			return null;
		}, 1);
		$f->method($volume, "getCapacity", function(mixed $thisValue, array $args) use ($locations) : mixed{
			return count($locations($thisValue));
		});
		$f->method($volume, "getMin", function(mixed $thisValue, array $args) use ($locations) : mixed{
			$bounds = $this->listBounds($locations($thisValue));
			return $this->s->toJs($bounds === null ? ["x" => 0, "y" => 0, "z" => 0] : $bounds[0]);
		});
		$f->method($volume, "getMax", function(mixed $thisValue, array $args) use ($locations) : mixed{
			$bounds = $this->listBounds($locations($thisValue));
			return $this->s->toJs($bounds === null ? ["x" => 0, "y" => 0, "z" => 0] : $bounds[1]);
		});
		$f->method($volume, "getSpan", function(mixed $thisValue, array $args) use ($locations) : mixed{
			$bounds = $this->listBounds($locations($thisValue));
			if($bounds === null){
				return $this->s->toJs(["x" => 0, "y" => 0, "z" => 0]);
			}
			[$min, $max] = $bounds;
			return $this->s->toJs(["x" => $max["x"] - $min["x"] + 1, "y" => $max["y"] - $min["y"] + 1, "z" => $max["z"] - $min["z"] + 1]);
		});
		$f->method($volume, "isInside", function(mixed $thisValue, array $args) use ($locations) : mixed{
			$location = $this->location($args[0] ?? null);
			return isset($locations($thisValue)[$this->key(...$location)]);
		}, 1);
		$f->method($volume, "translate", function(mixed $thisValue, array $args) use ($locations) : mixed{
			$delta = $this->location($args[0] ?? null);
			$moved = [];
			foreach($locations($thisValue) as [$x, $y, $z]){
				$location = [$x + $delta[0], $y + $delta[1], $z + $delta[2]];
				$moved[$this->key(...$location)] = $location;
			}
			$thisValue->host["locations"] = $moved;
			return null;
		}, 1);
		$f->method($volume, "getBlockLocationIterator", function(mixed $thisValue, array $args) use ($locations, $js) : mixed{
			$snapshot = array_values($locations($thisValue));
			$index = 0;
			$iterator = $js->newObject();
			$iterator->className = "BlockLocationIterator";
			$js->defineMethod($iterator, "next", 0, function(mixed $unused, array $none) use (&$index, $snapshot, $js) : mixed{
				$result = $js->newObject();
				if($index >= count($snapshot)){
					$result->props["value"] = null;
					$result->props["done"] = true;
					return $result;
				}
				[$x, $y, $z] = $snapshot[$index++];
				$result->props["value"] = $this->s->toJs(["x" => $x, "y" => $y, "z" => $z]);
				$result->props["done"] = false;
				return $result;
			});
			$js->defineMethod($iterator, "isValid", 0, function(mixed $unused, array $none) : mixed{
				return true;
			});
			$js->defineMethod($iterator, $js->symIterator, 0, function(mixed $self, array $none) : mixed{
				return $self;
			});
			return $iterator;
		});
		$this->s->export("ListBlockVolume", $volume);
	}

	/**
	 * Converts a BlockVolume, a ListBlockVolume or a {from, to} object to the
	 * request encoding.
	 *
	 * @return array<string, mixed>
	 */
	private function volume(mixed $value) : array{
		if(!$value instanceof JsObject){
			$this->js->throwError("TypeError", "Expected a BlockVolumeBase");
		}
		$host = is_array($value->host) ? $value->host : [];
		if(($host["kind"] ?? null) === "volume"){
			return ["from" => $host["from"], "to" => $host["to"]];
		}
		if(($host["kind"] ?? null) === "listvolume"){
			return ["list" => array_values($host["locations"])];
		}
		return ["from" => $this->s->vector($this->js->get($value, "from")), "to" => $this->s->vector($this->js->get($value, "to"))];
	}

	/**
	 * @param array<string, mixed> $volume
	 */
	private function capacity(array $volume) : int{
		if(isset($volume["list"])){
			return count($volume["list"]);
		}
		$span = 1;
		foreach(["x", "y", "z"] as $axis){
			$span *= (int) abs(floor($volume["from"][$axis]) - floor($volume["to"][$axis])) + 1;
		}
		return $span;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function filter(mixed $value) : array{
		if(!$value instanceof JsObject || $value instanceof JsArray){
			return [];
		}
		$result = $this->s->fromJs($value);
		return is_array($result) ? $result : [];
	}

	/**
	 * @param array<string, mixed> $filter
	 */
	private function hasTagFilter(array $filter) : bool{
		return (is_array($filter["includeTags"] ?? null) && count($filter["includeTags"]) > 0) || (is_array($filter["excludeTags"] ?? null) && count($filter["excludeTags"]) > 0);
	}

	/**
	 * @return list<string>
	 */
	private function tags(array $permutation) : array{
		$cacheKey = (string) json_encode($permutation);
		if(isset($this->tagCache[$cacheKey])){
			return $this->tagCache[$cacheKey];
		}
		try{
			$tags = $this->s->raw("tag.block", ["\$p" => $permutation["\$p"], "s" => $permutation["s"] ?? []]);
		}catch(ScriptException){
			$tags = [];
		}
		$result = [];
		if(is_array($tags)){
			foreach($tags as $tag){
				if(is_string($tag)){
					$result[] = $tag;
				}
			}
		}
		return $this->tagCache[$cacheKey] = $result;
	}

	private function namespaced(string $id) : string{
		return ServerModule::namespaced($id);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function states(mixed $states) : array{
		if($states instanceof stdClass){
			return get_object_vars($states);
		}
		return is_array($states) ? $states : [];
	}

	private function permutationMatches(array $permutation, mixed $candidate) : bool{
		if(!is_array($candidate) || !is_string($candidate["\$p"] ?? null) || $this->namespaced($candidate["\$p"]) !== $permutation["\$p"]){
			return false;
		}
		$actual = $this->states($permutation["s"] ?? null);
		foreach($this->states($candidate["s"] ?? null) as $name => $value){
			if(!array_key_exists($name, $actual)){
				return false;
			}
			$other = $actual[$name];
			if(is_numeric($value) && is_numeric($other) && !is_bool($value) && !is_bool($other)){
				if((float) $value !== (float) $other){
					return false;
				}
			}elseif($value !== $other){
				return false;
			}
		}
		return true;
	}

	/**
	 * Evaluates the include part of a filter holding tags, the exclude types
	 * and permutations having been applied by the request.
	 *
	 * @param array<string, mixed> $filter
	 */
	private function tagFilterMatches(array $permutation, array $filter) : bool{
		$tags = $this->tags($permutation);
		foreach(is_array($filter["excludeTags"] ?? null) ? $filter["excludeTags"] : [] as $tag){
			if(is_string($tag) && in_array($tag, $tags, true)){
				return false;
			}
		}
		$includeTypes = is_array($filter["includeTypes"] ?? null) ? $filter["includeTypes"] : [];
		$includePermutations = is_array($filter["includePermutations"] ?? null) ? $filter["includePermutations"] : [];
		$includeTags = is_array($filter["includeTags"] ?? null) ? $filter["includeTags"] : [];
		if(count($includeTypes) === 0 && count($includePermutations) === 0 && count($includeTags) === 0){
			return true;
		}
		foreach($includeTypes as $type){
			if(is_string($type) && $this->namespaced($type) === $permutation["\$p"]){
				return true;
			}
		}
		foreach($includePermutations as $candidate){
			if($this->permutationMatches($permutation, $candidate)){
				return true;
			}
		}
		foreach($includeTags as $tag){
			if(is_string($tag) && in_array($tag, $tags, true)){
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns the matching locations of a volume.
	 *
	 * @param array<string, mixed> $volume
	 * @param array<string, mixed> $filter
	 * @return list<mixed>
	 */
	private function query(string $dimension, array $volume, array $filter, bool $allowUnloaded, ?int $stopAfter) : array{
		if(!$this->hasTagFilter($filter)){
			if($stopAfter !== null){
				return $this->raw("dimx.contains", $dimension, $volume, $filter, $allowUnloaded) === true ? [[0, 0, 0]] : [];
			}
			return $this->raw("dimx.blocks", $dimension, $volume, $filter, $allowUnloaded, false);
		}
		$requestFilter = [
			"excludeTypes" => $filter["excludeTypes"] ?? [],
			"excludePermutations" => $filter["excludePermutations"] ?? []
		];
		$result = [];
		foreach($this->raw("dimx.blocks", $dimension, $volume, $requestFilter, $allowUnloaded, true) as $entry){
			if($this->tagFilterMatches($entry["p"], $filter)){
				$result[] = $entry["l"];
				if($stopAfter !== null && count($result) >= $stopAfter){
					break;
				}
			}
		}
		return $result;
	}

	private function raw(string $method, mixed ...$args) : mixed{
		try{
			return $this->s->raw($method, ...$args);
		}catch(ScriptException $e){
			$message = $e->getMessage();
			if(str_starts_with($message, self::UNLOADED)){
				$this->js->throwError("UnloadedChunksError", substr($message, strlen(self::UNLOADED)));
			}
			$this->js->throwError("Error", $message);
		}
	}

	private function blockValue(mixed $value) : mixed{
		if(is_string($value)){
			return $value;
		}
		if($value instanceof JsObject && is_array($value->host) && in_array($value->host["kind"] ?? null, ["permutation", "type"], true)){
			return $this->s->fromJs($value);
		}
		$this->js->throwError("TypeError", "Expected a BlockPermutation, a BlockType or a block type id");
	}

	/**
	 * @return array<string, mixed>
	 */
	private function options(mixed $value) : array{
		if(!$value instanceof JsObject || $value instanceof JsArray){
			return [];
		}
		$result = $this->s->fromJs($value);
		return is_array($result) ? $result : [];
	}

	private function defineDimension() : void{
		$f = $this->f;
		$dimension = $this->s->dimension;
		$dimensionId = function(mixed $thisValue) : string{
			return $this->f->host($thisValue, "dimension")["id"];
		};
		$f->method($dimension, "fillBlocks", function(mixed $thisValue, array $args) use ($dimensionId) : mixed{
			$id = $dimensionId($thisValue);
			$volume = $this->volume($args[0] ?? null);
			$block = $this->blockValue($args[1] ?? null);
			$options = $this->options($args[2] ?? null);
			$ignoreUnloaded = ($options["ignoreChunkBoundErrors"] ?? false) === true;
			$filter = is_array($options["blockFilter"] ?? null) ? $options["blockFilter"] : [];
			$capacity = $this->capacity($volume);
			if($capacity > 32768){
				$this->js->throwError("Error", "The volume of " . $capacity . " blocks exceeds the maximum of 32768 blocks");
			}
			if($this->hasTagFilter($filter)){
				if(!$ignoreUnloaded){
					$this->raw("dimx.blocks", $id, $volume, ["includeTypes" => ["minecraft:__none__"]], false, false);
				}
				$volume = ["list" => $this->query($id, $volume, $filter, true, null)];
				$filter = [];
			}
			return $this->listObject($this->raw("dimx.fill", $id, $volume, $block, $filter, $ignoreUnloaded));
		}, 3);
		$f->method($dimension, "getBlocks", function(mixed $thisValue, array $args) use ($dimensionId) : mixed{
			$allowUnloaded = ($args[2] ?? null) !== null && !$args[2] instanceof JsNull && $this->js->toBoolean($args[2]);
			return $this->listObject($this->query($dimensionId($thisValue), $this->volume($args[0] ?? null), $this->filter($args[1] ?? null), $allowUnloaded, null));
		}, 3);
		$f->method($dimension, "containsBlock", function(mixed $thisValue, array $args) use ($dimensionId) : mixed{
			$allowUnloaded = ($args[2] ?? null) !== null && !$args[2] instanceof JsNull && $this->js->toBoolean($args[2]);
			return $this->query($dimensionId($thisValue), $this->volume($args[0] ?? null), $this->filter($args[1] ?? null), $allowUnloaded, 1) !== [];
		}, 3);
		$f->method($dimension, "getWeather", function(mixed $thisValue, array $args) use ($dimensionId) : mixed{
			return $this->raw("dimx.weather.get", $dimensionId($thisValue));
		});
		$f->method($dimension, "setWeather", function(mixed $thisValue, array $args) use ($dimensionId) : mixed{
			$duration = $args[1] ?? null;
			$ticks = $duration === null || $duration instanceof JsNull ? null : (int) $this->js->toNumber($duration);
			if($ticks !== null && ($ticks < 1 || $ticks > 1000000)){
				$this->js->throwError("RangeError", "Weather duration must be between 1 and 1000000 ticks");
			}
			$this->raw("dimx.weather.set", $dimensionId($thisValue), $this->js->toString($args[0] ?? ""), $ticks);
			return null;
		}, 2);
		$f->method($dimension, "getBlockAbove", function(mixed $thisValue, array $args) use ($dimensionId) : mixed{
			return $this->s->toJs($this->raw("dimx.near", $dimensionId($thisValue), $this->s->vector($args[0] ?? null), 1, $this->options($args[1] ?? null)));
		}, 2);
		$f->method($dimension, "getBlockBelow", function(mixed $thisValue, array $args) use ($dimensionId) : mixed{
			return $this->s->toJs($this->raw("dimx.near", $dimensionId($thisValue), $this->s->vector($args[0] ?? null), -1, $this->options($args[1] ?? null)));
		}, 2);
	}
}
