<?php

declare(strict_types=1);

namespace dimension\generator\nether\populator;

use dimension\generator\ChunkHash;
use dimension\generator\math\GenerationMath;
use dimension\generator\nether\object\SeededPopulator;
use dimension\generator\nether\object\WorldQuery;
use dimension\generator\object\BlockManager;
use pocketmine\world\ChunkManager;

final class LavaPopulator extends SeededPopulator{

	public function populate(BlockManager $root, ChunkManager $world, int $chunkX, int $chunkZ, int $seed) : void{
		$random = $this->random;
		$random->setSeed($seed ^ ChunkHash::hash($chunkX, $chunkZ));
		$object = new BlockManager($world);
		$amount = $random->nextBoundedInt(30) - 29;

		for($i = 0; $i < $amount; ++$i){
			$x = GenerationMath::randomRange($random, $chunkX << 4, ($chunkX << 4) + 15);
			$z = GenerationMath::randomRange($random, $chunkZ << 4, ($chunkZ << 4) + 15);
			$y = $this->getHighestWorkableBlock($object, $x, $z);
			if($y <= 1){
				continue;
			}
			if($random->nextInt(4) === 1){
				continue;
			}
			$object->setBlockStateAt($x, $y + 1, $z, "minecraft:lava");
		}
		$root->merge($object);
	}

	private function getHighestWorkableBlock(BlockManager $level, int $x, int $z) : int{
		for($y = 127; $y >= 0; $y--){
			if($level->getBlockIdAt($x, $y, $z) === WorldQuery::AIR){
				break;
			}
		}
		return $y === 0 ? -1 : $y;
	}
}
