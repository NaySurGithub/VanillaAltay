<?php

declare(strict_types=1);

namespace dimension\generator\nether\populator;

use dimension\generator\ChunkHash;
use dimension\generator\math\GenerationMath;
use dimension\generator\nether\object\SeededPopulator;
use dimension\generator\nether\object\WorldQuery;
use dimension\generator\object\BlockManager;
use pocketmine\world\ChunkManager;

final class GlowstonePopulator extends SeededPopulator{

	private const GLOWSTONE = "minecraft:glowstone";
	private const NETHERRACK = "minecraft:netherrack";

	private const FACES = [[0, -1, 0], [0, 1, 0], [0, 0, -1], [0, 0, 1], [-1, 0, 0], [1, 0, 0]];

	public function populate(BlockManager $root, ChunkManager $world, int $chunkX, int $chunkZ, int $seed) : void{
		$random = $this->random;
		$random->setSeed($seed ^ ChunkHash::hash($chunkX, $chunkZ));
		$object = new BlockManager($world);
		if($random->nextInt(11) === 0){
			$x = GenerationMath::randomRange($random, $chunkX << 4, ($chunkX << 4) + 15);
			$z = GenerationMath::randomRange($random, $chunkZ << 4, ($chunkZ << 4) + 15);
			$y = $this->getHighestWorkableBlock($world, $x, $z);
			if($y !== -1 && WorldQuery::blockId($world, $x, $y, $z) !== self::NETHERRACK){
				$count = GenerationMath::randomRange($random, 40, 60);
				$object->setBlockStateAt($x, $y, $z, self::GLOWSTONE);
				$cyclesNum = 0;
				while($count !== 0){
					if($cyclesNum === 1500){
						break;
					}
					$spawnX = $x + $random->nextInt(9) - $random->nextInt(9);
					$spawnY = $y - $random->nextInt(9);
					$spawnZ = $z + $random->nextInt(9) - $random->nextInt(9);
					if($cyclesNum % 128 === 0 && $cyclesNum !== 0){
						$object->setBlockStateAt($x + $random->nextRangeInt(-3, 3), $y - $random->nextInt(5), $z + $random->nextRangeInt(-3, 3), self::GLOWSTONE);
						$count--;
					}
					if($this->checkAroundBlock($spawnX, $spawnY, $spawnZ, $object)){
						$object->setBlockStateAt($spawnX, $spawnY, $spawnZ, self::GLOWSTONE);
						$count--;
					}
					$cyclesNum++;
				}
			}
		}
		$root->merge($object);
	}

	private function getHighestWorkableBlock(ChunkManager $world, int $x, int $z) : int{
		for($y = 125; $y >= 0; $y--){
			if(WorldQuery::blockId($world, $x, $y, $z) === WorldQuery::AIR){
				break;
			}
		}
		return $y === 0 ? -1 : $y;
	}

	private function checkAroundBlock(int $x, int $y, int $z, BlockManager $level) : bool{
		foreach(self::FACES as [$dx, $dy, $dz]){
			if($level->getBlockIdAt($x + $dx, $y + $dy, $z + $dz) === self::GLOWSTONE){
				return true;
			}
		}
		return false;
	}
}
