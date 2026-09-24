<?php

declare(strict_types=1);

namespace dimension\generator\end\populator;

use dimension\generator\end\object\ObsidianPillar;
use dimension\generator\object\BlockManager;
use dimension\generator\Populator;
use pocketmine\math\Vector3;
use pocketmine\world\ChunkManager;

/**
 * Builds the obsidian spikes whose centre lies in the populated chunk.
 *
 * Each spike carries an end crystal, which cannot be spawned from the
 * generation thread: crystalSpawns() gives where they go so the main thread
 * can spawn them once the chunk is populated.
 */
final class ObsidianPillarPopulator implements Populator{

	public function populate(BlockManager $root, ChunkManager $world, int $chunkX, int $chunkZ, int $seed) : void{
		for($i = 0; $i < ObsidianPillar::COUNT; $i++){
			[$x, $z] = ObsidianPillar::position($i);
			if($x >> 4 === $chunkX && $z >> 4 === $chunkZ){
				$object = new BlockManager($world);
				(new ObsidianPillar($i, $seed))->generate($object, $x, $z);
				$root->merge($object);
			}
		}
	}

	/**
	 * End crystal positions of the spikes, with the chunk whose population
	 * builds each spike.
	 *
	 * @return list<array{chunkX: int, chunkZ: int, position: Vector3}>
	 */
	public static function crystalSpawns(int $seed) : array{
		$spawns = [];
		for($i = 0; $i < ObsidianPillar::COUNT; $i++){
			[$x, $z] = ObsidianPillar::position($i);
			$height = (new ObsidianPillar($i, $seed))->getHeight();
			$spawns[] = [
				"chunkX" => $x >> 4,
				"chunkZ" => $z >> 4,
				"position" => new Vector3($x + 0.5, $height + 1, $z + 0.5)
			];
		}
		return $spawns;
	}
}
