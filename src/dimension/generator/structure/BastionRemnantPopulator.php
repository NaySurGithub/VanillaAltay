<?php

declare(strict_types=1);

namespace dimension\generator\structure;

use dimension\generator\object\BlockManager;
use dimension\generator\Populator;
use dimension\generator\random\Xoroshiro128;
use dimension\generator\structure\jigsaw\BastionLayout;
use dimension\generator\structure\placement\NetherComplexPlacement;
use dimension\generator\structure\placement\StructurePlacement;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\world\ChunkManager;
use function abs;
use function array_key_exists;
use function array_key_first;
use function count;

/**
 * Bastion remnants. A bastion starts in the chosen chunk of each 30 by 30
 * chunk region picked for bastions, unless that chunk lies in a basalt
 * delta, and is assembled from template pools with its lowest corner at
 * Y 33. The assembly depends on the world seed only, so every chunk the
 * bastion crosses rebuilds the same plan and writes its own part.
 *
 * The basalt delta check needs the biome of the start chunk, known only
 * when that chunk is part of the population area. Farther chunks assume
 * the start chunk is not a basalt delta.
 */
final class BastionRemnantPopulator implements Populator{

	private const ORIGIN_Y = 33;
	private const SEARCH_RADIUS_CHUNKS = 10;
	private const CACHE_SIZE = 4;

	/** @var array<string, StructureBuffer|null> */
	private static array $plans = [];

	private Xoroshiro128 $random;

	public function __construct(
		private int $seed,
		private string $resourceFolder
	){
		$this->random = new Xoroshiro128(0);
	}

	public function populate(BlockManager $root, ChunkManager $world, int $chunkX, int $chunkZ, int $seed) : void{
		$size = NetherComplexPlacement::REGION_SIZE_CHUNKS;
		$minRegionX = StructurePlacement::floorDiv($chunkX - self::SEARCH_RADIUS_CHUNKS, $size);
		$maxRegionX = StructurePlacement::floorDiv($chunkX + self::SEARCH_RADIUS_CHUNKS, $size);
		$minRegionZ = StructurePlacement::floorDiv($chunkZ - self::SEARCH_RADIUS_CHUNKS, $size);
		$maxRegionZ = StructurePlacement::floorDiv($chunkZ + self::SEARCH_RADIUS_CHUNKS, $size);
		for($regionX = $minRegionX; $regionX <= $maxRegionX; ++$regionX){
			for($regionZ = $minRegionZ; $regionZ <= $maxRegionZ; ++$regionZ){
				[$startX, $startZ] = NetherComplexPlacement::regionStart($seed, $regionX, $regionZ, $this->random);
				if(abs($startX - $chunkX) > self::SEARCH_RADIUS_CHUNKS || abs($startZ - $chunkZ) > self::SEARCH_RADIUS_CHUNKS){
					continue;
				}
				if($this->startBiome($world, $startX, $startZ) === BiomeIds::BASALT_DELTAS){
					continue;
				}
				$plan = $this->plan($seed, $startX, $startZ);
				if($plan !== null){
					$plan->emit($root, $chunkX, $chunkZ);
				}
			}
		}
	}

	/**
	 * Biome sampled at the start chunk, -1 when that chunk is not available.
	 */
	private function startBiome(ChunkManager $world, int $startX, int $startZ) : int{
		$chunk = $world->getChunk($startX, $startZ);
		if($chunk === null){
			return -1;
		}
		return $chunk->getBiomeId(7, self::ORIGIN_Y, 7);
	}

	private function plan(int $seed, int $startX, int $startZ) : ?StructureBuffer{
		$key = $seed . ":" . $startX . ":" . $startZ;
		if(array_key_exists($key, self::$plans)){
			return self::$plans[$key];
		}
		if(count(self::$plans) >= self::CACHE_SIZE){
			unset(self::$plans[array_key_first(self::$plans)]);
		}
		return self::$plans[$key] = $this->buildPlan($seed, $startX, $startZ);
	}

	private function buildPlan(int $seed, int $startX, int $startZ) : ?StructureBuffer{
		if(!NetherComplexPlacement::isNetherComplexStart($seed, $startX, $startZ, $this->random) || !NetherComplexPlacement::shouldGenerateBastion($seed, $startX, $startZ, $this->random)){
			return null;
		}
		$buffer = new StructureBuffer();
		(new BastionLayout())->assemble($buffer, $startX << 4, self::ORIGIN_Y, $startZ << 4, $this->resourceFolder, $this->random->fork());
		return $buffer->isEmpty() ? null : $buffer;
	}
}
