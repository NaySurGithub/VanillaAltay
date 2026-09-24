<?php

declare(strict_types=1);

namespace dimension\network;

use pocketmine\network\mcpe\cache\ChunkCache;
use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\world\World;
use ReflectionProperty;

/**
 * Sets the dimension a world's chunk packet cache serializes for.
 *
 * The core already knows how to serialize nether and end chunks (subchunk
 * range and biome count per dimension, LevelChunkPacket dimension id), but
 * ChunkCache only takes the dimension through its private constructor and
 * always builds itself for the overworld. Chunks are sent as pre-compressed
 * batches that no event sees, so the private dimensionId of the cache is the
 * only place to choose the serialization. Reflection is kept to this class.
 */
final class ChunkCacheDimension{

	private static ?ReflectionProperty $dimensionProperty = null;
	private static ?ReflectionProperty $cachesProperty = null;

	private function __construct(){
	}

	public static function apply(World $world, Compressor $compressor, int $dimension) : void{
		$cache = ChunkCache::getInstance($world, $compressor);
		$dimensionProperty = self::$dimensionProperty ??= new ReflectionProperty(ChunkCache::class, "dimensionId");
		if($dimensionProperty->getValue($cache) === $dimension){
			return;
		}
		$dimensionProperty->setValue($cache, $dimension);
		$cachesProperty = self::$cachesProperty ??= new ReflectionProperty(ChunkCache::class, "caches");
		$cachesProperty->setValue($cache, []);
	}
}
