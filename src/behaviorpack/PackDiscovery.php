<?php

declare(strict_types=1);

namespace behaviorpack;

use Logger;
use ZipArchive;
use function basename;
use function explode;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function mkdir;
use function pathinfo;
use function scandir;
use function sha1_file;
use function str_replace;
use function strtolower;
use function touch;
use function usort;
use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;

/**
 * Finds the behavior packs inside a folder: extracted packs, .mcpack, .mcaddon
 * and .zip archives, including archives nested inside other archives. Archives
 * are extracted into a cache folder keyed by their SHA-1, so an unchanged
 * archive is not extracted again. Resource packs are skipped.
 */
final class PackDiscovery{

	private const ARCHIVE_EXTENSIONS = ["mcpack", "mcaddon", "zip"];

	/** @var array<string, BehaviorPack> */
	private array $packs = [];

	public function __construct(
		private string $cacheDirectory,
		private Logger $logger
	){}

	/**
	 * @return list<BehaviorPack>
	 */
	public function discover(string $directory) : array{
		$this->packs = [];
		if(!is_dir($directory)){
			@mkdir($directory, 0777, true);
			return [];
		}
		$this->scan($directory);

		$packs = [];
		foreach($this->packs as $pack){
			$packs[] = $pack;
		}
		usort($packs, fn(BehaviorPack $a, BehaviorPack $b) : int => $a->getName() <=> $b->getName());
		return $packs;
	}

	private function scan(string $directory) : void{
		if(is_file($directory . "/manifest.json")){
			$this->addPack($directory);
			return;
		}

		$entries = scandir($directory);
		if($entries === false){
			return;
		}
		foreach($entries as $entry){
			if($entry === "." || $entry === ".."){
				continue;
			}
			$path = $directory . "/" . $entry;
			if(is_dir($path)){
				$this->scan($path);
			}elseif(in_array(strtolower(pathinfo($entry, PATHINFO_EXTENSION)), self::ARCHIVE_EXTENSIONS, true)){
				$extracted = $this->extract($path);
				if($extracted !== null){
					$this->scan($extracted);
				}
			}
		}
	}

	private function extract(string $archive) : ?string{
		$hash = sha1_file($archive);
		if($hash === false){
			$this->logger->warning("Cannot read the archive " . basename($archive));
			return null;
		}

		$target = $this->cacheDirectory . "/" . pathinfo($archive, PATHINFO_FILENAME) . "-" . $hash;
		$marker = $target . "/.extracted";
		if(is_file($marker)){
			return $target;
		}

		$zip = new ZipArchive();
		if($zip->open($archive) !== true){
			$this->logger->warning("Cannot open the archive " . basename($archive));
			return null;
		}
		@mkdir($target, 0777, true);
		for($i = 0; $i < $zip->numFiles; $i++){
			$name = $zip->getNameIndex($i);
			if($name === false || !self::isSafeEntry($name)){
				$zip->close();
				$this->logger->warning("Unsafe path in the archive " . basename($archive) . ", skipped");
				return null;
			}
		}
		$extracted = $zip->extractTo($target);
		$zip->close();
		if(!$extracted){
			$this->logger->warning("Cannot extract the archive " . basename($archive));
			return null;
		}
		touch($marker);
		return $target;
	}

	private static function isSafeEntry(string $name) : bool{
		$name = str_replace("\\", "/", $name);
		if($name === "" || $name[0] === "/" || (isset($name[1]) && $name[1] === ":")){
			return false;
		}
		foreach(explode("/", $name) as $part){
			if($part === ".."){
				return false;
			}
		}
		return true;
	}

	private function addPack(string $directory) : void{
		try{
			$manifest = BehaviorPack::readJson($directory . "/manifest.json");
		}catch(BehaviorPackException $e){
			$this->logger->warning($e->getMessage());
			return;
		}

		$types = [];
		foreach($manifest["modules"] ?? [] as $module){
			if(is_array($module) && isset($module["type"])){
				$types[] = $module["type"];
			}
		}
		if(!in_array("data", $types, true) && !in_array("script", $types, true) && !in_array("javascript", $types, true)){
			return;
		}

		$pack = new BehaviorPack($directory, $manifest);
		$key = $pack->getUuid() !== "" ? $pack->getUuid() : $pack->getPath();
		if(isset($this->packs[$key])){
			$this->logger->warning("Behavior pack " . $pack->getName() . " found twice, keeping the first one");
			return;
		}
		$this->packs[$key] = $pack;
	}
}
