<?php

declare(strict_types=1);

namespace dimension\generator\nether\populator;

use dimension\generator\ChunkHash;
use dimension\generator\math\GenerationMath;
use dimension\generator\nether\object\SeededPopulator;
use dimension\generator\nether\object\WorldQuery;
use dimension\generator\object\BlockManager;
use pocketmine\world\ChunkManager;

final class FirePopulator extends SeededPopulator{

	private const NETHERRACK = "minecraft:netherrack";

	public function populate(BlockManager $root, ChunkManager $world, int $chunkX, int $chunkZ, int $seed) : void{
		$random = $this->random;
		$random->setSeed($seed ^ ChunkHash::hash($chunkX, $chunkZ));
		$object = new BlockManager($world);
		$amount = $random->nextInt(2) + 1;

		for($i = 0; $i < $amount; ++$i){
			$x = GenerationMath::randomRange($random, $chunkX << 4, ($chunkX << 4) + 15);
			$z = GenerationMath::randomRange($random, $chunkZ << 4, ($chunkZ << 4) + 15);
			foreach($this->getHighestWorkableBlocks($object, $x, $z) as $y){
				if($y <= 1){
					continue;
				}
				if($random->nextInt(4) === 1){
					continue;
				}
				$object->setBlockStateAt($x, $y, $z, $object->getBlockIdAt($x, $y - 1, $z) === self::NETHERRACK ? "minecraft:fire" : "minecraft:soul_fire");
			}
		}
		$root->merge($object);
	}

	/**
	 * @return int[]
	 */
	private function getHighestWorkableBlocks(BlockManager $level, int $x, int $z) : array{
		$blockYs = [];
		for($y = 128; $y > 0; --$y){
			$b = $level->getBlockIdAt($x, $y, $z);
			if(($b === self::NETHERRACK || $b === "minecraft:soul_sand" || $b === "minecraft:soul_soil") && $level->getBlockIdAt($x, $y + 1, $z) === WorldQuery::AIR){
				$blockYs[] = $y + 1;
			}
		}
		return $blockYs;
	}
}
