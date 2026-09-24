<?php

declare(strict_types=1);

namespace behaviorpack;

use Ahc\Json\Comment;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;
use function file_get_contents;
use function implode;
use function is_array;
use function is_dir;
use function is_string;
use function rtrim;
use function sort;
use function str_replace;
use function strlen;
use function strtolower;
use function substr;

/**
 * A behavior pack found on disk, identified by its manifest.json.
 */
final class BehaviorPack{

	/**
	 * @param array<string, mixed> $manifest
	 */
	public function __construct(
		private string $path,
		private array $manifest
	){
		$this->path = rtrim(str_replace("\\", "/", $path), "/");
	}

	public function getPath() : string{
		return $this->path;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getManifest() : array{
		return $this->manifest;
	}

	public function getName() : string{
		$name = $this->manifest["header"]["name"] ?? null;
		return is_string($name) ? $name : $this->path;
	}

	public function getUuid() : string{
		$uuid = $this->manifest["header"]["uuid"] ?? null;
		return is_string($uuid) ? strtolower($uuid) : "";
	}

	public function getVersion() : string{
		$version = $this->manifest["header"]["version"] ?? null;
		if(is_array($version)){
			return implode(".", $version);
		}
		return is_string($version) ? $version : "0.0.0";
	}

	/**
	 * Returns the manifest modules of the given type ("data", "script", ...).
	 *
	 * @return list<array<string, mixed>>
	 */
	public function getModules(string $type) : array{
		$modules = [];
		foreach($this->manifest["modules"] ?? [] as $module){
			if(is_array($module) && ($module["type"] ?? null) === $type){
				$modules[] = $module;
			}
		}
		return $modules;
	}

	/**
	 * Returns the path of the script entry relative to the pack root, or null
	 * when the pack declares no script module.
	 */
	public function getScriptEntry() : ?string{
		foreach(["script", "javascript"] as $type){
			foreach($this->getModules($type) as $module){
				$entry = $module["entry"] ?? null;
				if(is_string($entry) && $entry !== ""){
					return $entry;
				}
			}
		}
		return null;
	}

	/**
	 * Returns the "module_name => version" pairs of the manifest dependencies
	 * that reference a script module, such as "@minecraft/server".
	 *
	 * @return array<string, string>
	 */
	public function getScriptDependencies() : array{
		$dependencies = [];
		foreach($this->manifest["dependencies"] ?? [] as $dependency){
			if(!is_array($dependency)){
				continue;
			}
			$name = $dependency["module_name"] ?? null;
			$version = $dependency["version"] ?? null;
			if(is_string($name)){
				$dependencies[$name] = is_array($version) ? implode(".", $version) : (is_string($version) ? $version : "");
			}
		}
		return $dependencies;
	}

	public function hasDirectory(string $directory) : bool{
		return is_dir($this->path . "/" . $directory);
	}

	/**
	 * Returns the absolute paths of the files with the given extension found
	 * recursively in a directory of the pack, sorted, or an empty list when
	 * the directory does not exist.
	 *
	 * @return list<string>
	 */
	public function listFiles(string $directory, string $extension = "json") : array{
		$root = $this->path . "/" . $directory;
		if(!is_dir($root)){
			return [];
		}

		$suffix = "." . strtolower($extension);
		$files = [];
		/** @var SplFileInfo $file */
		foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file){
			$name = strtolower($file->getFilename());
			if($file->isFile() && substr($name, -strlen($suffix)) === $suffix){
				$files[] = str_replace("\\", "/", $file->getPathname());
			}
		}
		sort($files);
		return $files;
	}

	/**
	 * Returns a path relative to the pack root, as used by the Bedrock files
	 * that reference each other (for example "loot_tables/blocks/ore.json").
	 */
	public function relativePath(string $absolutePath) : string{
		$absolutePath = str_replace("\\", "/", $absolutePath);
		$prefix = $this->path . "/";
		return substr($absolutePath, 0, strlen($prefix)) === $prefix ? substr($absolutePath, strlen($prefix)) : $absolutePath;
	}

	/**
	 * Reads a Bedrock JSON file, which may contain comments.
	 *
	 * @return array<mixed>
	 * @throws BehaviorPackException
	 */
	public static function readJson(string $absolutePath) : array{
		$contents = @file_get_contents($absolutePath);
		if($contents === false){
			throw new BehaviorPackException("Cannot read $absolutePath");
		}
		try{
			$decoded = (new Comment())->decode($contents, true);
		}catch(Throwable $e){
			throw new BehaviorPackException("Invalid JSON in $absolutePath: " . $e->getMessage(), 0, $e);
		}
		if(!is_array($decoded)){
			throw new BehaviorPackException("Expected a JSON object in $absolutePath");
		}
		return $decoded;
	}
}
