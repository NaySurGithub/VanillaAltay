<?php

declare(strict_types=1);

namespace dimension\generator\nether\populator;

use dimension\generator\ChunkHash;
use dimension\generator\math\GenerationMath;
use dimension\generator\nether\object\SeededPopulator;
use dimension\generator\nether\object\WorldQuery;
use dimension\generator\object\BlockManager;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\world\ChunkManager;

final class BasaltDeltaPillarPopulator extends SeededPopulator{

	public function populate(BlockManager $root, ChunkManager $world, int $chunkX, int $chunkZ, int $seed) : void{
		$random = $this->random;
		$random->setSeed($seed ^ ChunkHash::hash($chunkX, $chunkZ));
		$object = new BlockManager($world);
		$amount = $random->nextBoundedInt(128) + 128;
		$maxHeight = $world->getMaxY();
		for($i = 0; $i < $amount; ++$i){
			$x = GenerationMath::randomRange($random, $chunkX << 4, ($chunkX << 4) + 15);
			$z = GenerationMath::randomRange($random, $chunkZ << 4, ($chunkZ << 4) + 15);
			if(WorldQuery::biomeId($world, $x, 0, $z) !== BiomeIds::BASALT_DELTAS){
				continue;
			}
			foreach($this->getHighestWorkableBlocks($object, $x, $z) as $y){
				if($y <= 1){
					continue;
				}
				if($random->nextBoundedInt(5) === 0){
					continue;
				}
				for($randomHeight = 0; $randomHeight < $random->nextBoundedInt(5) + 1; $randomHeight++){
					$placeLocation = $y + $randomHeight;
					if($placeLocation >= $maxHeight){
						break;
					}
					$object->setBlockStateAt($x, $placeLocation, $z, "minecraft:basalt");
				}
			}
		}
		$root->merge($object);
	}

	/**
	 * @return int[]
	 */
	private function getHighestWorkableBlocks(BlockManager $level, int $x, int $z) : array{
		$blockYs = [];
		for($y = 126; $y > 1; --$y){
			if($level->getBlockIdAt($x, $y, $z) === "minecraft:blackstone" && $level->getBlockIdAt($x, $y + 1, $z) === WorldQuery::AIR){
				$blockYs[] = $y + 1;
			}
		}
		return $blockYs;
	}
}
