<?php

declare(strict_types=1);

namespace dimension\generator\end\populator;

use dimension\generator\ChunkHash;
use dimension\generator\end\EndIslands;
use dimension\generator\end\object\EndIsland;
use dimension\generator\object\BlockManager;
use dimension\generator\Populator;
use dimension\generator\random\LegacyRandom;
use pocketmine\world\ChunkManager;

final class EndIslandPopulator implements Populator{

	private LegacyRandom $random;
	private EndIsland $endIsland;

	public function __construct(){
		$this->random = new LegacyRandom(0);
		$this->endIsland = new EndIsland();
	}

	public function populate(BlockManager $root, ChunkManager $world, int $chunkX, int $chunkZ, int $seed) : void{
		if($chunkX * $chunkX + $chunkZ * $chunkZ <= 4096){
			return;
		}
		$random = $this->random;
		$random->setSeed($seed ^ ChunkHash::hash($chunkX, $chunkZ));
		if($random->nextBoundedInt(14) === 0){
			$height = EndIslands::height($seed, $chunkX, $chunkZ, 1, 1);
			if($height < -20.0){
				$baseX = $chunkX << 4;
				$baseZ = $chunkZ << 4;
				$object = new BlockManager($world);
				$x = $baseX + 8 + $random->nextBoundedInt(16);
				$y = 55 + $random->nextBoundedInt(16);
				$z = $baseZ + 8 + $random->nextBoundedInt(16);
				$this->endIsland->generate($object, $random, $x, $y, $z);
				if($random->nextBoundedInt(4) === 0){
					$x = $baseX + 8 + $random->nextBoundedInt(16);
					$y = 55 + $random->nextBoundedInt(16);
					$z = $baseZ + 8 + $random->nextBoundedInt(16);
					$this->endIsland->generate($object, $random, $x, $y, $z);
				}
				$root->merge($object);
			}
		}
	}
}
