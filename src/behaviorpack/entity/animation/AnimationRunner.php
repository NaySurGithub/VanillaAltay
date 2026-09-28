<?php

declare(strict_types=1);

namespace behaviorpack\entity\animation;

use function array_key_first;
use function count;
use function is_array;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function max;
use function min;
use function preg_match;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function trim;

/**
 * Runs the server-side animations of one host: the scripts of its
 * description (initialize, pre_animation, animate), the animation
 * controllers and the timelines of the behavior pack animations.
 */
final class AnimationRunner{

	private const MAX_DEPTH = 8;

	/** @var array<string, string> */
	private array $controllerStates = [];

	/** @var array<string, float> */
	private array $animationTimes = [];

	/** @var array<string, true> */
	private array $touched = [];

	public function __construct(
		private AnimationHost $host
	){
	}

	public function getHost() : AnimationHost{
		return $this->host;
	}

	/**
	 * @return array<mixed>
	 */
	private function description() : array{
		$description = $this->host->getAnimationDefinition()["description"] ?? null;
		return is_array($description) ? $description : [];
	}

	/**
	 * @return array<mixed>
	 */
	private function scripts() : array{
		$scripts = $this->description()["scripts"] ?? null;
		return is_array($scripts) ? $scripts : [];
	}

	/**
	 * Runs description.scripts.initialize once per host.
	 */
	public function initialize() : void{
		if($this->host->isAnimationInitialized()){
			return;
		}
		$this->host->markAnimationInitialized();
		$initialize = $this->scripts()["initialize"] ?? null;
		$this->runEntries(is_array($initialize) ? $initialize : [$initialize]);
	}

	public function tick(int $tickDiff) : void{
		$scripts = $this->scripts();
		$pre = $scripts["pre_animation"] ?? null;
		foreach(is_array($pre) ? $pre : [$pre] as $expression){
			if(is_string($expression)){
				$this->runMolang($expression);
			}
		}
		$animate = $scripts["animate"] ?? null;
		if(!is_array($animate) || count($animate) === 0){
			return;
		}
		$this->touched = [];
		$this->runList($animate, "", $tickDiff / 20, 0);
		foreach($this->animationTimes as $key => $time){
			if(!isset($this->touched[$key])){
				unset($this->animationTimes[$key]);
			}
		}
	}

	/**
	 * Runs a list of animation names, each a string or a {name: condition} object.
	 *
	 * @param array<mixed> $list
	 */
	private function runList(array $list, string $prefix, float $delta, int $depth) : void{
		foreach($list as $entry){
			if(is_string($entry)){
				$this->runNamed($entry, $prefix, $delta, $depth);
				continue;
			}
			if(!is_array($entry)){
				continue;
			}
			foreach($entry as $name => $condition){
				if(is_string($name) && $this->host->evaluateMolang($condition) != 0){
					$this->runNamed($name, $prefix, $delta, $depth);
				}
			}
		}
	}

	private function resolve(string $name) : string{
		$animations = $this->description()["animations"] ?? null;
		if(is_array($animations) && is_string($animations[$name] ?? null)){
			return $animations[$name];
		}
		return $name;
	}

	private function runNamed(string $name, string $prefix, float $delta, int $depth) : void{
		if($depth > self::MAX_DEPTH){
			return;
		}
		$full = $this->resolve($name);
		$key = $prefix . "|" . $full;
		$controller = AnimationRegistry::controller($full);
		if($controller !== null){
			$this->tickController($key, $controller, $delta, $depth);
			return;
		}
		$animation = AnimationRegistry::animation($full);
		if($animation !== null){
			$this->touched[$key] = true;
			$this->tickAnimation($key, $animation, $delta);
		}
	}

	/**
	 * @param array<mixed> $controller
	 */
	private function tickController(string $key, array $controller, float $delta, int $depth) : void{
		$states = $controller["states"] ?? null;
		if(!is_array($states) || count($states) === 0){
			return;
		}
		if(!isset($this->controllerStates[$key])){
			$initial = $controller["initial_state"] ?? "default";
			if(!is_string($initial) || !is_array($states[$initial] ?? null)){
				$initial = (string) array_key_first($states);
			}
			$this->controllerStates[$key] = $initial;
			$this->runEntries($states[$initial]["on_entry"] ?? null);
		}
		$current = $this->controllerStates[$key];
		$state = $states[$current] ?? null;
		if(!is_array($state)){
			unset($this->controllerStates[$key]);
			return;
		}
		$transitions = $state["transitions"] ?? null;
		if(is_array($transitions)){
			foreach($transitions as $transition){
				if(!is_array($transition)){
					continue;
				}
				foreach($transition as $target => $condition){
					if(!is_string($target) || !is_array($states[$target] ?? null) || $this->host->evaluateMolang($condition) == 0){
						continue;
					}
					$this->runEntries($state["on_exit"] ?? null);
					$this->controllerStates[$key] = $target;
					$this->runEntries($states[$target]["on_entry"] ?? null);
					$current = $target;
					$state = $states[$target];
					break 2;
				}
			}
		}
		$animations = $state["animations"] ?? null;
		if(is_array($animations)){
			$this->runList($animations, $key . "#" . $current, $delta, $depth + 1);
		}
	}

	/**
	 * @param array<mixed> $animation
	 */
	private function tickAnimation(string $key, array $animation, float $delta) : void{
		$timeline = $animation["timeline"] ?? null;
		$timeline = is_array($timeline) ? $timeline : [];
		$length = $this->number($animation["animation_length"] ?? null, -1.0);
		if($length < 0){
			$length = 0.0;
			foreach($timeline as $time => $entries){
				$length = max($length, (float) $time);
			}
		}
		$loop = ($animation["loop"] ?? false) === true;
		if(!isset($this->animationTimes[$key])){
			$this->animationTimes[$key] = 0.0;
			$this->fire($timeline, -1.0, 0.0);
			return;
		}
		$previous = $this->animationTimes[$key];
		$now = $previous + $delta;
		if($loop && $length > 0 && $now >= $length){
			$this->fire($timeline, $previous, $length);
			$now -= $length;
			while($now >= $length){
				$now -= $length;
			}
			$this->fire($timeline, -1.0, $now);
		}elseif($previous < $length || $length <= 0){
			$this->fire($timeline, $previous, $length > 0 ? min($now, $length) : $now);
		}
		$this->animationTimes[$key] = $now;
	}

	/**
	 * Runs the timeline entries whose time is in ]from, to].
	 *
	 * @param array<mixed> $timeline
	 */
	private function fire(array $timeline, float $from, float $to) : void{
		foreach($timeline as $time => $entries){
			if(!is_numeric($time)){
				continue;
			}
			$time = (float) $time;
			if($time > $from && $time <= $to){
				$this->runEntries($entries);
			}
		}
	}

	private function runEntries(mixed $entries) : void{
		if(is_string($entries)){
			$entries = [$entries];
		}
		if(!is_array($entries)){
			return;
		}
		foreach($entries as $entry){
			if(!is_string($entry) || $this->host->isAnimationHostClosed()){
				continue;
			}
			$entry = trim($entry);
			if(str_starts_with($entry, "@s ")){
				$this->host->triggerAnimationEvent(trim(substr($entry, 3)));
			}elseif(str_starts_with($entry, "/")){
				$this->host->runAnimationCommand($entry);
			}elseif($entry !== ""){
				$this->runMolang($entry);
			}
		}
	}

	/**
	 * Runs Molang statements, storing the "v.name = value" assignments in the
	 * host variables.
	 */
	public function runMolang(string $source) : void{
		foreach($this->splitStatements($source) as $statement){
			if(preg_match('/^\s*(?:v|variable)\.([a-zA-Z0-9_]+)\s*=(?!=)(.+)$/s', $statement, $match) === 1){
				$this->host->setMolangVariable(strtolower($match[1]), $this->host->evaluateMolang(trim($match[2])));
			}elseif(trim($statement) !== ""){
				$this->host->evaluateMolang($statement);
			}
		}
	}

	/**
	 * @return list<string>
	 */
	private function splitStatements(string $source) : array{
		$statements = [];
		$depth = 0;
		$current = "";
		$length = strlen($source);
		for($i = 0; $i < $length; $i++){
			$char = $source[$i];
			if($char === "{" || $char === "("){
				$depth++;
			}elseif($char === "}" || $char === ")"){
				$depth--;
			}
			if($char === ";" && $depth <= 0){
				$statements[] = $current;
				$current = "";
				continue;
			}
			$current .= $char;
		}
		$statements[] = $current;
		return $statements;
	}

	private function number(mixed $value, float $default) : float{
		if(is_int($value) || is_float($value)){
			return (float) $value;
		}
		if(is_string($value) && is_numeric($value)){
			return (float) $value;
		}
		return $default;
	}
}
