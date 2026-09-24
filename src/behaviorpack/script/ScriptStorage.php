<?php

declare(strict_types=1);

namespace behaviorpack\script;

use Throwable;
use function array_keys;
use function array_search;
use function array_values;
use function file_get_contents;
use function file_put_contents;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function mkdir;
use function str_starts_with;
use function substr;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Keeps what scripts store: dynamic properties and tags. A scope is "world",
 * "p:<player uuid>" for players, saved to disk, or "e:<runtime id>" for other
 * entities, kept for the session only. The scoreboard is stored as the data
 * given by the script runtime.
 */
final class ScriptStorage{

	/** @var array<string, array{dp: array<string, mixed>, tags: list<string>}> */
	private array $scopes = [];

	private mixed $scoreboard = null;

	private bool $dirty = false;

	public function __construct(
		private string $directory
	){}

	public function load() : void{
		$data = $this->readFile("properties.json");
		foreach($data as $scope => $entry){
			if(!is_array($entry)){
				continue;
			}
			$this->scopes[(string) $scope] = [
				"dp" => is_array($entry["dp"] ?? null) ? $entry["dp"] : [],
				"tags" => is_array($entry["tags"] ?? null) ? array_values($entry["tags"]) : []
			];
		}
		$scoreboard = $this->readFile("scoreboard.json");
		$this->scoreboard = $scoreboard === [] ? null : $scoreboard;
	}

	public function save() : void{
		if(!$this->dirty){
			return;
		}
		$this->dirty = false;
		$persistent = [];
		foreach($this->scopes as $scope => $entry){
			if(!str_starts_with($scope, "e:") && ($entry["dp"] !== [] || $entry["tags"] !== [])){
				$persistent[$scope] = ["dp" => (object) $entry["dp"], "tags" => $entry["tags"]];
			}
		}
		$this->writeFile("properties.json", $persistent);
		if($this->scoreboard !== null){
			$this->writeFile("scoreboard.json", $this->scoreboard);
		}
	}

	public function get(string $scope, string $key) : mixed{
		return $this->scopes[$scope]["dp"][$key] ?? null;
	}

	public function set(string $scope, string $key, mixed $value) : void{
		$this->touch($scope);
		if($value === null){
			unset($this->scopes[$scope]["dp"][$key]);
		}else{
			$this->scopes[$scope]["dp"][$key] = $value;
		}
		$this->markDirty($scope);
	}

	/**
	 * @return list<string>
	 */
	public function ids(string $scope) : array{
		return array_keys($this->scopes[$scope]["dp"] ?? []);
	}

	public function clear(string $scope) : void{
		if(isset($this->scopes[$scope])){
			$this->scopes[$scope]["dp"] = [];
			$this->markDirty($scope);
		}
	}

	/**
	 * @return list<string>
	 */
	public function getTags(string $scope) : array{
		return $this->scopes[$scope]["tags"] ?? [];
	}

	public function addTag(string $scope, string $tag) : bool{
		$this->touch($scope);
		if(in_array($tag, $this->scopes[$scope]["tags"], true)){
			return false;
		}
		$this->scopes[$scope]["tags"][] = $tag;
		$this->markDirty($scope);
		return true;
	}

	public function removeTag(string $scope, string $tag) : bool{
		$index = array_search($tag, $this->scopes[$scope]["tags"] ?? [], true);
		if($index === false){
			return false;
		}
		unset($this->scopes[$scope]["tags"][$index]);
		$this->scopes[$scope]["tags"] = array_values($this->scopes[$scope]["tags"]);
		$this->markDirty($scope);
		return true;
	}

	public function forget(string $scope) : void{
		unset($this->scopes[$scope]);
	}

	public function getScoreboard() : mixed{
		return $this->scoreboard;
	}

	public function setScoreboard(mixed $scoreboard) : void{
		$this->scoreboard = $scoreboard;
		$this->dirty = true;
	}

	private function touch(string $scope) : void{
		if(!isset($this->scopes[$scope])){
			$this->scopes[$scope] = ["dp" => [], "tags" => []];
		}
	}

	private function markDirty(string $scope) : void{
		if(substr($scope, 0, 2) !== "e:"){
			$this->dirty = true;
		}
	}

	/**
	 * @return array<mixed>
	 */
	private function readFile(string $name) : array{
		$path = $this->directory . "/" . $name;
		if(!is_file($path)){
			return [];
		}
		try{
			$decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
		}catch(Throwable){
			return [];
		}
		return is_array($decoded) ? $decoded : [];
	}

	private function writeFile(string $name, mixed $data) : void{
		if(!is_dir($this->directory)){
			@mkdir($this->directory, 0777, true);
		}
		try{
			file_put_contents($this->directory . "/" . $name, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		}catch(Throwable){
			$this->dirty = true;
		}
	}
}
