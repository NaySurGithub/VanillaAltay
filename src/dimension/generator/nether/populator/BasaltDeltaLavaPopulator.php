<?php

declare(strict_types=1);

namespace dimension\generator\nether\populator;

use dimension\generator\ChunkHash;
use dimension\generator\math\Float32;
use dimension\generator\math\GenerationMath;
use dimension\generator\holder\BasaltDeltaHolder;
use dimension\generator\nether\object\SeededPopulator;
use dimension\generator\nether\object\WorldQuery;
use dimension\generator\object\BlockManager;
use dimension\generator\random\LegacyRandom;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\world\ChunkManager;

/**
 * Basalt delta surface: lava pockets in the basalt and the gravel placeholder
 * turned into basalt, blackstone, magma or lava from two surface noises.
 */
final class BasaltDeltaLavaPopulator extends SeededPopulator{

	private const GRAVEL = "minecraft:gravel";
	private const HORIZONTALS = [[0, 0, 1], [-1, 0, 0], [0, 0, -1], [1, 0, 0]];

	private ?int $noiseSeed = null;
	private BasaltDeltaHolder $noises;

	public function populate(BlockManager $root, ChunkManager $world, int $chunkX, int $chunkZ, int $seed) : void{
		if($this->noiseSeed !== $seed){
			$this->noises = new BasaltDeltaHolder(new LegacyRandom($seed));
			$this->noiseSeed = $seed;
		}
		$random = $this->random;
		$random->setSeed($seed ^ ChunkHash::hash($chunkX, $chunkZ));
		$amount = $random->nextInt(64) + 64;
		$object = new BlockManager($world);
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
				$object->setBlockStateAt($x, $y, $z, "minecraft:flowing_lava");
			}
		}
		$blackstoneLimit = Float32::of(-0.9);
		$basaltLimit = Float32::of(0.8);
		$baseX = $chunkX << 4;
		$baseZ = $chunkZ << 4;
		for($x = 0; $x < 16; ++$x){
			for($z = 0; $z < 16; ++$z){
				if(WorldQuery::biomeId($world, $x + $baseX, 0, $z + $baseZ) !== BiomeIds::BASALT_DELTAS){
					continue;
				}
				for($y = 1; $y < 127; ++$y){
					$worldX = $x + $baseX;
					$worldZ = $z + $baseZ;
					if($object->getBlockIdAt($worldX, $y, $worldZ) !== self::GRAVEL){
						continue;
					}
					$sec = $this->noises->getSurfaceSecNoise()->getValue($worldX, $y, $worldZ);
					$state = $sec < $blackstoneLimit ? "minecraft:blackstone" : ($sec < $basaltLimit ? "minecraft:basalt" : "minecraft:magma");
					if($this->noises->getSurfaceNoise()->getValue($worldX, $y, $worldZ) > 0.0){
						$object->setBlockStateAt($worldX, $y, $worldZ, $state);
					}else{
						$air = false;
						foreach(self::HORIZONTALS as [$dx, $dy, $dz]){
							if($object->getBlockIdAt($worldX + $dx, $y + $dy, $worldZ + $dz) === WorldQuery::AIR){
								$air = true;
							}
						}
						if($air){
							$object->setBlockStateAt($worldX, $y, $worldZ, $state);
						}else{
							$object->setBlockStateAt($worldX, $y, $worldZ, "minecraft:lava");
						}
					}
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
		for($y = 128; $y > 0; --$y){
			$b = $level->getBlockIdAt($x, $y, $z);
			if(($b === "minecraft:basalt" || $b === "minecraft:blackstone") &&
				$level->getBlockIdAt($x, $y + 1, $z) === WorldQuery::AIR &&
				$level->getBlockIdAt($x + 1, $y, $z) !== WorldQuery::AIR &&
				$level->getBlockIdAt($x - 1, $y, $z) !== WorldQuery::AIR &&
				$level->getBlockIdAt($x, $y, $z + 1) !== WorldQuery::AIR &&
				$level->getBlockIdAt($x, $y, $z - 1) !== WorldQuery::AIR
			){
				$blockYs[] = $y;
			}
		}
		return $blockYs;
	}
}
