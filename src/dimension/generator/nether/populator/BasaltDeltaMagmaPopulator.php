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
use function in_array;

final class BasaltDeltaMagmaPopulator extends SeededPopulator{

	private const LAVA = ["minecraft:lava", "minecraft:flowing_lava"];

	public function populate(BlockManager $root, ChunkManager $world, int $chunkX, int $chunkZ, int $seed) : void{
		$random = $this->random;
		$random->setSeed($seed ^ ChunkHash::hash($chunkX, $chunkZ));
		$object = new BlockManager($world);
		$amount = $random->nextBoundedInt(4) + 20;
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
				$object->setBlockStateAt($x, $y, $z, "minecraft:magma");
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
			$b1 = $level->getBlockIdAt($x + 1, $y, $z);
			$b2 = $level->getBlockIdAt($x - 1, $y, $z);
			$b3 = $level->getBlockIdAt($x, $y, $z + 1);
			$b4 = $level->getBlockIdAt($x, $y, $z - 1);
			if(($b === "minecraft:basalt" || $b === "minecraft:blackstone") &&
				$level->getBlockIdAt($x, $y + 1, $z) === WorldQuery::AIR && (
					in_array($b1, self::LAVA, true) ||
					in_array($b2, self::LAVA, true) ||
					in_array($b3, self::LAVA, true) ||
					in_array($b4, self::LAVA, true)
				)
			){
				$blockYs[] = $y;
			}
		}
		return $blockYs;
	}
}
