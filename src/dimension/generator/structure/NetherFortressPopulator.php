<?php

declare(strict_types=1);

namespace dimension\generator\structure;

use dimension\generator\math\Int64;
use dimension\generator\object\BlockManager;
use dimension\generator\Populator;
use dimension\generator\random\LegacyRandom;
use dimension\generator\random\Xoroshiro128;
use dimension\generator\structure\fortress\FortressLayout;
use dimension\generator\structure\placement\NetherComplexPlacement;
use dimension\generator\structure\placement\StructurePlacement;
use pocketmine\world\ChunkManager;
use function abs;
use function array_key_first;
use function count;

/**
 * Nether fortresses. A fortress starts in the chosen chunk of each 30 by 30
 * chunk region that does not hold a bastion. Its layout depends on the
 * world seed only, so every chunk the fortress crosses rebuilds the same
 * layout and writes its own part, with the chunk random the whole fortress
 * would use for that chunk.
 */
final class NetherFortressPopulator implements Populator{

	private const SEARCH_RADIUS_CHUNKS = 10;
	private const CACHE_SIZE = 8;

	/** @var array<string, FortressLayout|null> */
	private static array $layouts = [];

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
				$this->populateFrom($root, $world, $seed, $startX, $startZ, $chunkX, $chunkZ);
			}
		}
	}

	private function populateFrom(BlockManager $root, ChunkManager $world, int $seed, int $startX, int $startZ, int $chunkX, int $chunkZ) : void{
		if(!NetherComplexPlacement::isNetherComplexStart($seed, $startX, $startZ, $this->random) || NetherComplexPlacement::shouldGenerateBastion($seed, $startX, $startZ, $this->random)){
			return;
		}
		$this->random->setSeed($seed);
		$r1 = $this->random->nextInt();
		$r2 = $this->random->nextInt();

		$layout = $this->layout($seed, $startX, $startZ);
		if($layout === null || !$layout->isValid()){
			return;
		}
		$box = $layout->getBoundingBox();
		if($chunkX < ($box->x0 >> 4) || $chunkX > ($box->x1 >> 4) || $chunkZ < ($box->z0 >> 4) || $chunkZ > ($box->z1 >> 4)){
			return;
		}
		$chunkRandom = new LegacyRandom(Int64::mul($chunkX, $r1) ^ Int64::mul($chunkZ, $r2) ^ $seed);
		$manager = new BlockManager($world);
		$layout->postProcess($manager, $chunkRandom, BoundingBox::chunk($chunkX, $chunkZ), $chunkX, $chunkZ);
		$root->merge($manager);
	}

	private function layout(int $seed, int $startX, int $startZ) : ?FortressLayout{
		$key = $seed . ":" . $startX . ":" . $startZ;
		if(!isset(self::$layouts[$key])){
			if(count(self::$layouts) >= self::CACHE_SIZE){
				unset(self::$layouts[array_key_first(self::$layouts)]);
			}
			self::$layouts[$key] = new FortressLayout($seed, $startX, $startZ);
		}
		return self::$layouts[$key];
	}
}
