<?php

declare(strict_types=1);

namespace dimension\generator\structure;

use dimension\generator\ChunkHash;
use dimension\generator\math\Int64;
use dimension\generator\object\BlockManager;
use dimension\generator\Populator;
use dimension\generator\random\Xoroshiro128;
use dimension\generator\structure\endcity\EndCityLayout;
use dimension\generator\structure\placement\StructurePlacement;
use dimension\generator\structure\template\StateBlocks;
use pocketmine\world\ChunkManager;
use function abs;
use function array_key_exists;
use function array_key_first;
use function count;
use function min;
use const PHP_INT_MAX;

/**
 * End cities. The outer islands are cut into regions of 20 by 20 chunks,
 * each with one candidate chunk; a city is built there when the chunk lies
 * more than 64 chunks from the world centre and the ground under the city
 * centre is at Y 60 or higher.
 *
 * The city stands on the lowest end stone surface of the 5 by 5 columns at
 * the start chunk centre, so a city can only be planned while its start
 * chunk is part of the population area; every chunk it then crosses writes
 * its own part of the same plan.
 */
final class EndCityPopulator implements Populator{

	private const SPACING = 20;
	private const SEPARATION = 11;
	private const MIN_HEIGHT = 60;
	private const SEARCH_RADIUS_CHUNKS = 8;
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
		$minRegionX = StructurePlacement::floorDiv($chunkX - self::SEARCH_RADIUS_CHUNKS, self::SPACING);
		$maxRegionX = StructurePlacement::floorDiv($chunkX + self::SEARCH_RADIUS_CHUNKS, self::SPACING);
		$minRegionZ = StructurePlacement::floorDiv($chunkZ - self::SEARCH_RADIUS_CHUNKS, self::SPACING);
		$maxRegionZ = StructurePlacement::floorDiv($chunkZ + self::SEARCH_RADIUS_CHUNKS, self::SPACING);
		for($regionX = $minRegionX; $regionX <= $maxRegionX; ++$regionX){
			for($regionZ = $minRegionZ; $regionZ <= $maxRegionZ; ++$regionZ){
				$this->random->setSeed($seed ^ ChunkHash::hash($regionX, $regionZ));
				$startX = $regionX * self::SPACING + $this->random->nextBoundedInt(self::SPACING - self::SEPARATION);
				$startZ = $regionZ * self::SPACING + $this->random->nextBoundedInt(self::SPACING - self::SEPARATION);
				if(abs($startX - $chunkX) > self::SEARCH_RADIUS_CHUNKS || abs($startZ - $chunkZ) > self::SEARCH_RADIUS_CHUNKS){
					continue;
				}
				if($startX * $startX + $startZ * $startZ <= 4096){
					continue;
				}
				$rotation = $this->random->nextInt(4);
				$plan = $this->plan($world, $seed, $startX, $startZ, $rotation);
				if($plan !== null){
					$plan->emit($root, $chunkX, $chunkZ);
				}
			}
		}
	}

	private function plan(ChunkManager $world, int $seed, int $startX, int $startZ, int $rotation) : ?StructureBuffer{
		$key = $seed . ":" . $startX . ":" . $startZ;
		if(array_key_exists($key, self::$plans)){
			return self::$plans[$key];
		}
		$startY = $this->startHeight($world, $startX, $startZ);
		if($startY === null){
			return null;
		}
		if(count(self::$plans) >= self::CACHE_SIZE){
			unset(self::$plans[array_key_first(self::$plans)]);
		}
		return self::$plans[$key] = $this->buildPlan($seed, $startX, $startY, $startZ, $rotation);
	}

	private function buildPlan(int $seed, int $startX, int $startY, int $startZ, int $rotation) : ?StructureBuffer{
		if($startY < self::MIN_HEIGHT){
			return null;
		}
		$pieceRandom = new Xoroshiro128($seed);
		$first = Int64::mul($startX, $pieceRandom->nextInt());
		$second = Int64::mul($startZ, $pieceRandom->nextInt());
		$pieceRandom->setSeed($first ^ $second ^ $seed);

		$buffer = new StructureBuffer();
		$layout = new EndCityLayout($this->resourceFolder);
		if(!$layout->place($buffer, ($startX << 4) + 8, $startY, ($startZ << 4) + 8, $rotation, $pieceRandom)){
			return null;
		}
		return $buffer->isEmpty() ? null : $buffer;
	}

	/**
	 * Lowest first free Y above the end stone over the 5 by 5 columns at
	 * local 7 to 11 of the start chunk, null when that chunk is not
	 * available.
	 */
	private function startHeight(ChunkManager $world, int $startX, int $startZ) : ?int{
		$chunk = $world->getChunk($startX, $startZ);
		if($chunk === null){
			return null;
		}
		$endStone = StateBlocks::state("minecraft:end_stone");
		$endStoneBlock = $endStone === null ? null : StateBlocks::toBlock($endStone);
		if($endStoneBlock === null){
			return null;
		}
		$endStoneId = $endStoneBlock->getStateId();
		$minY = $world->getMinY();
		$maxY = $world->getMaxY();
		$lowest = PHP_INT_MAX;
		for($x = 7; $x <= 11; ++$x){
			for($z = 7; $z <= 11; ++$z){
				$height = $minY;
				for($y = $maxY - 1; $y >= $minY; --$y){
					if($chunk->getBlockStateId($x, $y, $z) === $endStoneId){
						$height = $y + 1;
						break;
					}
				}
				$lowest = min($lowest, $height);
			}
		}
		return $lowest;
	}
}
