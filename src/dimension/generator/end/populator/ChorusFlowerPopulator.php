<?php

declare(strict_types=1);

namespace dimension\generator\end\populator;

use dimension\generator\ChunkHash;
use dimension\generator\end\EndIslands;
use dimension\generator\end\object\ChorusPlant;
use dimension\generator\nether\object\WorldQuery;
use dimension\generator\object\BlockManager;
use dimension\generator\Populator;
use dimension\generator\random\Xoroshiro128;
use pocketmine\world\ChunkManager;

final class ChorusFlowerPopulator implements Populator{

	private Xoroshiro128 $random;
	private ChorusPlant $chorusPlant;

	public function __construct(){
		$this->random = new Xoroshiro128(0);
		$this->chorusPlant = new ChorusPlant();
	}

	public function populate(BlockManager $root, ChunkManager $world, int $chunkX, int $chunkZ, int $seed) : void{
		if($chunkX * $chunkX + $chunkZ * $chunkZ <= 4096){
			return;
		}
		$random = $this->random;
		$random->setSeed($seed ^ ChunkHash::hash($chunkX, $chunkZ));
		if(EndIslands::height($seed, $chunkX, $chunkZ, 1, 1) > 40.0){
			for($i = 0; $i < $random->nextBoundedInt(5); $i++){
				$x = ($chunkX << 4) + $random->nextBoundedInt(16);
				$z = ($chunkZ << 4) + $random->nextBoundedInt(16);
				$y = WorldQuery::heightMap($world, $x, $z);
				if($y > 0){
					if(WorldQuery::blockId($world, $x, $y + 1, $z) === WorldQuery::AIR && WorldQuery::blockId($world, $x, $y, $z) === "minecraft:end_stone"){
						$object = new BlockManager($world);
						$this->chorusPlant->generate($object, $random, $x, $y + 1, $z, 8);
						$root->merge($object);
					}
				}
			}
		}
	}
}
